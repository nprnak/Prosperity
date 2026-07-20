<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;
use Illuminate\Http\Request;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Notifications\ApplicationApprovedNotification;
use Modules\VoucherManagement\Services\VoucherIssueService;

/**
 * Application stage 3: final sign-off. Approval here is what issues the
 * voucher and notifies the applicant.
 */
class ApproverController extends ApplicationStageController
{
    protected function stage(): WorkflowStage
    {
        return WorkflowStage::Approver;
    }

    protected function view(): string
    {
        return 'Approver/Dashboard';
    }

    protected function afterAct(Request $request, ShareApplication $application, ApplicationStatus $before): void
    {
        if ($application->status === $before) {
            return;
        }

        if ($application->status === ApplicationStatus::Approved) {
            $this->issueVoucher($request, $application);

            return;
        }

        parent::afterAct($request, $application, $before);
    }

    private function issueVoucher(Request $request, ShareApplication $application): void
    {
        $payment = $application->paymentTransactions()
            ->where('verification_status', 'verified')
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
    }
}
