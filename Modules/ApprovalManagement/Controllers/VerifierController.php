<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Requests\ApplicationWorkflowActionRequest;

/** Application stage 1: acts on submitted applications. */
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

    public function dashboard(Request $request)
    {
        $pending = $this->applications->pendingForStage(
            $this->stage(),
            $request->user(),
            ['applicant', 'paymentTransactions.voucher', 'workflowEvents.actor:id,name'],
        );

        $viewedApplicationIds = [];

        foreach ($pending->items() as $application) {
            if (Cache::get($this->viewedCacheKey($request->user()->id, $application->id), false)) {
                $viewedApplicationIds[] = $application->id;
            }
        }

        return Inertia::render($this->view(), [
            'pendingApplications' => $pending,
            'viewedApplicationIds' => $viewedApplicationIds,
            'verifiedByMe' => $this->applications->verifiedByUser(
                $request->user(),
                ['applicant', 'paymentTransactions.voucher', 'workflowEvents.actor:id,name'],
            ),
        ]);
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
