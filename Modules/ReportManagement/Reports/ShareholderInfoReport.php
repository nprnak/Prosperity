<?php

namespace Modules\ReportManagement\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\ApplicantManagement\Models\Profile;
use Modules\CompanyManagement\Models\ShareOffering;

/**
 * KYC Information — one row per shareholder, with a column per share offering
 * carrying the kitta approved in it.
 *
 * The per-offering columns are generated from the offerings in filter scope, so
 * a new issue appears in the report without a code change. Narrowing to one
 * company or offering narrows the columns with it.
 */
class ShareholderInfoReport extends BaseReport
{
    public function key(): string
    {
        return 'shareholders';
    }

    public function title(): string
    {
        return 'Shareholder KYC Information';
    }

    public function description(): string
    {
        return 'Contact details and KYC state per shareholder, with approved kitta broken out by '
            .'share offering. Filter to those who have applied, or to those who have not.';
    }

    public function filters(): array
    {
        return array_merge($this->issueFilters(), [
            [
                'key' => 'participation',
                'label' => 'Participation',
                'type' => 'select',
                'placeholder' => 'Everyone',
                'options' => [
                    ['value' => 'applied', 'label' => 'Applied (in scope)'],
                    ['value' => 'not_applied', 'label' => 'Not applied (in scope)'],
                ],
            ],
        ]);
    }

    public function columns(array $filters = []): array
    {
        $columns = [
            ['key' => 'sn', 'label' => 'S.N.', 'align' => 'right'],
            ['key' => 'username', 'label' => 'Username'],
            ['key' => 'email', 'label' => 'Mail ID'],
            ['key' => 'phone', 'label' => 'Phone No.'],
            ['key' => 'kyc_status', 'label' => 'KYC Status'],
            ['key' => 'kyc_completion', 'label' => 'KYC Updated', 'align' => 'right'],
            ['key' => 'boid', 'label' => 'BOID', 'default' => false],
            ['key' => 'citizenship_number', 'label' => 'Citizenship No.', 'default' => false],
            ['key' => 'pan_number', 'label' => 'PAN', 'default' => false],
            ['key' => 'bank_account', 'label' => 'Bank Account', 'default' => false],
            ['key' => 'focal_person', 'label' => 'Focal Person', 'default' => false],
        ];

        foreach ($this->offerings($filters) as $offering) {
            $columns[] = [
                'key' => 'offering_'.$offering->id,
                'label' => 'Kitta — '.$this->offeringLabel($offering),
                'align' => 'right',
            ];
        }

        $columns[] = ['key' => 'total_kitta', 'label' => 'Total Kitta', 'align' => 'right'];

        return $columns;
    }

    public function rows(array $filters = []): array
    {
        $offerings = $this->offerings($filters);
        $approvedByOffering = $this->approvedSharesByProfileAndOffering($offerings);

        $participation = $filters['participation'] ?? null;

        $profiles = Profile::query()
            // permanentAddress and documents are loaded because
            // completionPercent() reads them — without this the KYC column
            // costs two queries per shareholder.
            ->with([
                'user:id,name,email',
                'focalPerson:id,name,focal_person_code',
                'permanentAddress',
                'documents:id,profile_id,document_type',
            ])
            ->when($participation === 'applied', fn ($q) => $q->whereIn('id', array_keys($approvedByOffering)))
            ->when($participation === 'not_applied', fn ($q) => $q->whereNotIn('id', array_keys($approvedByOffering) ?: [0]))
            ->orderBy('full_name_en')
            ->get();

        $rows = [];
        $serial = 0;

        foreach ($profiles as $profile) {
            $perOffering = $approvedByOffering[$profile->id] ?? [];

            $row = [
                'sn' => ++$serial,
                'username' => $profile->user?->name ?: $profile->full_name_en,
                'email' => $profile->email ?: $profile->user?->email ?: '—',
                'phone' => $profile->mobile ?: '—',
                'kyc_status' => $profile->profile_status?->labelEn() ?: '—',
                'kyc_completion' => $profile->completionPercent().'%',
                'boid' => $profile->boid ?: '—',
                'citizenship_number' => $profile->citizenship_number ?: '—',
                'pan_number' => $profile->pan_number ?: '—',
                'bank_account' => $this->bankAccount($profile),
                'focal_person' => $profile->focalPerson
                    ? $profile->focalPerson->name.' ('.$profile->focalPerson->focal_person_code.')'
                    : '—',
            ];

            foreach ($offerings as $offering) {
                $row['offering_'.$offering->id] = number_format($perOffering[$offering->id] ?? 0);
            }

            $row['total_kitta'] = number_format(array_sum($perOffering));

            $rows[] = $row;
        }

        return $rows;
    }

    public function totals(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $totals = ['total_kitta' => number_format($this->sumColumn($rows, 'total_kitta'))];

        foreach (array_keys($rows[0]) as $key) {
            if (str_starts_with($key, 'offering_')) {
                $totals[$key] = number_format($this->sumColumn($rows, $key));
            }
        }

        return $totals;
    }

    /**
     * Approved kitta per profile per offering, in one query.
     *
     * @param  Collection<int, ShareOffering>  $offerings
     * @return array<int, array<int, int>> profile id => [offering id => shares]
     */
    protected function approvedSharesByProfileAndOffering($offerings): array
    {
        if ($offerings->isEmpty()) {
            return [];
        }

        $totals = DB::table('share_applications')
            ->selectRaw('applicant_id, share_offering_id, SUM(shares_applied) as shares')
            ->whereIn('status', $this->holdingStatuses())
            ->whereIn('share_offering_id', $offerings->pluck('id'))
            ->groupBy('applicant_id', 'share_offering_id')
            ->get();

        $byProfile = [];

        foreach ($totals as $total) {
            $byProfile[(int) $total->applicant_id][(int) $total->share_offering_id] = (int) $total->shares;
        }

        return $byProfile;
    }

    private function bankAccount(Profile $profile): string
    {
        return $profile->bank_account_number
            ? trim(($profile->bank_name ?: '').' '.$profile->bank_account_number)
            : '—';
    }
}
