<?php

namespace Modules\ReportManagement\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Repositories\CompanyRepository;
use Modules\CompanyManagement\Repositories\ShareOfferingRepository;
use Modules\PaymentManagement\Repositories\PaymentMethodRepository;
use Modules\ReportManagement\Exports\ApplicationsReportExport;
use Modules\ReportManagement\Repositories\ApplicationsReportRepository;

class AdminReportsController extends Controller
{
    public function __construct(private ApplicationsReportRepository $reports)
    {
    }

    public function index(
        Request $request,
        CompanyRepository $companies,
        ShareOfferingRepository $offerings,
        PaymentMethodRepository $paymentMethods,
    ) {
        $filters = $this->currentFilters($request);
        $query = $this->reports->filtered($filters);

        return Inertia::render('Admin/Reports', [
            'applications' => (clone $query)->latest()->paginate(25)->withQueryString(),
            'summary' => $this->reports->summary($query),
            'filters' => $filters,
            'options' => [
                'companies' => $companies->query()->orderBy('name')->get(['id', 'name']),
                'offerings' => $offerings->listForFilters(),
                'statuses' => ShareApplication::STATUS_FLOW,
                'paymentMethods' => $paymentMethods->query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->currentFilters($request);
        $format = $request->string('format')->toString() ?: 'xlsx';
        $query = $this->reports->filtered($filters)->latest();
        $filename = 'applications-report-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.applications-report', [
                'applications' => $query->get(),
                'summary' => $this->reports->summary($this->reports->filtered($filters)),
                'filters' => array_filter($filters),
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename.'.pdf');
        }

        $writer = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        return Excel::download(new ApplicationsReportExport($query), $filename.'.'.($format === 'csv' ? 'csv' : 'xlsx'), $writer);
    }

    protected function currentFilters(Request $request): array
    {
        return [
            'company_id' => $request->integer('company_id') ?: null,
            'share_offering_id' => $request->integer('share_offering_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'payment_method_id' => $request->integer('payment_method_id') ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
        ];
    }
}
