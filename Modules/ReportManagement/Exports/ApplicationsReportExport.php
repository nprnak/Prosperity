<?php

namespace Modules\ReportManagement\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Modules\ApplicationManagement\Models\ShareApplication;

class ApplicationsReportExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Application No',
            'Applicant',
            'Mobile',
            'Company',
            'Offering',
            'Fiscal Year',
            'Shares Applied',
            'Rate',
            'Total Declared',
            'Verified Payments',
            'Shares Allotted',
            'Status',
            'Applied Date',
        ];
    }

    /**
     * @param  ShareApplication  $application
     */
    public function map($application): array
    {
        return [
            $application->application_number,
            $application->applicant?->full_name_en,
            $application->applicant?->mobile,
            $application->offering?->company?->name,
            $application->offering?->title,
            $application->offering?->fiscal_year,
            $application->shares_applied,
            $application->amount_per_share,
            $application->total_amount_declared,
            $application->verified_amount ?? '0.00',
            $application->allotment?->shares_allotted ?? 0,
            $application->status->value,
            $application->created_at?->toDateString(),
        ];
    }
}
