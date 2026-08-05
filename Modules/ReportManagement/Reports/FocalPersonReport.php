<?php

namespace Modules\ReportManagement\Reports;

use App\Models\User;
use Modules\ApplicationManagement\Models\ShareApplication;

/**
 * Focal Personwise Summary — shares and deposits credited to each focal person,
 * broken out by offering.
 *
 * Attribution comes from the application's own focal_person_id, falling back to
 * the applicant's profile default only for applications created before that
 * column existed. That is what keeps a re-attributed applicant from rewriting
 * figures already reported against an earlier issue.
 *
 * Applications credited to nobody are summed into one Direct / Unassigned row
 * rather than dropped, so the report's total reconciles with the issue.
 */
class FocalPersonReport extends BaseReport
{
    /** Row key for applications with no focal person on either side. */
    private const UNASSIGNED = 0;

    public function key(): string
    {
        return 'focal-persons';
    }

    public function title(): string
    {
        return 'Focal Personwise Summary';
    }

    public function description(): string
    {
        return 'Shares and deposit amounts credited to each focal person, per share offering. '
            .'Applications with no focal person are grouped as Direct / Unassigned.';
    }

    public function filters(): array
    {
        return $this->issueFilters();
    }

    public function columns(array $filters = []): array
    {
        $columns = [
            ['key' => 'sn', 'label' => 'S.N.', 'align' => 'right'],
            ['key' => 'focal_person', 'label' => 'Focal Person'],
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'applicants', 'label' => 'Applicants', 'align' => 'right'],
        ];

        foreach ($this->offerings($filters) as $offering) {
            $label = $this->offeringLabel($offering);

            $columns[] = ['key' => 'offering_'.$offering->id.'_shares', 'label' => $label.' — No. of Shares', 'align' => 'right'];
            $columns[] = ['key' => 'offering_'.$offering->id.'_amount', 'label' => $label.' — Deposit Amount', 'align' => 'right'];
        }

        $columns[] = ['key' => 'total_shares', 'label' => 'Total Shares', 'align' => 'right'];
        $columns[] = ['key' => 'total_amount', 'label' => 'Total Deposit', 'align' => 'right'];

        return $columns;
    }

    public function rows(array $filters = []): array
    {
        $offerings = $this->offerings($filters);

        if ($offerings->isEmpty()) {
            return [];
        }

        $applications = ShareApplication::query()
            // Columns are qualified because of the join, and status is selected
            // so the model's own accessors stay usable.
            ->whereIn('share_applications.status', $this->holdingStatuses())
            ->whereIn('share_applications.share_offering_id', $offerings->pluck('id'))
            // COALESCE in SQL so the fallback is applied before grouping.
            ->leftJoin('profiles', 'profiles.id', '=', 'share_applications.applicant_id')
            ->selectRaw(
                'share_applications.id,
                 share_applications.status,
                 share_applications.share_offering_id,
                 share_applications.applicant_id,
                 share_applications.shares_applied,
                 COALESCE(share_applications.focal_person_id, profiles.focal_person_id) as credited_to'
            )
            ->get();

        if ($applications->isEmpty()) {
            return [];
        }

        $deposits = $this->verifiedDepositsByApplication($applications->pluck('id')->all());
        $names = $this->focalPersonNames($applications->pluck('credited_to')->filter()->unique()->all());

        $grouped = [];

        foreach ($applications as $application) {
            $key = (int) ($application->credited_to ?: self::UNASSIGNED);
            $offeringId = (int) $application->share_offering_id;

            $grouped[$key]['applicants'][(int) $application->applicant_id] = true;
            $grouped[$key]['shares'][$offeringId] = ($grouped[$key]['shares'][$offeringId] ?? 0) + (int) $application->shares_applied;
            $grouped[$key]['amount'][$offeringId] = ($grouped[$key]['amount'][$offeringId] ?? 0)
                + (float) ($deposits[$application->id] ?? 0);
        }

        // Named focal persons first, alphabetically; the unassigned bucket last,
        // since it is a residual rather than a person.
        uksort($grouped, function (int $a, int $b) use ($names) {
            if ($a === self::UNASSIGNED) {
                return 1;
            }

            if ($b === self::UNASSIGNED) {
                return -1;
            }

            return strcmp($names[$a]['name'] ?? '', $names[$b]['name'] ?? '');
        });

        $rows = [];
        $serial = 0;

        foreach ($grouped as $key => $totals) {
            $row = [
                'sn' => ++$serial,
                'focal_person' => $key === self::UNASSIGNED
                    ? 'Direct / Unassigned'
                    : ($names[$key]['name'] ?? 'User #'.$key),
                'code' => $key === self::UNASSIGNED ? '—' : ($names[$key]['code'] ?? '—'),
                'applicants' => count($totals['applicants']),
            ];

            foreach ($offerings as $offering) {
                $row['offering_'.$offering->id.'_shares'] = number_format($totals['shares'][$offering->id] ?? 0);
                $row['offering_'.$offering->id.'_amount'] = number_format($totals['amount'][$offering->id] ?? 0, 2, '.', ',');
            }

            $row['total_shares'] = number_format(array_sum($totals['shares']));
            $row['total_amount'] = number_format(array_sum($totals['amount']), 2, '.', ',');

            $rows[] = $row;
        }

        return $rows;
    }

    public function totals(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $totals = [
            'applicants' => number_format($this->sumColumn($rows, 'applicants')),
            'total_shares' => number_format($this->sumColumn($rows, 'total_shares')),
            'total_amount' => number_format($this->sumColumn($rows, 'total_amount'), 2, '.', ','),
        ];

        foreach (array_keys($rows[0]) as $key) {
            if (! str_starts_with($key, 'offering_')) {
                continue;
            }

            $totals[$key] = str_ends_with($key, '_amount')
                ? number_format($this->sumColumn($rows, $key), 2, '.', ',')
                : number_format($this->sumColumn($rows, $key));
        }

        return $totals;
    }

    /**
     * Names and codes for the credited users, including any whose designation
     * has since been removed — the history still has to name them.
     *
     * @param  array<int, mixed>  $userIds
     * @return array<int, array{name: string, code: string|null}>
     */
    protected function focalPersonNames(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'focal_person_code'])
            ->mapWithKeys(fn (User $user) => [
                $user->id => ['name' => $user->name, 'code' => $user->focal_person_code],
            ])
            ->all();
    }

    /**
     * The same figures the dashboard widget shows: shares and deposits per
     * focal person across every open-or-past offering, biggest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dashboardSummary(int $limit = 5): array
    {
        $rows = $this->rows();

        $shares = fn (array $row) => (int) str_replace(',', '', (string) $row['total_shares']);

        usort($rows, fn (array $a, array $b) => $shares($b) <=> $shares($a));

        return array_map(fn (array $row) => [
            'name' => $row['focal_person'],
            'code' => $row['code'],
            'shares' => $row['total_shares'],
            'amount' => $row['total_amount'],
        ], array_slice($rows, 0, $limit));
    }
}
