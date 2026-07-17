<?php

namespace Modules\ApplicantManagement\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicantManagement\Requests\ApplicantProfileUpdateRequest;

/**
 * Persists the full KYC profile form: scalar fields plus addresses,
 * documents, sources of funds, nominee, and work experiences.
 */
class ApplicantProfileService
{
    /**
     * Scalar profile columns accepted straight from the KYC form.
     */
    private const SCALAR_FIELDS = [
        'title', 'full_name_np', 'gender', 'date_of_birth', 'nationality', 'marital_status',
        'father_name', 'mother_name', 'grandfather_name', 'spouse_name', 'occupation', 'education',
        'mobile', 'citizenship_number', 'citizenship_issued_district', 'citizenship_issued_date',
        'national_id_number', 'pan_number', 'boid', 'bank_name', 'bank_code',
        'bank_branch', 'bank_account_number', 'account_holder_name', 'asba_consent',
    ];

    /**
     * Upload input name => stored document_type (+ which profile column numbers it).
     */
    private const DOCUMENT_INPUTS = [
        'photo' => ['type' => 'photo', 'number_field' => null],
        'citizenship_front' => ['type' => 'citizenship_front', 'number_field' => 'citizenship_number'],
        'citizenship_back' => ['type' => 'citizenship_back', 'number_field' => 'citizenship_number'],
        'national_id_doc' => ['type' => 'national_id', 'number_field' => 'national_id_number'],
        'pan_doc' => ['type' => 'pan', 'number_field' => 'pan_number'],
        'signature' => ['type' => 'signature', 'number_field' => null],
    ];

    public function __construct(private ProfileRepository $profiles)
    {
    }

    public function update(User $user, ApplicantProfileUpdateRequest $request): Profile
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($user, $request, $validated) {
            $profile = $this->profiles->firstOrNewForUser($user->id);

            $profile->fill(Arr::only($validated, self::SCALAR_FIELDS));

            // Keep share applicant identity tied to login account details.
            $profile->full_name_en = $user->name;
            $profile->email = $user->email;
            $profile->user_id = $user->id;

            $profile->declaration_accepted = true;
            $profile->declaration_accepted_at ??= now();

            if (blank($profile->profile_status)) {
                $profile->profile_status = Profile::PROFILE_INCOMPLETE;
            }

            $profile->save();

            $this->syncAddresses($profile, $validated, $request->boolean('temporary_same_as_permanent'));
            $this->syncDocuments($profile, $request);
            $this->syncSourcesOfFunds($profile, $validated);
            $this->syncNominee($profile, $validated['nominee'] ?? []);
            $this->syncExperiences($profile, $validated['experiences'] ?? []);

            return $profile;
        });
    }

    private function syncAddresses(Profile $profile, array $validated, bool $sameAsPermanent): void
    {
        $permanent = Arr::only($validated['permanent'] ?? [], ['province', 'district', 'local_level', 'ward_no', 'tole']);

        $profile->addresses()->updateOrCreate(['type' => 'permanent'], $permanent);

        $temporary = $sameAsPermanent
            ? $permanent
            : Arr::only($validated['temporary'] ?? [], ['province', 'district', 'local_level', 'ward_no', 'tole']);

        if (array_filter($temporary) !== []) {
            $profile->addresses()->updateOrCreate(['type' => 'temporary'], $temporary);
        } else {
            $profile->addresses()->where('type', 'temporary')->delete();
        }
    }

    private function syncDocuments(Profile $profile, ApplicantProfileUpdateRequest $request): void
    {
        foreach (self::DOCUMENT_INPUTS as $input => $config) {
            if (! $request->hasFile($input)) {
                continue;
            }

            $path = $request->file($input)->store('profiles/'.$profile->id, 'private');

            $existing = $profile->documents()->where('document_type', $config['type'])->first();

            if ($existing && $existing->file_path !== $path) {
                Storage::disk('private')->delete($existing->file_path);
            }

            $profile->documents()->updateOrCreate(
                ['document_type' => $config['type']],
                [
                    'file_path' => $path,
                    'document_number' => $config['number_field'] ? $profile->{$config['number_field']} : null,
                    'status' => 'pending',
                ],
            );
        }
    }

    private function syncSourcesOfFunds(Profile $profile, array $validated): void
    {
        $sources = array_values(array_unique((array) ($validated['sources'] ?? [])));

        $profile->sourcesOfFunds()->whereNotIn('source_type', $sources)->delete();

        foreach ($sources as $source) {
            $profile->sourcesOfFunds()->updateOrCreate(
                ['source_type' => $source],
                ['description' => $source === 'other' ? ($validated['source_other_description'] ?? null) : null],
            );
        }
    }

    private function syncNominee(Profile $profile, array $nominee): void
    {
        if (blank($nominee['full_name'] ?? null)) {
            $profile->nominees()->delete();

            return;
        }

        $record = $profile->nominees()->first() ?? $profile->nominees()->make();
        $record->fill([
            'full_name' => $nominee['full_name'],
            'relationship' => $nominee['relationship'] ?? '',
            'mobile' => $nominee['mobile'] ?? null,
            'address' => $nominee['address'] ?? null,
        ])->save();
    }

    private function syncExperiences(Profile $profile, array $experiences): void
    {
        $profile->experiences()->delete();

        foreach ($experiences as $experience) {
            if (blank($experience['organization_name'] ?? null)) {
                continue;
            }

            $profile->experiences()->create(Arr::only($experience, ['organization_name', 'address', 'position', 'years']));
        }
    }
}
