<?php

namespace App\Observers;

use Modules\ApplicantManagement\Models\Profile;
use App\Models\User;

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
