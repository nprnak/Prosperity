<?php

namespace Modules\ApplicationManagement\Repositories;

use App\Enums\WorkflowStage;
use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareApplicationRepository extends Repository
{
    /**
     * Statuses that count as "in progress" on admin dashboards.
     */
    public const PENDING_STATUSES = [
        ApplicationStatus::Submitted,
        ApplicationStatus::SentToBank,
        ApplicationStatus::BankAccepted,
        ApplicationStatus::Blocked,
        ApplicationStatus::PaymentPending,
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
            'paymentTransactions' => fn ($query) => $query
                ->with(['voucher', 'checker:id,name', 'verifier:id,name', 'approver:id,name'])
                ->latest(),
            // Two distinct trails: workflow_events is the review chain's
            // sign-offs, application_events the payment/lifecycle transitions
            // that finance drives outside the chain.
            'events' => fn ($query) => $query->with('actor:id,name')->latest(),
            'workflowEvents' => fn ($query) => $query->with('actor:id,name'),
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
            ->where('status', ApplicationStatus::Draft)
            ->with(['applicant.nominees', 'applicant.sourcesOfFunds'])
            ->latest()
            ->first();
    }

    /**
     * The queue for one stage and one member of staff: applications sitting at
     * that stage, minus any the act-once rule bars them from.
     *
     * The act-once filter runs in SQL, not over the fetched collection, so
     * pages come out a consistent size. It mirrors
     * WorkflowService::assertMayAct: barred only if this user already acted at
     * a *different* stage of the current cycle.
     */
    public function pendingForStage(
        WorkflowStage $stage,
        User $user,
        array $with = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        $statuses = array_filter(
            ApplicationStatus::cases(),
            fn (ApplicationStatus $status) => $status->pendingStage() === $stage,
        );

        return $this->query()
            ->whereIn('status', $statuses)
            ->whereDoesntHave('workflowEvents', fn ($event) => $event
                ->where('actor_id', $user->id)
                ->whereColumn('workflow_events.cycle', 'share_applications.workflow_cycle')
                ->where('stage', '!=', $stage->value))
            ->with($with)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function listByStatus(ApplicationStatus|array $status, array $with = []): Collection
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
     * Non-draft applications for an applicant profile. Returned ones stay
     * visible — the applicant needs to see what to correct.
     */
    public function activeCountForApplicant(int $applicantId): int
    {
        return $this->query()
            ->where('applicant_id', $applicantId)
            ->whereNotIn('status', [ApplicationStatus::Draft])
            ->count();
    }

    /**
     * The applicant's most recent non-draft application — what the wizard shows
     * as "under review" instead of offering a fresh form.
     */
    public function activeForUser(int $userId): ?ShareApplication
    {
        return $this->forUser($userId)
            ->where('status', '!=', ApplicationStatus::Draft)
            ->latest()
            ->first();
    }

    public function firstOrNewDraft(int $applicantId): ShareApplication
    {
        return ShareApplication::firstOrNew([
            'applicant_id' => $applicantId,
            'status' => ApplicationStatus::Draft,
        ]);
    }

    private function forUser(int $userId)
    {
        return $this->query()->whereHas('applicant', fn ($q) => $q->where('user_id', $userId));
    }
}
