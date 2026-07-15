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

        // Admin Users
        $admins = [
            ['email' => 'admin1@prosperity.com', 'name' => 'Admin One'],
            ['email' => 'admin2@prosperity.com', 'name' => 'Admin Two'],
        ];

        foreach ($admins as $admin) {
            $user = User::firstOrCreate(
                ['email' => $admin['email']],
                ['name' => $admin['name'], 'password' => $password, 'email_verified_at' => now()]
            );
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $user->syncRoles(['admin']);
            $this->command->info("✓ Admin user created: {$user->email}");
        }

        // Finance Staff Users
        $finance = [
            ['email' => 'finance1@prosperity.com', 'name' => 'Finance Officer One'],
            ['email' => 'finance2@prosperity.com', 'name' => 'Finance Officer Two'],
        ];

        foreach ($finance as $staff) {
            $user = User::firstOrCreate(
                ['email' => $staff['email']],
                ['name' => $staff['name'], 'password' => $password, 'email_verified_at' => now()]
            );
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $user->syncRoles(['finance_staff']);
            $this->command->info("✓ Finance staff user created: {$user->email}");
        }

        // Approver Users
        $approvers = [
            ['email' => 'approver1@prosperity.com', 'name' => 'Approver One'],
            ['email' => 'approver2@prosperity.com', 'name' => 'Approver Two'],
        ];

        foreach ($approvers as $approver) {
            $user = User::firstOrCreate(
                ['email' => $approver['email']],
                ['name' => $approver['name'], 'password' => $password, 'email_verified_at' => now()]
            );
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $user->syncRoles(['approver']);
            $this->command->info("✓ Approver user created: {$user->email}");
        }

        // Applicant Users
        $applicants = [
            ['email' => 'applicant1@prosperity.com', 'name' => 'Raaj Sharma'],
            ['email' => 'applicant2@prosperity.com', 'name' => 'Priya Poudel'],
            ['email' => 'applicant3@prosperity.com', 'name' => 'Amit Nair'],
            ['email' => 'applicant4@prosperity.com', 'name' => 'Deepa Khanal'],
            ['email' => 'applicant5@prosperity.com', 'name' => 'Vikram Singh'],
        ];

        foreach ($applicants as $applicant) {
            $user = User::firstOrCreate(
                ['email' => $applicant['email']],
                ['name' => $applicant['name'], 'password' => $password, 'email_verified_at' => now()]
            );
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $user->syncRoles(['applicant']);
            $this->command->info("✓ Applicant user created: {$user->email}");
        }

        $this->command->info("\n✓ All users created successfully!");
        $this->command->info("Default password for all users: password\n");
    }
}
