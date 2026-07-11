<?php

namespace App\Exports;

use App\Models\ShareAllotment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ShareholderRegisterExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return ShareAllotment::query()
            ->with('applicant')
            ->get()
            ->map(function (ShareAllotment $allotment) {
                return [
                    'applicant_name' => $allotment->applicant?->full_name_english,
                    'shares_allotted' => $allotment->shares_allotted,
                    'allotment_date' => optional($allotment->allotment_date)->toDateString(),
                    'certificate_number' => $allotment->certificate_number,
                ];
            });
    }

    public function headings(): array
    {
        return ['Applicant', 'Shares Allotted', 'Allotment Date', 'Certificate Number'];
    }
}
