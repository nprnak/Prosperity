<?php

namespace Modules\CompanyManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Modules\CompanyManagement\Models\ShareOffering;

class ShareOfferingRepository extends Repository
{
    public function __construct(ShareOffering $model)
    {
        parent::__construct($model);
    }

    /**
     * Offerings currently open for applications, soonest closing first,
     * with subscribed/remaining share counts for display.
     */
    public function openNow(): Collection
    {
        return $this->query()
            ->openNow()
            ->with('company:id,name,code')
            ->withSum(
                ['applications as shares_subscribed' => fn ($query) => $query->countsTowardSubscription()],
                'shares_applied',
            )
            ->orderBy('closes_at')
            ->get()
            ->each(function (ShareOffering $offering) {
                $offering->shares_subscribed = (int) $offering->shares_subscribed;
                $offering->shares_remaining = max(0, (int) $offering->total_shares - $offering->shares_subscribed);
            });
    }

    public function listForFilters(): Collection
    {
        return $this->query()
            ->with('company:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'fiscal_year', 'company_id']);
    }
}
