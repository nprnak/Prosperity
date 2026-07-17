<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicantManagement\Requests\ApplicantProfileUpdateRequest;
use Modules\ApplicantManagement\Services\ApplicantProfileService;
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
    ) {
    }

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
            'geography' => $this->geography->flat(),
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

    public function updateApplicantProfile(ApplicantProfileUpdateRequest $request, ApplicantProfileService $applicantProfiles): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $applicantProfiles->update($user, $request);

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

        $profile = $this->profiles->findByUserId($user->id);

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

        $this->users->destroy($user->id);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
