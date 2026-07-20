<?php

namespace App\Observers;

use App\Models\User;
use Modules\ApplicantManagement\Models\Profile;

class UserObserver
{
    public function updated(User $user): void
    {
        if (! $user->wasChanged(['name', 'email'])) {
            return;
        }

        Profile::query()
            ->where('user_id', $user->id)
            ->update([
                'full_name_en' => $user->name,
                'email' => $user->email,
            ]);
    }
}
