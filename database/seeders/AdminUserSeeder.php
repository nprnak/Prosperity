<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        // Keep a single seeded user per administrative role to simplify testing
        $users = [
            ['email' => 'superadmin@prosperity.com', 'name' => 'Super Admin', 'roles' => ['super_admin']],
            ['email' => 'profile.verifier@prosperity.com', 'name' => 'Profile Verifier', 'roles' => ['profile_verifier']],
            ['email' => 'profile.reviewer@prosperity.com', 'name' => 'Profile Reviewer', 'roles' => ['profile_reviewer']],
            ['email' => 'profile.approver@prosperity.com', 'name' => 'Profile Approver', 'roles' => ['profile_approver']],
            ['email' => 'application.verifier@prosperity.com', 'name' => 'Application Verifier', 'roles' => ['application_verifier']],
            ['email' => 'application.reviewer@prosperity.com', 'name' => 'Application Reviewer', 'roles' => ['application_reviewer']],
            ['email' => 'application.approver@prosperity.com', 'name' => 'Application Approver', 'roles' => ['application_approver']],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => $password, 'email_verified_at' => now()]
            );

            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->syncRoles($u['roles']);
            $this->command->info("✓ Created user: {$user->email} roles: ".implode(',', $u['roles']));
        }

        $this->command->info("\n✓ Seeded core admin users.");
        $this->command->info("Default password for seeded users: password\n");
    }
}
