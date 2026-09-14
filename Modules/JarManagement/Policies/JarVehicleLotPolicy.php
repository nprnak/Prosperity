<?php

namespace Modules\JarManagement\Policies;

use App\Models\User;
use Modules\JarManagement\Models\JarVehicleLot;

class JarVehicleLotPolicy
{
    /** Anyone with dispatch oversight, or the field staff this lot is assigned to. */
    public function view(User $user, JarVehicleLot $lot): bool
    {
        return $user->can('jar.dispatch.view')
            || $user->can('jar.dispatch.manage')
            || $lot->assigned_staff_id === $user->id;
    }

    /** Loading/dispatching/receiving a lot is dispatch-management territory. */
    public function manage(User $user, JarVehicleLot $lot): bool
    {
        return $user->can('jar.dispatch.manage');
    }

    /**
     * Field staff act (deliver, collect returns) only on the lot assigned to
     * them; managers with jar.dispatch.manage may act on any lot.
     */
    public function act(User $user, JarVehicleLot $lot): bool
    {
        if ($user->can('jar.dispatch.manage')) {
            return true;
        }

        return $user->can('jar.delivery.manage') && $lot->assigned_staff_id === $user->id;
    }
}
