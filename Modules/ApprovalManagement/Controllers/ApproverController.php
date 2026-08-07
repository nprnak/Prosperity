<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Notifications\ApplicationApprovedNotification;
use Modules\ApprovalManagement\Requests\ApplicationWorkflowActionRequest;
use Modules\VoucherManagement\Models\Voucher;
use Modules\VoucherManagement\Services\VoucherIssueService;

/**
 * Application stage 3: final sign-off. Approval here is what issues the
 * voucher and notifies the applicant.
 */
class ApproverController extends ApplicationStageController
{
    private ?Voucher $issuedVoucher = null;

    protected function stage(): WorkflowStage
    {
        return WorkflowStage::Approver;
    }

    protected function view(): string
    {
        return 'Approver/Dashboard';
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
            'approvedByMe' => $this->applications->approvedByUser(
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
                'workflow' => 'Open and review the application form before marking it approved.',
            ]);
        }

        $response = parent::act($request, $application);

        if ($request->action()->value === 'approve') {
            Cache::forget($this->viewedCacheKey($request->user()->id, $application->id));
        }

        return $response;
    }

    protected function afterAct(Request $request, ShareApplication $application, ApplicationStatus $before): void
    {
        if ($application->status === $before) {
            return;
        }

        if ($application->status === ApplicationStatus::Approved) {
            $this->issuedVoucher = $this->issueVoucher($request, $application);

            return;
        }

        parent::afterAct($request, $application, $before);
    }

    protected function redirectAfterAct(Request $request, ShareApplication $application, ApplicationStatus $before): ?RedirectResponse
    {
        if ($application->status === ApplicationStatus::Approved && $this->issuedVoucher) {
            return redirect()->route('vouchers.show', $this->issuedVoucher->id);
        }

        return null;
    }

    private function issueVoucher(Request $request, ShareApplication $application): Voucher
    {
        $payment = $application->paymentTransactions()
            ->latest()
            ->firstOrFail();

        $payment->update(['approved_by' => $request->user()->id]);

        $voucher = app(VoucherIssueService::class)->issue($application, $payment, $request->user()->id);

        $application->forceFill([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $this->notifyApplicant($application, new ApplicationApprovedNotification($application, $voucher));

        return $voucher;
    }

    private function viewedCacheKey(int $userId, int $applicationId): string
    {
        return "application:viewed:{$userId}:{$applicationId}";
    }
}
