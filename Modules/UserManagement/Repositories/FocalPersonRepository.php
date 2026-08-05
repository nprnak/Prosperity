<?php

namespace Modules\UserManagement\Repositories;

use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\ApplicantManagement\Enums\ProfileStatus;

/**
 * Who may act as a focal person.
 *
 * Two independent conditions: super_admin's tag (users.is_focal_person) and an
 * approved KYC profile. The KYC half is a *live* precondition rather than a
 * snapshot — if someone's profile is later returned they drop out of the
 * pickers, but assignments already made stay put, because clearing them would
 * silently rewrite past focal-person figures.
 *
 * Profile state is reached by subquery rather than an Eloquent relation, so
 * App\Models\User stays free of module imports.
 */
class FocalPersonRepository extends Repository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Users who satisfy both conditions — the only valid answers to "who can
     * be picked as a focal person".
     */
    public function eligible(): Collection
    {
        return $this->eligibleQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'focal_person_code']);
    }

    public function findEligibleById(int $id): ?User
    {
        return $this->eligibleQuery()->find($id);
    }

    /**
     * Code lookup for the applicant-facing field. Matched case-insensitively
     * and whitespace-trimmed, since the code reaches the applicant by being
     * read off a screen or repeated over the phone.
     */
    public function findEligibleByCode(string $code): ?User
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        return $this->eligibleQuery()
            ->whereRaw('LOWER(focal_person_code) = ?', [mb_strtolower($code)])
            ->first();
    }

    public function isEligible(User $user): bool
    {
        return $this->eligibleQuery()->whereKey($user->getKey())->exists();
    }

    /**
     * Everyone super_admin could tag — anyone with an approved profile,
     * whatever their role — plus anyone already tagged, so a person whose KYC
     * was later returned still shows up and can be un-tagged.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function candidates(?string $search = null, bool $taggedOnly = false): \Illuminate\Support\Collection
    {
        return $this->query()
            ->select(['id', 'name', 'email', 'is_focal_person', 'focal_person_code'])
            ->addSelect([
                // What un-tagging would strand, and what the report counts.
                'applicants_count' => DB::table('profiles')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('profiles.focal_person_id', 'users.id'),
                'applications_count' => DB::table('share_applications')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('share_applications.focal_person_id', 'users.id'),
                // Drives the "KYC no longer approved" warning on a tagged row.
                'approved_profiles_count' => DB::table('profiles')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('profiles.user_id', 'users.id')
                    ->where('profiles.profile_status', ProfileStatus::Approved->value),
            ])
            ->where(fn ($q) => $this->whereKycApproved($q)->orWhere('is_focal_person', true))
            ->when($taggedOnly, fn ($q) => $q->where('is_focal_person', true))
            ->when(filled($search), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('focal_person_code', 'like', "%{$search}%")))
            ->orderByDesc('is_focal_person')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_focal_person' => (bool) $user->is_focal_person,
                'focal_person_code' => $user->focal_person_code,
                'applicants_count' => (int) $user->applicants_count,
                'applications_count' => (int) $user->applications_count,
                'kyc_approved' => $user->approved_profiles_count > 0,
            ])
            ->values();
    }

    /** Tagged AND KYC-approved. */
    protected function eligibleQuery(): Builder
    {
        return $this->whereKycApproved($this->query()->where('is_focal_person', true));
    }

    /**
     * Constrains a users query to those holding an approved KYC profile.
     */
    protected function whereKycApproved(Builder $query): Builder
    {
        return $query->whereExists(fn ($sub) => $sub
            ->select(DB::raw(1))
            ->from('profiles')
            ->whereColumn('profiles.user_id', 'users.id')
            ->where('profiles.profile_status', ProfileStatus::Approved->value));
    }
}
