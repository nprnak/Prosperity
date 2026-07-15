<?php

namespace Modules\ApprovalManagement\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Concerns\RecordsStageTransitions;
use Modules\ApprovalManagement\Requests\RejectApplicationRequest;
use Modules\ApprovalManagement\Requests\VerifyApplicationRequest;

class VerifierController extends Controller
{
    use RecordsStageTransitions;

    public function dashboard()
    {
        return Inertia::render('Verifier/Dashboard', [
            'applications' => ShareApplication::query()
                ->where('status', ShareApplication::STATUS_REVIEWED)
                ->with(['applicant', 'paymentTransactions', 'reviewer'])
                ->latest()
                ->get(),
        ]);
    }

    public function verify(VerifyApplicationRequest $request, ShareApplication $application)
    {
        abort_unless($application->status === ShareApplication::STATUS_REVIEWED, 422);

        $this->transition($request, $application, ShareApplication::STATUS_VERIFIED,
            $request->validated('remarks') ?: 'Application verified.',
            ['verified_by' => $request->user()->id, 'verified_at' => now()]);

        return back()->with('success', 'Application marked as verified.');
    }

    public function reject(RejectApplicationRequest $request, ShareApplication $application)
    {
        abort_unless($application->status === ShareApplication::STATUS_REVIEWED, 422);

        return $this->rejectAtStage($request, $application, 'verification');
    }
}
