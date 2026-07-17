<?php

namespace Modules\ApplicantManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Modules\ApplicantManagement\Models\Profile;

class ProfileRepository extends Repository
{
    public function __construct(Profile $model)
    {
        parent::__construct($model);
    }

    public function findByUserId(int $userId): ?Profile
    {
        return $this->query()->where('user_id', $userId)->first();
    }

    /**
     * Profile with every relation the KYC form needs.
     */
    public function findByUserIdWithKyc(int $userId): ?Profile
    {
        return $this->query()
            ->with(['permanentAddress', 'temporaryAddress', 'documents', 'sourcesOfFunds', 'nominees', 'experiences'])
            ->where('user_id', $userId)
            ->first();
    }

    public function firstOrNewForUser(int $userId): Profile
    {
        return $this->query()->firstOrNew(['user_id' => $userId]);
    }

    public function pendingReviewQueue(): Collection
    {
        return $this->query()
            ->where('profile_status', Profile::PROFILE_SUBMITTED)
            ->orderBy('profile_submitted_at')
            ->get();
    }

    public function recentlyReviewed(int $limit = 20): Collection
    {
        return $this->query()
            ->whereIn('profile_status', [Profile::PROFILE_APPROVED, Profile::PROFILE_REJECTED])
            ->with('profileReviewer:id,name')
            ->latest('profile_reviewed_at')
            ->limit($limit)
            ->get();
    }
}
