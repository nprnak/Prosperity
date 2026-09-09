<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Requests\ApplicationWorkflowActionRequest;

/**
 * Application stage 2: acts on verified applications.
 *
 * Browsing the queue is handled by ApplicationReviewController, which merges
 * all three stages into one page — this controller now only ever acts.
 */
class ReviewerController extends ApplicationStageController
{
    protected function stage(): WorkflowStage
    {
        return WorkflowStage::Reviewer;
    }

    protected function view(): string
    {
        return 'Reviewer/Dashboard';
    }

    public function act(ApplicationWorkflowActionRequest $request, ShareApplication $application)
    {
        if ($request->action()->value === 'approve' && ! Cache::get($this->viewedCacheKey($request->user()->id, $application->id), false)) {
            throw ValidationException::withMessages([
                'workflow' => 'Open and review the application form before marking it reviewed.',
            ]);
        }

        $response = parent::act($request, $application);

        if ($request->action()->value === 'approve') {
            Cache::forget($this->viewedCacheKey($request->user()->id, $application->id));
        }

        return $response;
    }

    private function viewedCacheKey(int $userId, int $applicationId): string
    {
        return "application:viewed:{$userId}:{$applicationId}";
    }
}
