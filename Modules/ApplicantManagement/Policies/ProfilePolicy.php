<?php

namespace Modules\ApplicantManagement\Policies;

use App\Enums\WorkflowStage;
use App\Models\User;
use Modules\ApplicantManagement\Models\Profile;

class ProfilePolicy
{
    /**
     * Who may read a KYC profile in full — the applicant it belongs to, or
     * anyone holding a stage in the review chain.
     *
     * Stage holders are granted the whole chain rather than only the stage a
     * profile currently sits at: a reviewer needs to open records they have
     * already verified, and the queue's act-once rule is a separate control
     * enforced by WorkflowService when they try to *act*.
     */
    public function view(User $user, Profile $profile): bool
    {
        if ($profile->user_id === $user->id) {
            return true;
        }

        return $user->hasAnyPermission(
            array_map(
                fn (WorkflowStage $stage) => $stage->permission('profile'),
                WorkflowStage::cases(),
            )
        );
    }
}
