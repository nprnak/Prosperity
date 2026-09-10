<?php

namespace Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\AllotmentManagement\Repositories\ShareAllotmentRepository;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\CompanyManagement\Repositories\ShareOfferingRepository;
use Modules\PaymentManagement\Repositories\PaymentTransactionRepository;
use Modules\ReportManagement\Reports\FocalPersonReport;

class AdminDashboardController extends Controller
{
    /** Statuses that mean the applicant actually holds shares. */
    private const HOLDING_STATUSES = [
        ApplicationStatus::Approved,
        ApplicationStatus::Allotted,
        ApplicationStatus::PartiallyAllotted,
        ApplicationStatus::DematCredited,
    ];

    public function __construct(
        private ShareApplicationRepository $applications,
        private PaymentTransactionRepository $payments,
        private ShareAllotmentRepository $allotments,
        private ShareOfferingRepository $offerings,
        private FocalPersonReport $focalPersons,
    ) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'capitalRaised' => $this->payments->verifiedSum($filters),
                'totalShareholders' => $this->applications->distinctShareholderCount(self::HOLDING_STATUSES, $filters),
                'totalSharesAllotted' => $this->allotments->totalShares($filters),
                'activeOfferings' => ShareOffering::query()
                    ->openNow()
                    ->when($filters['company_id'] ?? null, fn ($q, $companyId) => $q->where('company_id', $companyId))
                    ->when($filters['share_offering_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
                    ->count(),
                'pendingApplications' => $this->applications->countByStatus(ShareApplicationRepository::PENDING_STATUSES, $filters),
                'totalApplications' => $this->applications->countByStatus(
                    array_map(fn (ApplicationStatus $status) => $status->value, array_values(array_filter(
                        ApplicationStatus::cases(),
                        fn (ApplicationStatus $status) => $status !== ApplicationStatus::Draft,
                    ))),
                    $filters,
                ),
            ],
            'capitalSeries' => $this->payments->verifiedDailySeries($filters),
            // Every issue's subscription progress at a glance — open,
            // upcoming and recently closed together — so staff can see which
            // offering needs attention without opening the companies module.
            'offerings' => $this->offerings->subscriptionOverview(filters: $filters)->map(fn (ShareOffering $offering) => [
                'id' => $offering->id,
                'title' => $offering->title,
                'company' => $offering->company?->name,
                'status' => $offering->status,
                'totalShares' => (int) $offering->total_shares,
                'sharesSubscribed' => $offering->shares_subscribed,
                'sharesRemaining' => $offering->shares_remaining,
                'subscriptionPercent' => $offering->total_shares > 0
                    ? round(min(100, $offering->shares_subscribed / $offering->total_shares * 100), 1)
                    : 0,
                'closesAt' => $offering->closes_at?->format('Y-m-d'),
            ])->values(),
            // Where applications are sitting right now, so a bottleneck (say,
            // a pile-up at "Awaiting Verification") is visible immediately.
            'statusBreakdown' => $this->applications->statusBreakdown($filters),
            'recentApplications' => $this->applications->recent(filters: $filters)->map(fn (ShareApplication $application) => [
                'id' => $application->id,
                'applicationNumber' => $application->application_number,
                'applicant' => $application->applicant?->full_name_en,
                'offering' => $application->offering?->title,
                'status' => $application->status->value,
                'statusLabel' => $application->status_label,
                'shares' => (int) $application->shares_applied,
                'amount' => $application->total_amount_declared,
                'submittedAt' => $application->submitted_at?->format('Y-m-d'),
            ])->values(),
            'totalCompanies' => Company::query()->count(),
            // Note 4 of the Focal Personwise format: the same figures belong on
            // the dashboard, not only in the report.
            'focalPersons' => $request->user()->can('report.view')
                ? $this->focalPersons->dashboardSummary()
                : [],
            'filters' => $filters,
            'filterOptions' => [
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                // Offerings carry their company id so the picker can narrow
                // itself the same way the report filters already do.
                'offerings' => $this->offerings->listForFilters()->map(fn (ShareOffering $offering) => [
                    'id' => $offering->id,
                    'title' => $offering->title,
                    'company_id' => $offering->company_id,
                ]),
            ],
        ]);
    }

    /**
     * Only these four keys ever reach the dashboard's queries, so an
     * unrelated query parameter can't do anything unexpected.
     *
     * @return array{company_id: int|null, share_offering_id: int|null, date_from: string|null, date_to: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'company_id' => $request->integer('company_id') ?: null,
            'share_offering_id' => $request->integer('share_offering_id') ?: null,
            'date_from' => $request->filled('date_from') ? $request->string('date_from')->toString() : null,
            'date_to' => $request->filled('date_to') ? $request->string('date_to')->toString() : null,
        ];
    }
}
