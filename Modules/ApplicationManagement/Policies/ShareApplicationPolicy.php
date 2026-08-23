<?php

namespace Modules\ApplicationManagement\Policies;

use App\Models\User;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareApplicationPolicy
{
    /**
     * The owning applicant may view their own application; staff need
     * the view-any permission, or — for one they filed on a paper applicant's
     * behalf — the verify permission. (Admins pass via the Gate::before shortcut.)
     */
    public function view(User $user, ShareApplication $application): bool
    {
        return $application->applicant?->user_id === $user->id
            || $user->can('application.view-any')
            || $user->can('application.verify');
    }

    /**
     * The applicant who owns the draft may submit it. An Application
     * Verifier may also submit one on behalf of a paper applicant they
     * filed it for — the verify permission is the same one that gates
     * filing it in the first place. (Admins pass via the Gate::before shortcut.)
     */
    public function submit(User $user, ShareApplication $application): bool
    {
        if ($application->applicant?->user_id === $user->id) {
            return $user->can('application.submit');
        }

        return $user->can('application.verify');
    }
}
