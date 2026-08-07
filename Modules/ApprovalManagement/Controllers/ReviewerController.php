<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Requests\ApplicationWorkflowActionRequest;

/** Application stage 2: acts on verified applications. */
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

    public function dashboard(Request $request)
    {
        $search = $request->query('q');

        $applications = $this->applications->pendingForStage(
            $this->stage(),
            $request->user(),
            ['applicant', 'paymentTransactions.voucher', 'workflowEvents.actor:id,name'],
            search: $search,
        );

        $viewedApplicationIds = [];

        foreach ($applications->items() as $application) {
            if (Cache::get($this->viewedCacheKey($request->user()->id, $application->id), false)) {
                $viewedApplicationIds[] = $application->id;
            }
        }

        return Inertia::render($this->view(), [
            'applications' => $applications,
            'viewedApplicationIds' => $viewedApplicationIds,
            'reviewedByMe' => $this->applications->reviewedByUser(
                $request->user(),
                ['applicant', 'paymentTransactions.voucher', 'workflowEvents.actor:id,name'],
                search: $search,
            ),
            'filters' => ['q' => $search],
        ]);
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
