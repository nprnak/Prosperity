<?php

namespace Modules\ApplicationManagement\Policies;

use App\Models\User;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareApplicationPolicy
{
    /**
     * The owning applicant may view their own application; staff need
     * the view-any permission. (Admins pass via the Gate::before shortcut.)
     */
    public function view(User $user, ShareApplication $application): bool
    {
        return $application->applicant?->user_id === $user->id
            || $user->can('application.view-any');
    }

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
