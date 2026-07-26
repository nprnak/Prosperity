<?php

namespace Modules\ReportManagement\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\ReportManagement\Reports\Contracts\Report;

/**
 * Shared scaffolding for the prescribed reports: the filter descriptors they
 * have in common, the per-offering column expansion two of them need, and the
 * "which applications count as a holding" question they must all answer the
 * same way.
 */
abstract class BaseReport implements Report
{
    /**
     * Par value per share. The Share Lagat format defines चुक्ता भएको रकम as
     * shares × 100 regardless of what the offering charged, with anything above
     * that being premium, so the figure is fixed here rather than read from the
     * offering rate.
     */
    public const PAR_VALUE = 100;

    public function titleNp(): ?string
    {
        return null;
    }

    public function totals(array $rows): array
    {
        return [];
    }

    public function totalsLabel(): string
    {
        return 'Total';
    }

    public function heading(array $filters = []): ?array
    {
        return null;
    }

    public function usesNepaliDates(): bool
    {
        return false;
    }

    /**
     * Applications that count as a holding: final sign-off given, and not
     * since dropped from allotment or refunded. Every report that counts
     * "approved" shares uses this one list so the figures reconcile.
     *
     * @return array<int, string>
     */
    protected function holdingStatuses(): array
    {
        return array_map(fn (ApplicationStatus $status) => $status->value, [
            ApplicationStatus::Approved,
            ApplicationStatus::Allotted,
            ApplicationStatus::PartiallyAllotted,
            ApplicationStatus::DematCredited,
        ]);
    }

    /**
     * Company and offering pickers, shared by every report that scopes to an
     * issue. The offering list carries company_id so the UI can narrow it.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function issueFilters(): array
    {
        return [
            [
                'key' => 'company_id',
                'label' => 'Company',
                'type' => 'select',
                'placeholder' => 'All companies',
                'options' => Company::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Company $company) => ['value' => $company->id, 'label' => $company->name])
                    ->all(),
            ],
            [
                'key' => 'share_offering_id',
                'label' => 'Offering',
                'type' => 'select',
                'placeholder' => 'All offerings',
                'dependsOn' => 'company_id',
                'options' => $this->offerings()
                    ->map(fn (ShareOffering $offering) => [
                        'value' => $offering->id,
                        'label' => $this->offeringLabel($offering),
                        'parent' => $offering->company_id,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * Offerings in filter scope, oldest first so the per-offering columns come
     * out in the order the issues actually happened.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ShareOffering>
     */
    protected function offerings(array $filters = []): Collection
    {
        return ShareOffering::query()
            ->with('company:id,name,code')
            ->when($filters['company_id'] ?? null, fn ($q, $companyId) => $q->where('company_id', $companyId))
            ->when($filters['share_offering_id'] ?? null, fn ($q, $offeringId) => $q->whereKey($offeringId))
            ->orderBy('id')
            ->get();
    }

    /**
     * "PHL @100" — how the prescribed formats name an offering, since a company
     * can run several at different rates.
     */
    protected function offeringLabel(ShareOffering $offering): string
    {
        $rate = rtrim(rtrim(number_format((float) $offering->share_rate, 2, '.', ''), '0'), '.');

        return ($offering->company?->code ?: $offering->company?->name ?: $offering->title).' @'.$rate;
    }

    /**
     * Verified deposits per application id — the "Total Deposit Amount" the
     * Share Lagat notes reconcile against. Only verified transactions count;
     * a pending slip is not money in the account.
     *
     * @param  array<int, int>  $applicationIds
     * @return array<int, string>
     */
    protected function verifiedDepositsByApplication(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        return DB::table('payment_transactions')
            ->selectRaw('share_application_id, COALESCE(SUM(amount), 0) as total')
            ->whereIn('share_application_id', $applicationIds)
            ->where('verification_status', 'verified')
            ->whereNull('deleted_at')
            ->groupBy('share_application_id')
            ->pluck('total', 'share_application_id')
            ->map(fn ($total) => (string) $total)
            ->all();
    }

    /**
     * Sums a numeric column across rows, for the footer.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function sumColumn(array $rows, string $key): float
    {
        return array_sum(array_map(
            fn (array $row) => (float) preg_replace('/[^0-9.\-]/', '', (string) ($row[$key] ?? 0)),
            $rows,
        ));
    }
}
