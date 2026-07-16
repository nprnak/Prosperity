<?php

namespace Modules\ReportManagement\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Modules\AllotmentManagement\Models\ShareAllotment;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\PaymentManagement\Models\PaymentMethod;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\ReportManagement\Exports\ApplicationsReportExport;

class AdminReportsController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        return Inertia::render('Admin/Reports', [
            'applications' => (clone $query)->latest()->paginate(25)->withQueryString(),
            'summary' => $this->summary($query),
            'filters' => $this->currentFilters($request),
            'options' => [
                'companies' => Company::orderBy('name')->get(['id', 'name']),
                'offerings' => ShareOffering::with('company:id,name')->orderBy('title')->get(['id', 'title', 'fiscal_year', 'company_id']),
                'statuses' => ShareApplication::STATUS_FLOW,
                'paymentMethods' => PaymentMethod::orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->string('format')->toString() ?: 'xlsx';
        $query = $this->filteredQuery($request)->latest();
        $filename = 'applications-report-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.applications-report', [
                'applications' => $query->get(),
                'summary' => $this->summary($this->filteredQuery($request)),
                'filters' => array_filter($this->currentFilters($request)),
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename.'.pdf');
        }

        $writer = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        return Excel::download(new ApplicationsReportExport($query), $filename.'.'.($format === 'csv' ? 'csv' : 'xlsx'), $writer);
    }

    protected function filteredQuery(Request $request): Builder
    {
        return ShareApplication::query()
            ->with(['applicant:id,full_name_en,mobile', 'offering.company', 'allotment'])
            ->withSum([
                'paymentTransactions as verified_amount' => fn ($q) => $q->where('verification_status', 'verified'),
            ], 'amount')
            ->when($request->integer('company_id'), fn ($q, $companyId) => $q->whereHas(
                'offering', fn ($o) => $o->where('company_id', $companyId)
            ))
            ->when($request->integer('share_offering_id'), fn ($q, $offeringId) => $q->where('share_offering_id', $offeringId))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('payment_method_id'), fn ($q, $methodId) => $q->whereHas(
                'paymentTransactions', fn ($p) => $p->where('payment_method_id', $methodId)
            ))
            ->when($request->date('date_from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->date('date_to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to));
    }

    protected function summary(Builder $query): array
    {
        $ids = (clone $query)->select('share_applications.id');

        return [
            'totalApplications' => (clone $query)->count(),
            'totalShares' => (int) (clone $query)->sum('shares_applied'),
            'totalDeclared' => (string) (clone $query)->sum('total_amount_declared'),
            'totalVerifiedPayments' => (string) PaymentTransaction::query()
                ->where('verification_status', 'verified')
                ->whereIn('share_application_id', $ids)
                ->sum('amount'),
            'totalAllotted' => (int) ShareAllotment::query()
                ->whereIn('share_application_id', $ids)
                ->sum('shares_allotted'),
        ];
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
