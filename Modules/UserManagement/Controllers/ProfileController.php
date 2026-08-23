<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ApplicantManagement\Enums\EducationLevel;
use Modules\ApplicantManagement\Enums\Gender;
use Modules\ApplicantManagement\Enums\MaritalStatus;
use Modules\ApplicantManagement\Enums\SourceOfFunds;
use Modules\ApplicantManagement\Enums\Title;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicantManagement\Requests\ApplicantProfileUpdateRequest;
use Modules\ApplicantManagement\Services\ApplicantProfileService;
use Modules\ApplicantManagement\Services\ProfileDocumentService;
use Modules\SettingsManagement\Repositories\GeographyRepository;
use Modules\UserManagement\Repositories\UserRepository;
use Modules\UserManagement\Requests\ProfileUpdateRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileRepository $profiles,
        private GeographyRepository $geography,
        private UserRepository $users,
    ) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $profile = $this->profiles->findByUserIdWithKyc($user->id);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'profile' => $profile,
            'completionPercent' => $profile?->completionPercent() ?? 0,
            // Latest reviewer remarks, shown when a profile comes back for changes.
            'reviewRemarks' => $profile?->latest_workflow_remarks,
            'geography' => $this->geography->flat(),
            // Single source of truth for the KYC form's constrained fields.
            'options' => [
                'titles' => Title::options(),
                'genders' => Gender::options(),
                'maritalStatuses' => MaritalStatus::options(),
                'educationLevels' => EducationLevel::options(),
                'sourcesOfFunds' => SourceOfFunds::options(),
            ],
        ]);
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('Profile/Settings', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'signatureDetails' => [
                'signature_name' => $user->signature_name,
                'signature_designation' => $user->signature_designation,
                'has_signature' => filled($user->signature_path),
            ],
        ]);
    }

    public function updateSignature(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'signature_name' => ['nullable', 'string', 'max:255'],
            'signature_designation' => ['nullable', 'string', 'max:255'],
            'signature_file' => ['nullable', 'image', 'max:2048'],
        ]);

        $attributes = [
            'signature_name' => $validated['signature_name'] ?? null,
            'signature_designation' => $validated['signature_designation'] ?? null,
        ];

        if ($request->hasFile('signature_file')) {
            if ($user->signature_path) {
                Storage::disk('private')->delete($user->signature_path);
            }

            $attributes['signature_path'] = $request->file('signature_file')->store('user-signatures', 'private');
        }

        $user->forceFill($attributes)->save();

        return Redirect::route('settings.edit')->with('success', 'Signature details updated.');
    }

    public function signaturePreview(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless(is_string($user->signature_path) && $user->signature_path !== '', 404);
        abort_unless(Storage::disk('private')->exists($user->signature_path), 404);

        return response()->file(Storage::disk('private')->path($user->signature_path));
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

        $nameChanged = $user->isDirty('name');

        $user->save();

        // The KYC form freezes once a profile is with/past the review chain,
        // but the applicant's display name is exempt from that lock: it
        // should always follow their account name, approved or not.
        if ($nameChanged) {
            $this->profiles->findByUserId($user->id)?->forceFill(['full_name_en' => $user->name])->save();
        }

        return Redirect::route('settings.edit')->with('success', 'Account details updated.');
    }

    public function updateApplicantProfile(ApplicantProfileUpdateRequest $request, ApplicantProfileService $applicantProfiles): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $applicantProfiles->update($user, $request);

        // 'status' drives the inline banner on the form; 'success' drives the toast.
        return Redirect::route('profile.edit')
            ->with('status', 'applicant-profile-updated')
            ->with('success', 'Profile saved.');
    }

    /**
     * The applicant's own uploads. Reviewers reach the same files through
     * ApplicantProfileReviewController, which authorises via ProfilePolicy.
     */
    public function document(Request $request, string $type, ProfileDocumentService $documents): BinaryFileResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $profile = $this->profiles->findByUserId($user->id);

        abort_unless($profile instanceof Profile, 404);

        return $documents->respond($profile, $type, $request->query('mode') === 'download');
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

        $this->users->destroy($user->id);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
