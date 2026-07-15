<?php

namespace Modules\ApprovalManagement\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Modules\ApplicationManagement\Models\ApplicationEvent;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApprovalManagement\Notifications\ApplicationRejectedNotification;
use Modules\ApprovalManagement\Requests\RejectApplicationRequest;

trait RecordsStageTransitions
{
    /**
     * Moves the application to a new status and records the transition as an
     * ApplicationEvent, including the actor's IP and browser for the audit trail.
     */
    protected function transition(Request $request, ShareApplication $application, string $toStatus, string $remarks, array $attributes = [], array $meta = []): void
    {
        $fromStatus = $application->status;

        $application->update([...$attributes, 'status' => $toStatus]);

        ApplicationEvent::query()->create([
            'share_application_id' => $application->id,
            'actor_id' => $request->user()->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'remarks' => $remarks,
            'meta' => [...$meta, 'ip' => $request->ip(), 'user_agent' => $request->userAgent()],
        ]);
    }

    protected function rejectAtStage(RejectApplicationRequest $request, ShareApplication $application, string $stage)
    {
        $reason = $request->validated('rejection_reason');

        $this->transition($request, $application, ShareApplication::STATUS_REJECTED,
            "Application rejected at {$stage} stage.",
            ['rejection_reason' => $reason],
            ['reason' => $reason]);

        if ($application->applicant?->email) {
            Notification::route('mail', $application->applicant->email)
                ->notify(new ApplicationRejectedNotification($application));
        }

        $application->applicant?->user?->notify(new ApplicationRejectedNotification($application));

        return back()->with('success', 'Application rejected.');
    }
}
