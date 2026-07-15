<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Models\Applicant;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\SettingsManagement\Models\Setting;
use Tests\TestCase;

class SettingsConsumptionTest extends TestCase
{
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

        $admin = User::factory()->create()->assignRole('admin');

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
        $this->application($applicant, ShareApplication::STATUS_SUBMITTED, 'APP-1');
        $draft = $this->application($applicant, ShareApplication::STATUS_DRAFT, 'DRAFT-000001');

        $this->actingAs($user)
            ->post("/applications/{$draft->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasErrors('profile');

        $this->assertSame(ShareApplication::STATUS_DRAFT, $draft->fresh()->status);

        // raising the cap allows the submission through
        Setting::set('max_applications_per_user', '5', 'application');

        $this->actingAs($user)
            ->post("/applications/{$draft->id}/submit", ['declaration_accepted' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(ShareApplication::STATUS_SUBMITTED, $draft->fresh()->status);
    }

    protected function approvedApplicant(User $user): Applicant
    {
        $applicant = Applicant::create([
            'user_id' => $user->id,
            'full_name_nepali' => 'परीक्षण',
            'full_name_english' => 'Applicant '.$user->id,
            'date_of_birth' => '1990-01-01',
            'age' => 36,
            'father_name' => 'F',
            'grandfather_name' => 'GF',
            'marital_status' => 'single',
            'permanent_district' => 'KTM',
            'permanent_municipality' => 'KMC',
            'permanent_ward' => '1',
            'mobile_number' => '98000000'.$user->id,
        ]);

        // profile_status is intentionally not mass-assignable
        $applicant->forceFill(['profile_status' => Applicant::PROFILE_APPROVED])->save();

        return $applicant;
    }

    protected function application(Applicant $applicant, string $status, string $number): ShareApplication
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
