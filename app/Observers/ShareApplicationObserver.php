<?php

namespace App\Observers;

use Modules\ApplicationManagement\Models\ShareApplication;

class ShareApplicationObserver
{
    public function updating(ShareApplication $application): void
    {
        if ($application->isDirty('status')) {
            if (in_array($application->status, [ShareApplication::STATUS_APPROVED, ShareApplication::STATUS_REJECTED], true) && ! $application->reviewed_at) {
                $application->reviewed_at = now();
            }

            if (in_array($application->status, [
                ShareApplication::STATUS_SUBMITTED,
                ShareApplication::STATUS_SENT_TO_BANK,
                ShareApplication::STATUS_BANK_ACCEPTED,
                ShareApplication::STATUS_BLOCKED,
            ], true) && ! $application->submitted_at) {
                $application->submitted_at = now();
            }
        }
    }
}
