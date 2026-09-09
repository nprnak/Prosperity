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
            'focalPerson:id,name,focal_person_code',
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
    public function pendingForUser(User $user, int $perPage = 15, ?string $search = null): LengthAwarePaginator
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
            ->when($search, fn ($query) => $this->applySearch($query, $search))
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
    public function recentlyReviewed(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return $this->query()
            ->whereIn('profile_status', [ProfileStatus::Approved, ProfileStatus::Returned])
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with('workflowEvents.actor:id,name')
            ->latest('updated_at')
            ->paginate($perPage, ['*'], 'decided')
            ->withQueryString();
    }

    /**
     * Approved applicants — for an Application Verifier picking who to file a
     * paper application on behalf of, and for the Applicant List page.
     *
     * $enteredBy narrows this to the profiles a given staff member entered
     * themselves: the Applicant List shows every approved applicant to
     * whoever can see the applications list (application.view-any) and only
     * their own paper entries to a verifier/reviewer/approver, who has no
     * business browsing the full roster.
     */
    public function approved(?string $search = null, ?int $enteredBy = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('profile_status', ProfileStatus::Approved)
            ->when($enteredBy, fn ($query) => $query->where('entered_by', $enteredBy))
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with(['user:id,name,email', 'enteredBy:id,name', 'permanentAddress'])
            ->orderBy('full_name_en')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Profiles this KYC Verifier filed themselves from a paper form — a
     * different list from pendingForUser(), which is everything waiting on
     * whichever stage they hold, self-submitted included.
     */
    public function enteredBy(int $verifierId, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->query()
            ->where('entered_by', $verifierId)
            ->when($search, fn ($query) => $this->applySearch($query, $search))
            ->with('workflowEvents.actor:id,name')
            ->latest('id')
            ->paginate($perPage, ['*'], 'entered')
            ->withQueryString();
    }

    /**
     * Matches a name, mobile number or citizenship number, so staff can find
     * a profile with whichever detail they have on hand.
     */
    private function applySearch($query, string $search)
    {
        return $query->where(function ($outer) use ($search) {
            $outer->where('full_name_en', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhere('citizenship_number', 'like', "%{$search}%");
        });
    }
}
