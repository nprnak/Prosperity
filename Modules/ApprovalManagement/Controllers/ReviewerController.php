<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Concerns\RecordsStageTransitions;
use Modules\ApprovalManagement\Requests\RejectApplicationRequest;
use Modules\ApprovalManagement\Requests\ReviewApplicationRequest;

class ReviewerController extends Controller
{
    use RecordsStageTransitions;

    public function dashboard()
    {
        return Inertia::render('Reviewer/Dashboard', [
            'applications' => ShareApplication::query()
                ->where('status', ShareApplication::STATUS_PAYMENT_VERIFIED)
                ->with(['applicant', 'paymentTransactions'])
                ->latest()
                ->get(),
        ]);
    }

    public function review(ReviewApplicationRequest $request, ShareApplication $application)
    {
        abort_unless($application->status === ShareApplication::STATUS_PAYMENT_VERIFIED, 422);

        $this->transition($request, $application, ShareApplication::STATUS_REVIEWED,
            $request->validated('remarks') ?: 'Application reviewed.',
            ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('success', 'Application marked as reviewed.');
    }

    public function reject(RejectApplicationRequest $request, ShareApplication $application)
    {
        abort_unless($application->status === ShareApplication::STATUS_PAYMENT_VERIFIED, 422);

        return $this->rejectAtStage($request, $application, 'review');
    }
}
