<?php

namespace App\Observers;

use App\Models\Applicant;
use App\Models\User;

class UserObserver
{
    public function updated(User $user): void
    {
        if (! $user->wasChanged(['name', 'email'])) {
            return;
        }

        Applicant::query()
            ->where('user_id', $user->id)
            ->update([
                'full_name_english' => $user->name,
                'email' => $user->email,
            ]);
    }
}
