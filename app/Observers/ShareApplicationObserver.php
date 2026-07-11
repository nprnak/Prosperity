<?php

namespace App\Observers;

use App\Models\ShareApplication;

class ShareApplicationObserver
{
    public function updating(ShareApplication $application): void
    {
        if ($application->isDirty('status')) {
            if (in_array($application->status, [ShareApplication::STATUS_APPROVED, ShareApplication::STATUS_REJECTED], true) && ! $application->reviewed_at) {
                $application->reviewed_at = now();
            }

            if ($application->status === ShareApplication::STATUS_SUBMITTED && ! $application->submitted_at) {
                $application->submitted_at = now();
            }
        }
    }
}
