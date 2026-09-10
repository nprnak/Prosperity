<?php

namespace Modules\PaymentManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Modules\PaymentManagement\Models\PaymentTransaction;

class PaymentTransactionRepository extends Repository
{
    public function __construct(PaymentTransaction $model)
    {
        parent::__construct($model);
    }

    /**
     * The payment method is eager-loaded because the list names it — without
     * it the column simply rendered blank on every row.
     */
    public function listForAdmin(?string $verificationStatus = null): Collection
    {
        return $this->query()
            ->with(['shareApplication.applicant', 'paymentMethod:id,name'])
            ->when($verificationStatus, fn ($query) => $query->where('verification_status', $verificationStatus))
            ->latest()
            ->get();
    }

    public function pendingCount(): int
    {
        return $this->query()->where('verification_status', 'pending')->count();
    }

    public function verifiedCount(): int
    {
        return $this->query()->where('verification_status', 'verified')->count();
    }

    /**
     * @param  array{company_id?: int|null, share_offering_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function verifiedSum(array $filters = []): string
    {
        return (string) $this->scopeToFilters($this->query()->where('verification_status', 'verified'), $filters)
            ->sum('amount');
    }

    /**
     * Verified amount per day, for the capital-raised chart.
     *
     * Grouped by the deposit's own date, not the receipt's: a receipt may
     * acknowledge deposits made weeks apart, and dating all of them to the
     * receipt would pile the money onto whichever day it was issued.
     *
     * Only the transaction's own verification matters here — a payment is
     * verified as a whole (automatically, once its application is approved),
     * not deposit by deposit, so a per-deposit status is no longer part of
     * this test.
     *
     * @param  array{company_id?: int|null, share_offering_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function verifiedDailySeries(array $filters = []): BaseCollection
    {
        return DB::table('payment_deposits')
            ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_deposits.payment_transaction_id')
            ->join('share_applications', 'share_applications.id', '=', 'payment_transactions.share_application_id')
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query
                ->join('share_offerings', 'share_offerings.id', '=', 'share_applications.share_offering_id')
                ->where('share_offerings.company_id', $companyId))
            ->when($filters['share_offering_id'] ?? null, fn ($query, $offeringId) => $query
                ->where('share_applications.share_offering_id', $offeringId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('payment_deposits.payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('payment_deposits.payment_date', '<=', $date))
            ->whereNull('payment_transactions.deleted_at')
            ->where('payment_transactions.verification_status', 'verified')
            ->selectRaw('DATE(payment_deposits.payment_date) as date, SUM(payment_deposits.amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Company/offering/date-range narrowing shared by the dashboard's
     * queries. All optional — an empty $filters array matches everything.
     *
     * @param  array{company_id?: int|null, share_offering_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    private function scopeToFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                ($filters['company_id'] ?? null) || ($filters['share_offering_id'] ?? null),
                fn ($q) => $q->whereHas('shareApplication', fn ($application) => $application
                    ->when($filters['company_id'] ?? null, fn ($a, $companyId) => $a->whereHas(
                        'offering', fn ($offering) => $offering->where('company_id', $companyId)
                    ))
                    ->when($filters['share_offering_id'] ?? null, fn ($a, $offeringId) => $a->where('share_offering_id', $offeringId)))
            )
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
    }
}
