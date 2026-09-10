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

    /**
     * Every offering with its subscription progress, most recently opened
     * first — the portfolio-wide view a dashboard needs (open, upcoming and
     * already-closed issues together) rather than openNow()'s narrower "can
     * an applicant file against this today" scope.
     *
     * @param  array{company_id?: int|null, share_offering_id?: int|null}  $filters
     */
    public function subscriptionOverview(int $limit = 8, array $filters = []): Collection
    {
        return $this->query()
            ->when($filters['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['share_offering_id'] ?? null, fn ($query, $offeringId) => $query->where('id', $offeringId))
            ->with('company:id,name,code')
            ->withSum(
                ['applications as shares_subscribed' => fn ($query) => $query->countsTowardSubscription()],
                'shares_applied',
            )
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'upcoming' THEN 1 ELSE 2 END")
            ->orderByDesc('opens_at')
            ->limit($limit)
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
