<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicationManagement\Repositories\ShareApplicationRepository;
use Modules\ApprovalManagement\Concerns\RecordsStageTransitions;
use Modules\ApprovalManagement\Notifications\ApplicationApprovedNotification;
use Modules\ApprovalManagement\Requests\ApproveApplicationRequest;
use Modules\ApprovalManagement\Requests\RejectApplicationRequest;
use Modules\VoucherManagement\Services\VoucherIssueService;

class ApproverController extends Controller
{
    use RecordsStageTransitions;

    public function __construct(private ShareApplicationRepository $applications)
    {
    }

    public function dashboard(Request $request)
    {
        return Inertia::render('Approver/Dashboard', [
            'applications' => $this->applications->listByStatus(
                ShareApplication::STATUS_VERIFIED,
                ['applicant', 'paymentTransactions', 'reviewer', 'verifier'],
            ),
        ]);
    }

    public function approve(ApproveApplicationRequest $request, ShareApplication $application, VoucherIssueService $voucherIssuer)
    {
        abort_unless($application->status === ShareApplication::STATUS_VERIFIED, 422);

        $payment = $application->paymentTransactions()->where('verification_status', 'verified')->latest()->firstOrFail();
        $payment->update(['approved_by' => $request->user()->id]);

        $voucher = $voucherIssuer->issue($application, $payment, $request->user()->id);

        $this->transition($request, $application, ShareApplication::STATUS_APPROVED,
            'Application approved by approver.',
            ['approved_by' => $request->user()->id, 'approved_at' => now(), 'rejection_reason' => null]);

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationApprovedNotification($application, $voucher));
        }

        $application->applicant?->user?->notify(new ApplicationApprovedNotification($application, $voucher));

        return back()->with('success', 'Application approved and voucher generated.');
    }

    public function reject(RejectApplicationRequest $request, ShareApplication $application)
    {
        return $this->rejectAtStage($request, $application, 'approval');
    }
}
