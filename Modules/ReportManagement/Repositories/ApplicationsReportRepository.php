<?php

namespace Modules\ReportManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Builder;
use Modules\AllotmentManagement\Models\ShareAllotment;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\PaymentManagement\Models\PaymentTransaction;

class ApplicationsReportRepository extends Repository
{
    public function __construct(ShareApplication $model)
    {
        parent::__construct($model);
    }

    /**
     * Applications report query, filtered by company / offering / status /
     * payment method / date range.
     */
    public function filtered(array $filters): Builder
    {
        return $this->query()
            ->with(['applicant:id,full_name_en,mobile', 'offering.company', 'allotment'])
            ->withSum([
                'paymentTransactions as verified_amount' => fn ($q) => $q->where('verification_status', 'verified'),
            ], 'amount')
            ->when($filters['company_id'] ?? null, fn ($q, $companyId) => $q->whereHas(
                'offering', fn ($o) => $o->where('company_id', $companyId)
            ))
            ->when($filters['share_offering_id'] ?? null, fn ($q, $offeringId) => $q->where('share_offering_id', $offeringId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['payment_method_id'] ?? null, fn ($q, $methodId) => $q->whereHas(
                'paymentTransactions', fn ($p) => $p->where('payment_method_id', $methodId)
            ))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));
    }

    /**
     * Aggregates for the applications currently matched by the report query.
     */
    public function summary(Builder $query): array
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
}
