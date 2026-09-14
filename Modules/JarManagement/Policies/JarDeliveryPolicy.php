<?php

namespace Modules\JarManagement\Policies;

use App\Models\User;
use Modules\JarManagement\Models\JarDelivery;

class JarDeliveryPolicy
{
    /** Own transactions (delivering staff), or anyone with broader visibility. */
    public function view(User $user, JarDelivery $delivery): bool
    {
        return $delivery->staff_id === $user->id
            || $user->can('jar.dispatch.view')
            || $user->can('jar.dispatch.manage')
            || $user->can('jar.sales.view');
    }
}
