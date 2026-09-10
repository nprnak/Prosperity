<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Enums\WorkflowStage;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Notifications\ApplicationApprovedNotification;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\ApprovalManagement\Requests\ApplicationWorkflowActionRequest;
use Modules\VoucherManagement\Models\Voucher;
use Modules\VoucherManagement\Services\VoucherIssueService;

/**
 * Application stage 3: final sign-off. Approval here is what issues the
 * voucher and notifies the applicant.
 *
 * Browsing the queue is handled by ApplicationReviewController, which merges
 * all three stages into one page — this controller now only ever acts.
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

        // Approval is the money sign-off now: the review chain has already
        // seen every declared voucher on the application form, so there is no
        // separate finance verification left to wait on. Every transaction on
        // the application is settled here rather than just the latest, since
        // an application can in principle carry more than one receipt.
        $application->paymentTransactions->each(fn (PaymentTransaction $transaction) => $transaction->update([
            'verification_status' => 'verified',
            'checked_by' => $transaction->checked_by ?? $request->user()->id,
            'checked_at' => $transaction->checked_at ?? now(),
            'verified_by' => $request->user()->id,
            'approved_by' => $request->user()->id,
        ]));

        $payment->refresh();

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
