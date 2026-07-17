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
     * Offerings currently open for applications, soonest closing first.
     */
    public function openNow(): Collection
    {
        return $this->query()
            ->openNow()
            ->with('company:id,name,code')
            ->orderBy('closes_at')
            ->get();
    }

    public function listForFilters(): Collection
    {
        return $this->query()
            ->with('company:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'fiscal_year', 'company_id']);
    }
}
