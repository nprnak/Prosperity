<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\UserManagement\Repositories\FocalPersonRepository;

/**
 * Resolves a focal person code to a name, for the applicant's application form.
 *
 * Deliberately a code lookup and not a list endpoint: eligibility is "any
 * KYC-approved user", so serving a browsable directory here would publish the
 * real names of approved users to every applicant. One exact code in, one name
 * out — and the route is throttled so the code space cannot be walked.
 */
class FocalPersonLookupController extends Controller
{
    public function __construct(private FocalPersonRepository $focalPersons) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $focalPerson = $this->focalPersons->findEligibleByCode($validated['code']);

        if (! $focalPerson) {
            return response()->json([
                'found' => false,
                'message' => 'No focal person found with that code.',
            ], 404);
        }

        // Name and code only. Nothing else about the user is the applicant's
        // business — they are confirming they typed the right code.
        return response()->json([
            'found' => true,
            'code' => $focalPerson->focal_person_code,
            'name' => $focalPerson->name,
        ]);
    }
}
