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
        if ($application->status === ApplicationStatus::Verified) {
            $application->paymentTransactions()
                ->where('verification_status', '!=', 'verified')
                ->update([
                    'verification_status' => 'verified',
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                ]);

            Cache::forget($this->viewedCacheKey($request->user()->id, $application->id));
        }

        parent::afterAct($request, $application, $before);
    }

    private function viewedCacheKey(int $userId, int $applicationId): string
    {
        return "application:viewed:{$userId}:{$applicationId}";
    }
}
