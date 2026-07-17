<?php

namespace Modules\ApplicationManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareApplicationRepository extends Repository
{
    /**
     * Statuses that count as "in progress" on admin dashboards.
     */
    public const PENDING_STATUSES = [
        ShareApplication::STATUS_SUBMITTED,
        ShareApplication::STATUS_SENT_TO_BANK,
        ShareApplication::STATUS_BANK_ACCEPTED,
        ShareApplication::STATUS_BLOCKED,
        ShareApplication::STATUS_PAYMENT_PENDING,
    ];

    public function __construct(ShareApplication $model)
    {
        parent::__construct($model);
    }

    public function listForAdmin(): Collection
    {
        return $this->query()->with('applicant')->latest()->get();
    }

    public function loadDetail(ShareApplication $application): ShareApplication
    {
        return $application->load([
            'applicant',
            'reviewer:id,name,email',
            'allotment',
            'paymentTransactions' => fn ($query) => $query->with('voucher')->latest(),
            'events' => fn ($query) => $query->with('actor:id,name')->latest(),
        ]);
    }

    /**
     * All applications belonging to a user's applicant profile, newest first.
     */
    public function listForUser(int $userId): Collection
    {
        return $this->forUser($userId)->latest()->get();
    }

    public function latestDraftForUser(int $userId): ?ShareApplication
    {
        return $this->forUser($userId)
            ->where('status', ShareApplication::STATUS_DRAFT)
            ->with(['applicant.nominees', 'applicant.sourcesOfFunds'])
            ->latest()
            ->first();
    }

    public function listByStatus(string|array $status, array $with = []): Collection
    {
        return $this->query()
            ->whereIn('status', (array) $status)
            ->with($with)
            ->latest()
            ->get();
    }

    public function countByStatus(string|array $status): int
    {
        return $this->query()->whereIn('status', (array) $status)->count();
    }

    /**
     * Non-draft, non-rejected applications for an applicant profile.
     */
    public function activeCountForApplicant(int $applicantId): int
    {
        return $this->query()
            ->where('applicant_id', $applicantId)
            ->whereNotIn('status', [ShareApplication::STATUS_DRAFT, ShareApplication::STATUS_REJECTED])
            ->count();
    }

    public function firstOrNewDraft(int $applicantId): ShareApplication
    {
        return ShareApplication::firstOrNew([
            'applicant_id' => $applicantId,
            'status' => ShareApplication::STATUS_DRAFT,
        ]);
    }

    private function forUser(int $userId)
    {
        return $this->query()->whereHas('applicant', fn ($q) => $q->where('user_id', $userId));
    }
}
