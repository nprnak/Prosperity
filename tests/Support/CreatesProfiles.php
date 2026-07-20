<?php

namespace Tests\Support;

use App\Models\User;
use Modules\ApplicantManagement\Enums\EducationLevel;
use Modules\ApplicantManagement\Enums\Gender;
use Modules\ApplicantManagement\Enums\MaritalStatus;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;

/**
 * Shared KYC profile fixtures.
 *
 * Feature tests previously each built their own applicant row inline, which is
 * how nine of them silently rotted against the profiles schema. Build them here
 * so a schema change breaks one place.
 */
trait CreatesProfiles
{
    /**
     * The bare identity a ShareApplication can hang off. Not complete enough
     * to pass KYC submission — use completeProfile() for that.
     */
    protected function minimalProfile(User $user, array $overrides = []): Profile
    {
        $profile = Profile::create(array_merge([
            'user_id' => $user->id,
            'full_name_en' => 'Applicant '.$user->id,
            'full_name_np' => 'परीक्षण',
            'date_of_birth' => '1990-01-01',
            'gender' => Gender::Male,
            'marital_status' => MaritalStatus::Single,
            'father_name' => 'F',
            'grandfather_name' => 'GF',
            'mobile' => '98000000'.$user->id,
            'email' => $user->email,
        ], $overrides));

        $profile->permanentAddress()->create([
            'type' => 'permanent',
            'province' => 'Bagmati',
            'district' => 'KTM',
            'local_level' => 'KMC',
            'ward_no' => '1',
        ]);

        return $profile->refresh();
    }

    /**
     * Satisfies every check in Profile::completionChecks(), so /profile/submit
     * accepts it.
     */
    protected function completeProfile(User $user, array $overrides = []): Profile
    {
        $profile = $this->minimalProfile($user, array_merge([
            'full_name_en' => 'Test Applicant '.$user->id,
            'mother_name' => 'Mother',
            'education' => EducationLevel::Bachelors,
            'citizenship_number' => 'CTZ-'.$user->id,
            // national_id_number is validated as exactly 10 digits.
            'national_id_number' => sprintf('90%08d', $user->id),
            // boid is char(16) and unique.
            'boid' => sprintf('13010000%08d', $user->id),
            'bank_name' => 'Test Bank',
            'bank_branch' => 'Kathmandu',
            'bank_account_number' => '0123456789'.$user->id,
            'account_holder_name' => 'Test Applicant '.$user->id,
            'asba_consent' => true,
            'declaration_accepted' => true,
        ], $overrides));

        foreach (Profile::REQUIRED_DOCUMENT_TYPES as $type) {
            $profile->documents()->create([
                'document_type' => $type,
                'file_path' => "docs/{$type}.jpg",
            ]);
        }

        return $profile->refresh();
    }

    /**
     * A profile already through KYC review, for tests that need to reach the
     * application wizard.
     */
    protected function approvedProfile(User $user, array $overrides = []): Profile
    {
        $profile = $this->completeProfile($user, $overrides);

        // profile_status is intentionally not mass-assignable.
        $profile->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        return $profile;
    }
}
