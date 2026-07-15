<?php

namespace Modules\ApplicationManagement\Policies;

use App\Models\User;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareApplicationPolicy
{
    /**
     * Only the applicant who owns the draft may submit it.
     * (Admins pass via the Gate::before shortcut.)
     */
    public function submit(User $user, ShareApplication $application): bool
    {
        return $user->can('application.submit')
            && $application->applicant?->user_id === $user->id;
    }
}
