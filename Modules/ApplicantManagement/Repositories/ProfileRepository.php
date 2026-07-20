<?php

namespace Modules\ApplicantManagement\Repositories;

use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Modules\ApplicantManagement\Enums\ProfileStatus;
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

    /**
     * A profile already resolved by route binding, loaded with everything the
     * reviewer's detail page renders — the same relation set the KYC form
     * uses, plus the trail and the owning user.
     */
    public function loadForReview(Profile $profile): Profile
    {
        return $profile->load([
            'permanentAddress', 'temporaryAddress', 'documents', 'sourcesOfFunds',
            'nominees', 'experiences', 'user:id,name,email', 'workflowEvents.actor:id,name',
        ]);
    }

    /**
     * The KYC queue for one member of staff: profiles sitting at a stage they
     * hold, minus any the act-once rule bars them from.
     *
     * The act-once filter is expressed in SQL rather than by filtering the
     * collection afterwards, because filtering after the fact would cut pages
     * to inconsistent sizes. It mirrors WorkflowService::assertMayAct: barred
     * only if this user already acted at a *different* stage of the current
     * cycle, since repeating your own stage after a send-back is allowed.
     */
    public function pendingForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $actionable = array_filter(
            ProfileStatus::cases(),
            fn (ProfileStatus $status) => $status->pendingStage() !== null
                && $user->can($status->pendingStage()->permission('profile')),
        );

        if ($actionable === []) {
            return new Paginator([], 0, $perPage, 1, ['path' => request()->url()]);
        }

        return $this->query()
            ->where(function ($outer) use ($actionable, $user) {
                foreach ($actionable as $status) {
                    $outer->orWhere(fn ($q) => $q
                        ->where('profile_status', $status)
                        ->whereDoesntHave('workflowEvents', fn ($event) => $event
                            ->where('actor_id', $user->id)
                            ->whereColumn('workflow_events.cycle', 'profiles.workflow_cycle')
                            ->where('stage', '!=', $status->pendingStage()->value)));
                }
            })
            ->with('workflowEvents.actor:id,name')
            ->orderBy('profile_submitted_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Profiles the chain has finished with. Paged under its own query
     * parameter so moving through it does not reset the pending queue, which
     * sits on the same screen.
     */
    public function recentlyReviewed(int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()
            ->whereIn('profile_status', [ProfileStatus::Approved, ProfileStatus::Returned])
            ->with('workflowEvents.actor:id,name')
            ->latest('updated_at')
            ->paginate($perPage, ['*'], 'decided')
            ->withQueryString();
    }
}
