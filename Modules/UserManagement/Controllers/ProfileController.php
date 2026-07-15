<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use Modules\ApplicantManagement\Requests\ApplicantProfileUpdateRequest;
use Modules\UserManagement\Requests\ProfileUpdateRequest;
use Modules\ApplicantManagement\Models\Applicant;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $applicant = Applicant::query()->firstOrNew(
            ['user_id' => $user->id],
            [
                'full_name_nepali' => '',
                'full_name_english' => $user->name,
                'email' => $user->email,
                'date_of_birth' => now()->subYears(18)->toDateString(),
                'age' => 18,
                'nationality' => 'Nepali',
                'marital_status' => 'single',
                'boid' => '',
                'crn_number' => '',
                'bank_name' => '',
                'bank_code' => '',
                'bank_branch' => '',
                'bank_account_number' => '',
                'account_holder_name' => '',
                'asba_consent' => false,
            ]
        );

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'applicant' => $applicant,
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

        $applicant = Applicant::query()->firstOrNew(['user_id' => $user->id]);
        $validated = $request->validated();

        $uploadMap = [
            'photo' => 'photo_path',
            'citizenship_doc' => 'citizenship_doc_path',
            'national_id_doc' => 'national_id_doc_path',
            'pan_doc' => 'pan_doc_path',
        ];

        foreach ($uploadMap as $fileInput => $dbColumn) {
            if ($request->hasFile($fileInput)) {
                $validated[$dbColumn] = $request->file($fileInput)->store('applications', 'private');
            }

            unset($validated[$fileInput]);
        }

        $applicant->fill($validated);

        // Keep share applicant identity tied to login account details.
        $applicant->full_name_english = $user->name;
        $applicant->email = $user->email;
        $applicant->user_id = $user->id;

        $missingDocuments = [];

        foreach (['photo_path', 'citizenship_doc_path', 'national_id_doc_path', 'pan_doc_path'] as $docField) {
            if (blank($applicant->{$docField})) {
                $missingDocuments[] = $docField;
            }
        }

        if ($missingDocuments !== []) {
            return Redirect::route('profile.edit')->withErrors([
                'documents' => 'Please upload all required documents: photo, citizenship, national ID, and PAN.',
            ]);
        }

        $applicant->save();

        return Redirect::route('profile.edit')->with('status', 'applicant-profile-updated');
    }

    public function document(Request $request, string $type): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $fieldByType = [
            'photo' => 'photo_path',
            'citizenship' => 'citizenship_doc_path',
            'national-id' => 'national_id_doc_path',
            'pan' => 'pan_doc_path',
        ];

        $field = $fieldByType[$type] ?? null;

        abort_unless($field !== null, 404);

        $applicant = Applicant::query()->where('user_id', $user->id)->first();

        abort_unless($applicant instanceof Applicant, 404);

        $path = $applicant->{$field};

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
}
