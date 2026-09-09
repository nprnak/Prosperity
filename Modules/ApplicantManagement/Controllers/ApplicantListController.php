<?php

namespace Modules\ApplicantManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicantManagement\Repositories\ProfileRepository;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;

/**
 * The full roster of approved applicants — separate from the review queues,
 * which only ever show what's waiting on a stage.
 *
 * Whoever can see the applications list (application.view-any: finance,
 * admin) sees every approved applicant here, and so does anyone in the
 * application review chain: an Application Verifier files a paper
 * application for any approved applicant, not only ones they themselves
 * entered — entered_by is a KYC-side concept, never set by that chain. A
 * profile_verifier/reviewer/approver, who *can* be an entered_by, sees only
 * the ones they entered themselves.
 */
class ApplicantListController extends Controller
{
    private const SEES_ALL_PERMISSIONS = [
        'application.view-any', 'application.verify', 'application.review', 'application.approve',
    ];

    public function __construct(private ProfileRepository $profiles) {}

    public function index(Request $request)
    {
        $search = $request->query('q');
        $user = $request->user();
        $seesAll = $user->hasAnyPermission(self::SEES_ALL_PERMISSIONS);

        return Inertia::render('Applicants/Index', [
            'applicants' => $this->profiles->approved($search, $seesAll ? null : $user->id),
            'filters' => ['q' => $search],
            'seesAll' => $seesAll,
        ]);
    }

    /**
     * One applicant's own application history — every share application
     * they have filed, reached from their row on the Applicant List.
     * Gated the same as their KYC profile (ProfilePolicy::view): either
     * review chain may look, since both work from the same roster.
     */
    public function applications(Request $request, Profile $applicant, ShareApplicationRepository $applications)
    {
        Gate::authorize('view', $applicant);

        return Inertia::render('Applicants/Applications', [
            'applicant' => $applicant->only(['id', 'full_name_en', 'user_id']),
            'applications' => $applicant->user_id ? $applications->listForUser($applicant->user_id) : [],
        ]);
    }
}
