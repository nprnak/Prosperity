<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\ApplicantManagement\Models\Profile;
use Modules\UserManagement\Services\FocalPersonService;

/**
 * Sets the applicant's default focal person — what their future applications
 * are credited to unless they quote a different code on the application itself.
 *
 * Attribution only: the service writes the one column and touches neither
 * profile_status nor the workflow cycle, so re-attributing an approved profile
 * does not drag it back through KYC review.
 */
class ApplicantFocalPersonController extends Controller
{
    public function __construct(private FocalPersonService $focalPersons) {}

    public function update(Request $request, Profile $applicant): RedirectResponse
    {
        $validated = $request->validate([
            // Eligibility (designated + KYC approved) is enforced by the
            // service, which fails with a validation error on this same key.
            'focal_person_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->focalPersons->assignToProfile($applicant, $validated['focal_person_id'] ?? null);

        return back()->with('success', 'Focal person updated.');
    }
}
