<?php

namespace Modules\UserManagement\Services;

use App\Models\User;
use App\Services\NumberGeneratorService;
use Illuminate\Validation\ValidationException;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\UserManagement\Repositories\FocalPersonRepository;

/**
 * Designating focal persons, and attributing applicants to them.
 *
 * Every write goes through here rather than through mass assignment, because
 * focal-person attribution feeds the focal-person report: an applicant must
 * never be able to set it on themselves via a profile update, and every change
 * has to leave a trail.
 */
class FocalPersonService
{
    public function __construct(
        private FocalPersonRepository $focalPersons,
        private NumberGeneratorService $numbers,
    ) {}

    /**
     * Tag a user as a focal person, issuing their code the first time.
     *
     * @throws ValidationException when the user's KYC is not approved
     */
    public function grant(User $user): User
    {
        if (! $this->hasApprovedKyc($user)) {
            throw ValidationException::withMessages([
                'is_focal_person' => "{$user->name} cannot be a focal person until their KYC profile is approved.",
            ]);
        }

        $wasFocalPerson = (bool) $user->is_focal_person;

        // Codes are issued once and kept through un-tagging, so a person who is
        // re-tagged keeps the code applicants already know them by.
        $code = $user->focal_person_code ?: $this->numbers->generateFocalPersonCode();

        $user->forceFill([
            'is_focal_person' => true,
            'focal_person_code' => $code,
        ])->save();

        if (! $wasFocalPerson) {
            activity('focal_person')
                ->performedOn($user)
                ->withProperties(['code' => $code])
                ->log("Designated {$user->name} as a focal person");
        }

        return $user;
    }

    /**
     * Un-tag a user. Existing attributions are left alone — they are history,
     * and the report still has to be able to name who brought those
     * applications in.
     */
    public function revoke(User $user): User
    {
        if (! $user->is_focal_person) {
            return $user;
        }

        $user->forceFill(['is_focal_person' => false])->save();

        activity('focal_person')
            ->performedOn($user)
            ->withProperties(['code' => $user->focal_person_code])
            ->log("Removed focal person designation from {$user->name}");

        return $user;
    }

    /**
     * Set (or clear, with null) an applicant's default focal person.
     *
     * Writes nothing but the one column: re-attributing an applicant must not
     * disturb profile_status or the KYC workflow cycle, or an approved profile
     * would be dragged back through review.
     *
     * @throws ValidationException when the target is not an eligible focal person
     */
    public function assignToProfile(Profile $profile, ?int $focalPersonId): Profile
    {
        $focalPerson = $this->resolveEligibleId($focalPersonId, 'focal_person_id');

        if ($focalPerson && $focalPerson->id === $profile->user_id) {
            throw ValidationException::withMessages([
                'focal_person_id' => 'An applicant cannot be their own focal person.',
            ]);
        }

        if ($profile->focal_person_id === $focalPerson?->id) {
            return $profile;
        }

        $previous = $profile->focalPerson;

        $profile->forceFill(['focal_person_id' => $focalPerson?->id])->save();

        activity('focal_person')
            ->performedOn($profile)
            ->withProperties([
                'from' => $previous?->focal_person_code,
                'to' => $focalPerson?->focal_person_code,
            ])
            ->log($focalPerson
                ? "Focal person for {$profile->full_name_en} set to {$focalPerson->name}"
                : "Focal person cleared for {$profile->full_name_en}");

        return $profile;
    }

    /**
     * Resolve the code an applicant typed into their application.
     *
     * @param  string  $errorKey  validation key to hang the message on, so the
     *                            wizard and the admin form can each surface it
     *                            beside their own field
     *
     * @throws ValidationException when the code matches nobody eligible, or
     *                             names the applicant themselves
     */
    public function resolveCodeForApplicant(?string $code, Profile $applicant, string $errorKey): ?User
    {
        if (blank($code)) {
            return null;
        }

        $focalPerson = $this->focalPersons->findEligibleByCode($code);

        if (! $focalPerson) {
            throw ValidationException::withMessages([
                $errorKey => 'No focal person found with that code. Check the code with the person who gave it to you, or leave it blank.',
            ]);
        }

        // Self-referral would let an applicant credit their own investment to
        // themselves, which is exactly what the report is meant to attribute.
        if ($focalPerson->id === $applicant->user_id) {
            throw ValidationException::withMessages([
                $errorKey => 'You cannot name yourself as your own focal person.',
            ]);
        }

        return $focalPerson;
    }

    /**
     * @throws ValidationException
     */
    protected function resolveEligibleId(?int $focalPersonId, string $errorKey): ?User
    {
        if ($focalPersonId === null) {
            return null;
        }

        $focalPerson = $this->focalPersons->findEligibleById($focalPersonId);

        if (! $focalPerson) {
            throw ValidationException::withMessages([
                $errorKey => 'That user is not currently an eligible focal person — they must be designated and hold an approved KYC profile.',
            ]);
        }

        return $focalPerson;
    }

    protected function hasApprovedKyc(User $user): bool
    {
        return Profile::query()
            ->where('user_id', $user->id)
            ->where('profile_status', ProfileStatus::Approved->value)
            ->exists();
    }
}
