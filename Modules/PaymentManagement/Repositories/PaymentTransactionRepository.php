<?php

namespace Modules\PaymentManagement\Repositories;

use App\Repositories\Repository;
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

    public function verifiedSum(): string
    {
        return (string) $this->query()->where('verification_status', 'verified')->sum('amount');
    }

    /**
     * Verified amount per day, for the capital-raised chart.
     *
     * Grouped by the deposit's own date, not the receipt's: a receipt may
     * acknowledge deposits made weeks apart, and dating all of them to the
     * receipt would pile the money onto whichever day it was issued.
     */
    public function verifiedDailySeries(): BaseCollection
    {
        return DB::table('payment_deposits')
            ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_deposits.payment_transaction_id')
            ->whereNull('payment_transactions.deleted_at')
            ->where('payment_transactions.verification_status', 'verified')
            ->where('payment_deposits.verification_status', 'verified')
            ->selectRaw('DATE(payment_deposits.payment_date) as date, SUM(payment_deposits.amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
