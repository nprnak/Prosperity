<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;

/**
 * The application review queue: verifier, reviewer and approver share one
 * page, the way the KYC review queue already merges its three stages into
 * one. Which applications show up is derived from whichever of the three
 * permissions the viewer holds — someone holding more than one sees them
 * all merged into the same list rather than needing a queue per stage.
 *
 * The actual sign-off still happens on the application detail page
 * (AdminApplicationsController::show / Admin/ApplicationShow.vue), which
 * already renders the right stage's actions — this controller only ever
 * lists records and links out to it, mirroring ApplicantProfileReviewController.
 */
class ApplicationReviewController extends Controller
{
    public function __construct(private ShareApplicationRepository $applications) {}

    public function dashboard(Request $request)
    {
        $search = $request->query('q');
        $user = $request->user();
        $with = ['applicant', 'paymentTransactions.voucher', 'workflowEvents.actor:id,name'];

        return Inertia::render('ApplicationReview', [
            'pending' => $this->applications->pendingForUser($user, $with, search: $search),
            'decided' => $this->applications->decidedByUser($user, $with, search: $search),
            // Only meaningful for an Application Verifier, but harmless (and
            // simplest) to include for anyone — empty for staff who filed none.
            'enteredByMe' => $this->applications->enteredBy($user->id, $with, search: $search),
            'filters' => ['q' => $search],
        ]);
    }
}
