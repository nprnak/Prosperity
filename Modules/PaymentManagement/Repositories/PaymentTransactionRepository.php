<?php

namespace Modules\PaymentManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Modules\PaymentManagement\Models\PaymentTransaction;

class PaymentTransactionRepository extends Repository
{
    public function __construct(PaymentTransaction $model)
    {
        parent::__construct($model);
    }

    public function listForAdmin(): Collection
    {
        return $this->query()->with('shareApplication.applicant')->latest()->get();
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
     */
    public function verifiedDailySeries(): BaseCollection
    {
        return $this->query()
            ->where('verification_status', 'verified')
            ->selectRaw('DATE(payment_date) as date, SUM(amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
