<?php

namespace Modules\ReportManagement\Reports;

use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;

/**
 * Applicationwise Status Report — one row per application, filterable by the
 * status it sits at and by the issue it belongs to.
 *
 * Deliberately per application rather than per shareholder: the point of this
 * format is to see where each individual application has got to, so a holder
 * who applied twice appears twice.
 */
class ApplicationStatusReport extends BaseReport
{
    public function key(): string
    {
        return 'application-status';
    }

    public function title(): string
    {
        return 'Applicationwise Status Report';
    }

    public function description(): string
    {
        return 'Every application with the shares and amount applied for and the stage it has '
            .'reached, filterable by status and by offering.';
    }

    public function filters(): array
    {
        return array_merge($this->issueFilters(), [
            [
                'key' => 'status',
                'label' => 'Application Status',
                'type' => 'select',
                'placeholder' => 'All statuses',
                'options' => array_map(fn (ApplicationStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->labelEn(),
                ], ApplicationStatus::cases()),
            ],
        ]);
    }

    public function columns(array $filters = []): array
    {
        return [
            ['key' => 'sn', 'label' => 'S.N.', 'align' => 'right'],
            ['key' => 'username', 'label' => 'Username'],
            ['key' => 'offering', 'label' => 'Offerings'],
            ['key' => 'shares_applied', 'label' => 'No. of Shares applied', 'align' => 'right'],
            ['key' => 'applied_amount', 'label' => 'Applied Amount', 'align' => 'right'],
            ['key' => 'status', 'label' => 'Application Status'],
            ['key' => 'application_number', 'label' => 'Application No.', 'default' => false],
            ['key' => 'deposited', 'label' => 'Verified Deposit', 'align' => 'right', 'default' => false],
            ['key' => 'focal_person', 'label' => 'Focal Person', 'default' => false],
            ['key' => 'submitted_at', 'label' => 'Applied On', 'default' => false],
        ];
    }

    public function rows(array $filters = []): array
    {
        $applications = ShareApplication::query()
            ->with([
                'applicant:id,user_id,full_name_en',
                'applicant.user:id,name',
                'offering:id,title,fiscal_year,share_rate,company_id',
                'offering.company:id,name,code',
                'focalPerson:id,name,focal_person_code',
            ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['share_offering_id'] ?? null, fn ($q, $offeringId) => $q->where('share_offering_id', $offeringId))
            ->when($filters['company_id'] ?? null, fn ($q, $companyId) => $q->whereHas(
                'offering', fn ($offering) => $offering->where('company_id', $companyId)
            ))
            ->orderByDesc('id')
            ->get();

        $deposits = $this->verifiedDepositsByApplication($applications->pluck('id')->all());

        $serial = 0;

        return $applications->map(fn (ShareApplication $application) => [
            'sn' => ++$serial,
            'username' => $application->applicant?->user?->name ?: ($application->applicant?->full_name_en ?: '—'),
            'offering' => $this->offeringName($application),
            'shares_applied' => number_format((int) $application->shares_applied),
            'applied_amount' => number_format((float) $application->total_amount_declared, 2, '.', ','),
            'status' => $application->status->labelEn(),
            'application_number' => $application->application_number,
            'deposited' => number_format((float) ($deposits[$application->id] ?? 0), 2, '.', ','),
            'focal_person' => $application->focalPerson
                ? $application->focalPerson->name.' ('.$application->focalPerson->focal_person_code.')'
                : '—',
            'submitted_at' => $application->submitted_at?->format('Y-m-d') ?: '—',
        ])->all();
    }

    public function totals(array $rows): array
    {
        return [
            'shares_applied' => number_format($this->sumColumn($rows, 'shares_applied')),
            'applied_amount' => number_format($this->sumColumn($rows, 'applied_amount'), 2, '.', ','),
            'deposited' => number_format($this->sumColumn($rows, 'deposited'), 2, '.', ','),
        ];
    }

    private function offeringName(ShareApplication $application): string
    {
        $offering = $application->offering;

        if (! $offering) {
            return '—';
        }

        return trim(($offering->company?->name ?: '').' · '.$offering->title.' ('.$offering->fiscal_year.')', ' ·');
    }
}
