<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ApplicationEventRepository;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\ApprovalManagement\Notifications\ApplicationReturnedNotification;
use Modules\PaymentManagement\Models\PaymentDeposit;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\PaymentManagement\Notifications\PaymentVerifiedNotification;
use Modules\PaymentManagement\Repositories\PaymentMethodRepository;
use Modules\PaymentManagement\Requests\VerifyPaymentRequest;

class FinanceController extends Controller
{
    public function __construct(
        private ShareApplicationRepository $applications,
        private ApplicationEventRepository $events,
    ) {}

    public function dashboard(Request $request, PaymentMethodRepository $paymentMethods)
    {
        $status = $request->string('status')->toString();

        $applications = $this->applications->listByStatus(
            $status ?: [
                ApplicationStatus::Submitted,
                ApplicationStatus::SentToBank,
                ApplicationStatus::BankAccepted,
                ApplicationStatus::Blocked,
                ApplicationStatus::PaymentPending,
                ApplicationStatus::PaymentVerified,
            ],
            // The deposits are the unit of work here, and the officer names
            // ride along so the second signatory can see whose check they are
            // re-verifying.
            ['applicant', 'paymentTransactions.deposits', 'paymentTransactions.checker:id,name',
                'paymentTransactions.verifier:id,name', 'vouchers'],
        );

        return Inertia::render('Finance/Dashboard', [
            'applications' => $applications,
            'status' => $status,
            'paymentMethods' => $paymentMethods->active(['id', 'name']),
        ]);
    }

    /**
     * Verify or reject one deposit.
     *
     * Each slip is checked against its own bank record, so this is where a
     * payment is actually accepted or queried. The transaction's two-officer
     * sign-off comes afterwards, once every deposit on the receipt has been
     * settled.
     */
    public function verifyDeposit(VerifyPaymentRequest $request, PaymentDeposit $deposit)
    {
        $status = $request->validated('status');

        $deposit->update([
            'verification_status' => $status,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $request->validated('notes'),
        ]);

        // A deposit that fails after the chain has passed the application is
        // the same problem as a rejected receipt: the money is not there, and
        // only the applicant can do anything about it.
        if ($status === 'rejected') {
            $application = $deposit->paymentTransaction?->shareApplication;

            if ($application) {
                $this->returnForFailedPayment($application, $request->user(), $request->validated('notes'));
            }
        }

        return back()->with('success', 'Deposit '.$status.'.');
    }

    /**
     * Two-officer sign-off: the first "verified" click checks the payment
     * (checked_by); a second, different finance officer's click re-verifies it
     * (verified_by) and only then does the payment count as verified.
     */
    public function verifyPayment(VerifyPaymentRequest $request, PaymentTransaction $payment)
    {
        $status = $request->validated('status');

        // The receipt cannot be signed off while a slip beneath it is still
        // unchecked or has been queried — it would acknowledge money nobody
        // confirmed arrived.
        if ($status === 'verified' && ! $payment->allDepositsVerified()) {
            abort(422, 'Every deposit on this receipt must be verified first.');
        }

        if ($status === 'verified' && ! $payment->checked_by) {
            $payment->update([
                'checked_by' => $request->user()->id,
                'checked_at' => now(),
                'notes' => $request->validated('notes'),
            ]);

            return back()->with('success', 'Payment checked. Awaiting re-verification by another finance officer.');
        }

        if ($status === 'verified') {
            abort_if($payment->checked_by === $request->user()->id, 422,
                'A different finance officer must re-verify this payment.');
        }

        $payment->update([
            'verification_status' => $status,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $request->validated('notes'),
        ]);

        $application = $payment->shareApplication;
        $oldStatus = $application->status;

        if ($status === 'rejected') {
            $this->returnForFailedPayment($application, $request->user(), $request->validated('notes'));
        }

        $application->syncPaymentVerificationStatus();

        if ($application->status !== $oldStatus) {
            $this->events->record($application, $request->user()->id,
                $oldStatus, $application->status,
                'Payment verification updated by finance.');
        }

        if ($application->status === ApplicationStatus::PaymentVerified) {
            if ($application->applicant?->email) {
                Notification::route('mail', $application->applicant->email)
                    ->notify(new PaymentVerifiedNotification($application));
            }

            $application->applicant?->user?->notify(new PaymentVerifiedNotification($application));
        }

        return back()->with('success', 'Payment verification updated.');
    }

    /**
     * Hand an application back when its money turns out not to have arrived.
     *
     * Only once the review chain has taken it on. Before that,
     * syncPaymentVerificationStatus moves it between PaymentPending and
     * PaymentVerified on its own and there is no sign-off at stake.
     *
     * Returning rather than rewinding matters: rewinding would drop the
     * application into an earlier staff queue while the problem is the
     * applicant's deposit, and would silently discard sign-offs already given.
     * A returned application is one the applicant can actually act on, and
     * resubmitting it restarts the chain from the first stage.
     */
    private function returnForFailedPayment(ShareApplication $application, User $actor, ?string $reason): void
    {
        if (in_array($application->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::Returned,
            ApplicationStatus::Submitted,
            ApplicationStatus::SentToBank,
            ApplicationStatus::BankAccepted,
            ApplicationStatus::Blocked,
            ApplicationStatus::PaymentPending,
            ApplicationStatus::PaymentVerified,
        ], true)) {
            return;
        }

        $reason = $reason ?: 'A payment on this application could not be verified.';
        $fromStatus = $application->status;

        $application->forceFill([
            'status' => ApplicationStatus::Returned,
            'rejection_reason' => $reason,
        ])->save();

        $this->events->record($application, $actor->id, $fromStatus, ApplicationStatus::Returned,
            'Returned by finance: '.$reason);

        $application->applicant?->user?->notify(new ApplicationReturnedNotification($application));

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationReturnedNotification($application));
        }
    }
}
