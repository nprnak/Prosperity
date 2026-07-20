<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\SettingsManagement\Models\Setting;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

class SettingsConsumptionTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_upload_size_limit_comes_from_settings(): void
    {
        Storage::fake('private');
        Setting::set('max_upload_size_kb', '100', 'application');

        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)->post('/admin/payment-methods', [
            'name' => 'eSewa',
            'status' => 'active',
            'qr_image' => UploadedFile::fake()->image('qr.png')->size(500),
        ])->assertSessionHasErrors('qr_image');

        $this->actingAs($admin)->post('/admin/payment-methods', [
            'name' => 'eSewa',
            'status' => 'active',
            'qr_image' => UploadedFile::fake()->image('qr.png')->size(50),
        ])->assertSessionHasNoErrors();
    }

    public function test_application_cap_from_settings_blocks_submission(): void
    {
        Setting::set('max_applications_per_user', '1', 'application');

        $user = User::factory()->create()->assignRole('applicant');
        $applicant = $this->approvedApplicant($user);

        // an already-submitted application fills the quota of one
        $this->application($applicant, ApplicationStatus::Submitted, 'APP-1');
        $draft = $this->application($applicant, ApplicationStatus::Draft, 'DRAFT-000001');

        $this->actingAs($user)
            ->post("/applications/{$draft->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasErrors('profile');

        $this->assertSame(ApplicationStatus::Draft, $draft->fresh()->status);

        // raising the cap allows the submission through
        Setting::set('max_applications_per_user', '5', 'application');

        $this->actingAs($user)
            ->post("/applications/{$draft->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::Submitted, $draft->fresh()->status);
    }

    protected function approvedApplicant(User $user): Profile
    {
        $applicant = $this->minimalProfile($user);

        // profile_status is intentionally not mass-assignable
        $applicant->forceFill(['profile_status' => ProfileStatus::Approved])->save();

        return $applicant;
    }

    protected function application(Profile $applicant, ApplicationStatus $status, string $number): ShareApplication
    {
        return ShareApplication::create([
            'applicant_id' => $applicant->id,
            'application_number' => $number,
            'status' => $status,
            'shares_applied' => 10,
            'amount_per_share' => '100.00',
            'total_amount_declared' => '1000.00',
        ]);
    }
}
