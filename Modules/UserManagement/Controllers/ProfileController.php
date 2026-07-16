<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\LocalLevel;
use App\Models\Province;
use Modules\ApplicantManagement\Requests\ApplicantProfileUpdateRequest;
use Modules\UserManagement\Requests\ProfileUpdateRequest;
use Modules\ApplicantManagement\Models\Profile;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
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

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $profile = Profile::query()
            ->with(['permanentAddress', 'temporaryAddress', 'documents', 'sourcesOfFunds', 'nominees', 'experiences'])
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'profile' => $profile,
            'completionPercent' => $profile?->completionPercent() ?? 0,
            'geography' => $this->geography(),
        ]);
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('Profile/Settings', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('settings.edit');
    }

    public function updateApplicantProfile(ApplicantProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $profile = Profile::query()->firstOrNew(['user_id' => $user->id]);
        $validated = $request->validated();

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

        return Redirect::route('profile.edit')->with('status', 'applicant-profile-updated');
    }

    public function document(Request $request, string $type): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $typeByRoute = [
            'photo' => 'photo',
            'citizenship-front' => 'citizenship_front',
            'citizenship-back' => 'citizenship_back',
            'national-id' => 'national_id',
            'pan' => 'pan',
            'signature' => 'signature',
        ];

        $documentType = $typeByRoute[$type] ?? null;

        abort_unless($documentType !== null, 404);

        $profile = Profile::query()->where('user_id', $user->id)->first();

        abort_unless($profile instanceof Profile, 404);

        $path = $profile->documents()->where('document_type', $documentType)->value('file_path');

        abort_unless(is_string($path) && $path !== '', 404);

        if (! Storage::disk('private')->exists($path)) {
            throw new FileNotFoundException($path);
        }

        $filename = basename($path);
        $absolutePath = storage_path('app/private/'.$path);
        $mode = $request->query('mode');

        if ($mode === 'download') {
            return response()->download($absolutePath, $filename);
        }

        return response()->file($absolutePath, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        abort_unless($user instanceof User, 403);

        Auth::logout();

        User::query()->whereKey($user->id)->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Nepal's federal structure for the cascading address dropdowns.
     */
    private function geography(): array
    {
        return Cache::remember('geography.flat', now()->addDay(), fn () => [
            'provinces' => Province::query()->orderBy('id')->get(['id', 'name_en'])->toArray(),
            'districts' => District::query()->orderBy('name_en')->get(['id', 'province_id', 'name_en'])->toArray(),
            'localLevels' => LocalLevel::query()->orderBy('name_en')->get(['id', 'district_id', 'name_en', 'type'])->toArray(),
        ]);
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
