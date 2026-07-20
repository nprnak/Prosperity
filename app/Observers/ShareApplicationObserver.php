<?php

namespace App\Observers;

use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareApplicationObserver
{
    public function updating(ShareApplication $application): void
    {
        if ($application->isDirty('status')) {
            if (in_array($application->status, [ApplicationStatus::Approved, ApplicationStatus::Returned], true) && ! $application->reviewed_at) {
                $application->reviewed_at = now();
            }

            if (in_array($application->status, [
                ApplicationStatus::Submitted,
                ApplicationStatus::SentToBank,
                ApplicationStatus::BankAccepted,
                ApplicationStatus::Blocked,
            ], true) && ! $application->submitted_at) {
                $application->submitted_at = now();
            }
        }
    }
}
