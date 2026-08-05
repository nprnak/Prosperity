<?php

namespace Modules\AllotmentManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AllotmentManagement\Models\ShareAllotment;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareAllotmentRepository extends Repository
{
    public function __construct(ShareAllotment $model)
    {
        parent::__construct($model);
    }

    /**
     * Shareholder register, searchable by applicant name.
     */
    public function register(string $search = '', string $sort = 'desc'): Collection
    {
        return $this->query()
            ->with(['applicant', 'shareApplication'])
            ->when($search, fn ($q) => $q->whereHas('applicant', fn ($aq) => $aq->where('full_name_en', 'like', '%'.$search.'%')))
            ->orderBy('allotment_date', $sort === 'asc' ? 'asc' : 'desc')
            ->get();
    }

    public function listForAdmin(): Collection
    {
        return $this->query()->with('shareApplication.applicant')->latest()->get();
    }

    public function upsertForApplication(ShareApplication $application, array $attributes): ShareAllotment
    {
        return ShareAllotment::updateOrCreate(
            ['share_application_id' => $application->id],
            [...$attributes, 'applicant_id' => $application->applicant_id],
        );
    }

    public function totalShares(): int
    {
        return (int) $this->query()->sum('shares_allotted');
    }

    /**
     * Shares already allotted out of one offering.
     *
     * $excludingApplicationId leaves an application's own allotment out of the
     * total, so revising it is measured against what everyone else holds
     * rather than against itself — otherwise a correction upwards is
     * impossible once the offering is fully allotted.
     */
    public function allottedSharesForOffering(int $offeringId, ?int $excludingApplicationId = null): int
    {
        return (int) $this->query()
            ->whereHas('shareApplication', fn ($query) => $query->where('share_offering_id', $offeringId))
            ->when($excludingApplicationId, fn ($query) => $query->where('share_application_id', '!=', $excludingApplicationId))
            ->sum('shares_allotted');
    }

    public function distinctApplicationCount(): int
    {
        return $this->query()->distinct('share_application_id')->count();
    }

    public function allotmentCount(): int
    {
        return $this->query()->count();
    }

    /**
     * Total raised across allotments — price per share lives on the
     * application, not the allotment.
     */
    public function totalRaised(): string
    {
        return (string) $this->query()
            ->join('share_applications', 'share_applications.id', '=', 'share_allotments.share_application_id')
            ->sum(DB::raw('share_allotments.shares_allotted * share_applications.amount_per_share'));
    }
}
