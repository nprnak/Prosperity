<?php

namespace Modules\CompanyManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Modules\CompanyManagement\Models\Company;

class CompanyRepository extends Repository
{
    public function __construct(Company $model)
    {
        parent::__construct($model);
    }

    /**
     * Companies with their offerings (newest first) and application counts.
     */
    public function listWithOfferings(): Collection
    {
        return $this->query()
            ->with(['offerings' => fn ($q) => $q->withCount('applications')->latest()])
            ->orderBy('name')
            ->get();
    }

    public function hasApplications(Company $company): bool
    {
        return $company->offerings()->withCount('applications')->get()->sum('applications_count') > 0;
    }
}
