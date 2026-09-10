<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Requests\ApplicationWorkflowActionRequest;

/**
 * Application stage 1: acts on submitted applications.
 *
 * Browsing the queue is handled by ApplicationReviewController, which merges
 * all three stages into one page — this controller now only ever acts.
 */
class VerifierController extends ApplicationStageController
{
    protected function stage(): WorkflowStage
    {
        return WorkflowStage::Verifier;
    }

    protected function view(): string
    {
        return 'Verifier/Dashboard';
    }

    public function act(ApplicationWorkflowActionRequest $request, ShareApplication $application)
    {
        if ($request->action()->value === 'approve' && ! Cache::get($this->viewedCacheKey($request->user()->id, $application->id), false)) {
            throw ValidationException::withMessages([
                'workflow' => 'Open and review the application form before marking it verified.',
            ]);
        }

        return parent::act($request, $application);
    }

    protected function afterAct(Request $request, ShareApplication $application, ApplicationStatus $before): void
    {
        // Payment verification happens once, at final approval
        // (ApproverController) — not here. Stage 1 is only the first of three
        // sign-offs, and an application can still be sent back after it, so
        // marking the payment verified this early would call money confirmed
        // on an application nobody has actually approved yet.
        if ($application->status === ApplicationStatus::Verified) {
            Cache::forget($this->viewedCacheKey($request->user()->id, $application->id));
        }

        parent::afterAct($request, $application, $before);
    }

    private function viewedCacheKey(int $userId, int $applicationId): string
    {
        return "application:viewed:{$userId}:{$applicationId}";
    }
}
