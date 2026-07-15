<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SettingsManagement\Database\Seeders\SettingsSeeder;
use Modules\SettingsManagement\Models\Setting;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'org_name' => 'Prosperity',
            'org_address' => 'Kathmandu, Nepal',
            'contact_email' => 'contact@prosperity.com',
            'support_phone' => '',
            'mail_host' => '',
            'mail_port' => 587,
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@prosperity.com',
            'mail_from_name' => 'Prosperity',
            'currency_code' => 'NPR',
            'currency_symbol' => 'रु',
            'max_upload_size_kb' => 2048,
            'max_applications_per_user' => 5,
        ], $overrides);
    }

    public function test_only_admins_access_site_settings(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');
        $this->actingAs($applicant)->get('/admin/settings')->assertForbidden();
        $this->actingAs($applicant)->put('/admin/settings', $this->validPayload())->assertForbidden();

        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
    }

    public function test_admin_can_update_settings_and_change_is_audit_logged(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->put('/admin/settings', $this->validPayload([
                'org_name' => 'Prosperity Holdings Ltd.',
                'currency_code' => 'USD',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Prosperity Holdings Ltd.', Setting::get('org_name'));
        $this->assertSame('USD', Setting::get('currency_code'));

        $log = Activity::where('log_name', 'settings')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertContains('org_name', $log->properties['changed']);
        $this->assertArrayHasKey('ip', $log->properties->toArray());
        $this->assertArrayHasKey('user_agent', $log->properties->toArray());
    }

    public function test_validation_rejects_bad_values(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->put('/admin/settings', $this->validPayload([
                'contact_email' => 'not-an-email',
                'currency_code' => 'NEPALESE',
                'max_upload_size_kb' => 999999,
            ]))
            ->assertSessionHasErrors(['contact_email', 'currency_code', 'max_upload_size_kb']);
    }

    public function test_blank_mail_password_keeps_existing_value(): void
    {
        Setting::set('mail_password', 'secret-existing', 'mail');
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->put('/admin/settings', $this->validPayload(['mail_password' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame('secret-existing', Setting::get('mail_password'));

        $this->actingAs($admin)
            ->put('/admin/settings', $this->validPayload(['mail_password' => 'new-secret']))
            ->assertSessionHasNoErrors();

        $this->assertSame('new-secret', Setting::get('mail_password'));
    }

    public function test_mail_password_is_never_sent_to_the_page(): void
    {
        Setting::set('mail_password', 'secret-existing', 'mail');
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)->get('/admin/settings')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings', false)
                ->where('settings.mail.mail_password', '')
            );
    }
}
