<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;
use App\Http\Controllers\Controller;
use App\Workflow\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\ApprovalManagement\Notifications\ApplicationReturnedNotification;
use Modules\ApprovalManagement\Requests\ApplicationWorkflowActionRequest;

/**
 * Shared behaviour for the three application sign-off stages. Each subclass
 * only declares which stage it is and which page it renders — the guards,
 * the audit record and the act-once rule all live in WorkflowService.
 */
abstract class ApplicationStageController extends Controller
{
    public function __construct(
        protected ShareApplicationRepository $applications,
        protected WorkflowService $workflow,
    ) {}

    abstract protected function stage(): WorkflowStage;

    abstract protected function view(): string;

    public function dashboard(Request $request)
    {
        return Inertia::render($this->view(), [
            'applications' => $this->applications->pendingForStage(
                $this->stage(),
                $request->user(),
                ['applicant', 'paymentTransactions', 'workflowEvents.actor:id,name'],
            ),
        ]);
    }

    public function act(ApplicationWorkflowActionRequest $request, ShareApplication $application)
    {
        $before = $application->status;

        $this->workflow->act(
            $application,
            $request->user(),
            $request->action(),
            $request->validated('remarks'),
            $request,
        );

        $application->refresh();

        $this->afterAct($request, $application, $before);

        return back()->with('success', 'Application moved to: '.$application->status->labelEn());
    }

    /**
     * Outcomes the applicant needs to know about. Any stage can reject or
     * return, so this lives here rather than in one subclass.
     */
    protected function afterAct(Request $request, ShareApplication $application, ApplicationStatus $before): void
    {
        if ($application->status === $before) {
            return;
        }

        if ($application->status === ApplicationStatus::Returned) {
            $this->notifyApplicant($application, new ApplicationReturnedNotification($application));
        }
    }

    protected function notifyApplicant(ShareApplication $application, $notification): void
    {
        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)->notify($notification);
        }

        $application->applicant?->user?->notify($notification);
    }
}
