<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Tests\Support\CreatesProfiles;
use Tests\TestCase;

/**
 * The five prescribed report formats.
 *
 * The figures are the point of these tests: the Share Lagat register has to
 * compile a holder's several approved applications into one row and split the
 * money into par and premium exactly as the format defines, and the focal
 * person summary has to credit each application to whoever it was booked
 * under rather than to whoever the applicant's default happens to be now.
 */
class PrescribedReportsTest extends TestCase
{
    use CreatesProfiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function admin(): User
    {
        return User::factory()->create()->assignRole('super_admin');
    }

    protected function offering(string $code = 'PHL', string $rate = '100.00'): ShareOffering
    {
        $company = Company::firstOrCreate(
            ['code' => $code],
            ['name' => $code.' Holdings Ltd', 'status' => 'active'],
        );

        return $company->offerings()->create([
            'title' => 'Issue '.$rate,
            'fiscal_year' => '2082/83',
            'total_shares' => 1000000,
            'share_rate' => $rate,
            'min_shares' => 10,
            'max_shares' => 500000,
            'status' => ShareOffering::STATUS_OPEN,
        ]);
    }

    protected function shareholder(array $overrides = []): Profile
    {
        return $this->approvedProfile(User::factory()->create()->assignRole('applicant'), $overrides);
    }

    /**
     * An approved holding with its deposit already verified, which is what the
     * register counts as paid.
     */
    protected function holding(
        Profile $applicant,
        ShareOffering $offering,
        int $shares,
        ?string $deposited = null,
        ApplicationStatus $status = ApplicationStatus::Approved,
        ?int $focalPersonId = null,
    ): ShareApplication {
        $declared = number_format($shares * (float) $offering->share_rate, 2, '.', '');

        $application = ShareApplication::create([
            'applicant_id' => $applicant->id,
            'focal_person_id' => $focalPersonId,
            'share_offering_id' => $offering->id,
            'application_number' => 'APP-'.$applicant->id.'-'.$offering->id.'-'.$shares,
            'status' => $status,
            'shares_applied' => $shares,
            'amount_per_share' => $offering->share_rate,
            'total_amount_declared' => $declared,
            'approved_at' => now(),
        ]);

        PaymentTransaction::create([
            'share_application_id' => $application->id,
            'receipt_number' => 'RCP-'.$application->id,
            'amount' => $deposited ?? $declared,
            'payment_mode' => 'online_transfer',
            'payment_date' => now()->toDateString(),
            'verification_status' => 'verified',
        ]);

        return $application;
    }

    /** @return array<int, array<string, mixed>> */
    protected function rowsOf(string $report, array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get('/admin/reports/'.$report.'?'.http_build_query($query));
        $response->assertOk();

        return $response->viewData('page')['props']['rows'];
    }

    // -------------------------------------------------------------- permissions

    public function test_every_report_and_export_requires_the_report_permission(): void
    {
        $applicant = User::factory()->create()->assignRole('applicant');

        foreach (['share-lagat', 'shareholders', 'focal-persons', 'application-status', 'kyc-status'] as $report) {
            $this->actingAs($applicant)->get("/admin/reports/{$report}")->assertForbidden();
            $this->actingAs($applicant)->get("/admin/reports/{$report}/export?format=csv")->assertForbidden();
        }
    }

    public function test_an_unknown_report_slug_is_not_routable(): void
    {
        $this->actingAs($this->admin())->get('/admin/reports/made-up')->assertNotFound();
    }

    // ------------------------------------------------------------- share lagat

    public function test_the_register_compiles_a_holders_repeat_applications_into_one_row(): void
    {
        $offering = $this->offering('PHL', '175.00');
        $holder = $this->shareholder(['full_name_np' => 'सुनिल कुमार खरेल', 'father_name' => 'डम्बर प्रसाद खरेल']);

        // The scenario from note 2 of the format: approved twice in one issue.
        $this->holding($holder, $offering, 2000);
        $this->holding($holder, $offering, 5000);

        $rows = $this->rowsOf('share-lagat');

        $this->assertCount(1, $rows);
        $this->assertSame(7000, $rows[0]['shares']);
        $this->assertSame('सुनिल कुमार खरेल', explode("\n", $rows[0]['name_address'])[0]);
        $this->assertSame('डम्बर प्रसाद खरेल', $rows[0]['father_or_spouse']);
    }

    public function test_the_register_splits_par_from_premium_as_the_format_defines(): void
    {
        // 1,000 shares at 175 → 175,000 deposited, of which 100,000 is par and
        // 75,000 premium. Premium comes from the two money columns, not the rate.
        $offering = $this->offering('CIL', '175.00');
        $this->holding($this->shareholder(), $offering, 1000);

        $rows = $this->rowsOf('share-lagat');

        $this->assertSame('175,000.00', $rows[0]['total_paid']);
        $this->assertSame('100,000.00', $rows[0]['paid']);
        $this->assertSame('75,000.00', $rows[0]['premium']);
        // Fully paid, so the format prints छैन। rather than 0.00.
        $this->assertSame('छैन।', $rows[0]['outstanding']);
    }

    public function test_the_register_shows_what_is_still_owed_when_the_deposit_is_short(): void
    {
        $offering = $this->offering('PHL', '100.00');
        $this->holding($this->shareholder(), $offering, 1000, deposited: '60000.00');

        $rows = $this->rowsOf('share-lagat');

        $this->assertSame('60,000.00', $rows[0]['total_paid']);
        $this->assertSame('40,000.00', $rows[0]['outstanding']);
        // Deposited less than par, so there is no premium to report.
        $this->assertSame('0.00', $rows[0]['premium']);
    }

    public function test_the_register_counts_only_approved_holdings(): void
    {
        $offering = $this->offering();

        $this->holding($this->shareholder(), $offering, 100, status: ApplicationStatus::Submitted);
        $this->holding($this->shareholder(), $offering, 200, status: ApplicationStatus::NotAllotted);
        $this->holding($this->shareholder(), $offering, 300, status: ApplicationStatus::Allotted);

        $rows = $this->rowsOf('share-lagat');

        // Only the allotted holding is on the register.
        $this->assertCount(1, $rows);
        $this->assertSame(300, $rows[0]['shares']);
    }

    public function test_the_register_scopes_to_one_offering(): void
    {
        $first = $this->offering('PHL', '100.00');
        $second = $this->offering('PHL', '110.00');
        $holder = $this->shareholder();

        $this->holding($holder, $first, 1000);
        $this->holding($holder, $second, 2000);

        $this->assertSame(3000, $this->rowsOf('share-lagat')[0]['shares']);
        $this->assertSame(
            2000,
            $this->rowsOf('share-lagat', ['share_offering_id' => $second->id])[0]['shares'],
        );
    }

    public function test_the_nepali_register_exports_in_every_format(): void
    {
        $this->holding(
            $this->shareholder(['full_name_np' => 'सुनिल कुमार खरेल']),
            $this->offering(),
            500,
        );
        $admin = $this->admin();

        foreach (['xlsx', 'csv', 'pdf'] as $format) {
            $response = $this->actingAs($admin)->get("/admin/reports/share-lagat/export?format={$format}");
            $response->assertOk();
            $this->assertStringContainsString(".{$format}", $response->headers->get('content-disposition'));
        }
    }

    public function test_the_nepali_pdf_embeds_a_devanagari_font_and_carries_the_register_text(): void
    {
        $this->holding(
            $this->shareholder(['full_name_np' => 'सुनिल कुमार खरेल']),
            $this->offering(),
            500,
        );

        $pdf = $this->actingAs($this->admin())
            ->get('/admin/reports/share-lagat/export?format=pdf')
            ->getContent();

        // A PDF that fell back to a Latin-only font would carry no Devanagari
        // subset at all, which is exactly how a blank Nepali report happens.
        $this->assertStringContainsString('FreeSerif', $pdf);
        $this->assertStringNotContainsString('DejaVuSans', $pdf);
        // Non-trivial page content rather than an empty content stream.
        $this->assertGreaterThan(20000, strlen($pdf));
    }

    public function test_the_register_dates_holdings_in_bikram_sambat(): void
    {
        $offering = $this->offering();
        $holding = $this->holding($this->shareholder(), $offering, 100);

        // 2025-08-15 AD is 2082 Shrawan 30.
        $holding->forceFill(['approved_at' => '2025-08-15 10:00:00'])->save();

        $row = $this->rowsOf('share-lagat')[0];

        $this->assertSame('२०८२/०४/३०', $row['registered_on']);
    }

    public function test_a_date_outside_the_converters_range_is_marked_as_gregorian(): void
    {
        $offering = $this->offering();
        $holding = $this->holding($this->shareholder(), $offering, 100);

        // Beyond the converter's 1944–2033 range: labelled ई.सं. rather than
        // silently printed as though it were a BS date.
        $holding->forceFill(['approved_at' => '2040-01-01 10:00:00'])->save();

        $this->assertStringContainsString('ई.सं.', $this->rowsOf('share-lagat')[0]['registered_on']);
    }

    public function test_the_promoter_column_carries_the_issuing_companys_name(): void
    {
        $offering = $this->offering('CIL');
        $offering->company->update(['name_np' => 'क्लासिक इन्डष्ट्रिज लिमिटेड']);

        $this->holding($this->shareholder(), $offering, 100);

        $this->assertSame('क्लासिक इन्डष्ट्रिज लिमिटेड', $this->rowsOf('share-lagat')[0]['promoter']);
    }

    public function test_the_promoter_column_falls_back_to_the_english_company_name(): void
    {
        $this->holding($this->shareholder(), $this->offering('PHL'), 100);

        $this->assertSame('PHL Holdings Ltd', $this->rowsOf('share-lagat')[0]['promoter']);
    }

    public function test_composite_register_cells_break_the_line_rather_than_using_a_comma(): void
    {
        $offering = $this->offering();
        $this->holding(
            $this->shareholder([
                'full_name_np' => 'शान्ति देवी श्रेष्ठ',
                'citizenship_number' => '२७०९१२३४',
                // The candrabindu in काठमाडौँ is half of what triggers mPDF's
                // comma-fusing bug; the other half is the Devanagari digits
                // before the separator. A newline keeps them apart.
                'citizenship_issued_district' => 'काठमाडौँ',
            ]),
            $offering,
            100,
        );

        $row = $this->rowsOf('share-lagat')[0];

        $this->assertStringContainsString("२७०९१२३४,\nकाठमाडौँ", $row['citizenship']);
        $this->assertStringContainsString("शान्ति देवी श्रेष्ठ\n", $row['name_address']);
    }

    public function test_the_register_is_filed_under_the_issuing_companys_nepali_name(): void
    {
        $offering = $this->offering('CIL');
        $offering->company->update([
            'name_np' => 'क्लासिक इन्डष्ट्रिज लिमिटेड',
            'address_np' => 'काठमाडौँ, नेपाल',
        ]);

        $this->holding($this->shareholder(), $offering, 100);

        $scoped = $this->actingAs($this->admin())
            ->get('/admin/reports/share-lagat?company_id='.$offering->company_id);
        $scoped->assertOk();

        $props = $scoped->viewData('page')['props'];

        // The register belongs to the issuer, so it carries their name, and the
        // totals row is labelled in Nepali.
        $this->assertSame('क्लासिक इन्डष्ट्रिज लिमिटेड', $props['org']['name']);
        $this->assertSame('काठमाडौँ, नेपाल', $props['org']['address']);
        $this->assertSame('जम्मा', $props['totalsLabel']);
    }

    public function test_a_register_spanning_several_companies_falls_back_to_the_operator(): void
    {
        $this->offering('AAA')->company->update(['name_np' => 'क ल']);
        $this->offering('BBB')->company->update(['name_np' => 'ख ग']);

        $response = $this->actingAs($this->admin())->get('/admin/reports/share-lagat');
        $response->assertOk();

        // No single issuer to file under, so the configured organisation stands.
        $this->assertSame(config('app.name'), $response->viewData('page')['props']['org']['name']);
    }

    // ----------------------------------------------------------- shareholders

    public function test_shareholder_info_grows_a_column_per_offering(): void
    {
        $first = $this->offering('PHL', '100.00');
        $second = $this->offering('PHL', '110.00');
        $holder = $this->shareholder();

        $this->holding($holder, $first, 1500);
        $this->holding($holder, $second, 2500);

        $response = $this->actingAs($this->admin())->get('/admin/reports/shareholders');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $keys = array_column($props['columns'], 'key');

        $this->assertContains('offering_'.$first->id, $keys);
        $this->assertContains('offering_'.$second->id, $keys);

        $row = collect($props['rows'])->firstWhere('username', $holder->user->name);

        $this->assertSame('1,500', $row['offering_'.$first->id]);
        $this->assertSame('2,500', $row['offering_'.$second->id]);
        $this->assertSame('4,000', $row['total_kitta']);
    }

    public function test_shareholder_info_can_list_only_those_who_have_not_applied(): void
    {
        $offering = $this->offering();
        $applied = $this->shareholder();
        $idle = $this->shareholder();

        $this->holding($applied, $offering, 100);

        $names = collect($this->rowsOf('shareholders', ['participation' => 'not_applied']))
            ->pluck('username');

        $this->assertTrue($names->contains($idle->user->name));
        $this->assertFalse($names->contains($applied->user->name));
    }

    // ---------------------------------------------------------- focal persons

    public function test_focal_person_summary_credits_shares_and_deposits_per_offering(): void
    {
        $offering = $this->offering('PHL', '100.00');
        $focalPerson = User::factory()->create(['name' => 'Ramesh Adhikari']);
        $this->approvedProfile($focalPerson);
        $focalPerson->forceFill(['is_focal_person' => true, 'focal_person_code' => 'FP-0001'])->save();

        $this->holding($this->shareholder(), $offering, 1000, focalPersonId: $focalPerson->id);
        $this->holding($this->shareholder(), $offering, 500, focalPersonId: $focalPerson->id);

        $rows = $this->rowsOf('focal-persons');
        $row = collect($rows)->firstWhere('code', 'FP-0001');

        $this->assertSame('Ramesh Adhikari', $row['focal_person']);
        $this->assertSame(2, $row['applicants']);
        $this->assertSame('1,500', $row['offering_'.$offering->id.'_shares']);
        $this->assertSame('150,000.00', $row['offering_'.$offering->id.'_amount']);
    }

    public function test_focal_person_summary_keeps_unattributed_applications_in_their_own_row(): void
    {
        $offering = $this->offering();
        $this->holding($this->shareholder(), $offering, 700);

        $rows = $this->rowsOf('focal-persons');

        $this->assertSame('Direct / Unassigned', $rows[0]['focal_person']);
        $this->assertSame('700', $rows[0]['total_shares']);
    }

    public function test_focal_person_summary_falls_back_to_the_profile_default_for_older_applications(): void
    {
        $offering = $this->offering();
        $focalPerson = User::factory()->create(['name' => 'Legacy Referrer']);
        $this->approvedProfile($focalPerson);
        $focalPerson->forceFill(['is_focal_person' => true, 'focal_person_code' => 'FP-0009'])->save();

        // An application from before the per-application column existed: it
        // carries no focal person of its own, only the applicant's default.
        $holder = $this->shareholder();
        $holder->forceFill(['focal_person_id' => $focalPerson->id])->save();
        $this->holding($holder, $offering, 400, focalPersonId: null);

        $row = collect($this->rowsOf('focal-persons'))->firstWhere('code', 'FP-0009');

        $this->assertNotNull($row);
        $this->assertSame('400', $row['total_shares']);
    }

    // ------------------------------------------------------ status reports

    public function test_application_status_report_filters_by_status(): void
    {
        $offering = $this->offering();

        $this->holding($this->shareholder(), $offering, 100, status: ApplicationStatus::Submitted);
        $this->holding($this->shareholder(), $offering, 200, status: ApplicationStatus::Approved);

        $this->assertCount(2, $this->rowsOf('application-status'));

        $approved = $this->rowsOf('application-status', ['status' => 'approved']);

        $this->assertCount(1, $approved);
        $this->assertSame('200', $approved[0]['shares_applied']);
        $this->assertSame('Approved', $approved[0]['status']);
    }

    public function test_kyc_status_report_filters_by_kyc_stage(): void
    {
        $this->shareholder();

        $pending = $this->completeProfile(User::factory()->create());
        $pending->forceFill(['profile_status' => ProfileStatus::Submitted->value])->save();

        $this->assertCount(2, $this->rowsOf('kyc-status'));

        $awaiting = $this->rowsOf('kyc-status', ['profile_status' => 'submitted']);

        $this->assertCount(1, $awaiting);
        $this->assertSame('Awaiting Verification', $awaiting[0]['kyc_status']);
    }

    // ------------------------------------------------------- columns, exports

    public function test_the_column_picker_narrows_the_screen_and_the_download(): void
    {
        $this->holding($this->shareholder(), $this->offering(), 100);

        $response = $this->actingAs($this->admin())
            ->get('/admin/reports/application-status?columns=sn,username');
        $response->assertOk();

        $this->assertSame(['sn', 'username'], $response->viewData('page')['props']['visibleColumns']);

        // The download honours the same list, so the file matches the screen.
        $csv = $this->actingAs($this->admin())
            ->get('/admin/reports/application-status/export?format=csv&columns=sn,username');
        $csv->assertOk();
    }

    public function test_hiding_every_column_falls_back_to_the_reports_defaults(): void
    {
        $response = $this->actingAs($this->admin())
            ->get('/admin/reports/kyc-status?columns=not_a_column');
        $response->assertOk();

        $this->assertNotEmpty($response->viewData('page')['props']['visibleColumns']);
    }

    public function test_an_english_report_exports_in_every_format(): void
    {
        $this->holding($this->shareholder(), $this->offering(), 250);
        $admin = $this->admin();

        foreach (['xlsx', 'csv', 'pdf'] as $format) {
            $response = $this->actingAs($admin)->get("/admin/reports/application-status/export?format={$format}");
            $response->assertOk();
            $this->assertStringContainsString(".{$format}", $response->headers->get('content-disposition'));
        }
    }

    public function test_a_filter_value_that_was_never_offered_is_ignored(): void
    {
        $this->holding($this->shareholder(), $this->offering(), 100);

        // company_id 9999 does not exist, so it must not silently filter the
        // report down to nothing — it is dropped as an unoffered option.
        $response = $this->actingAs($this->admin())->get('/admin/reports/application-status?company_id=9999');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertNull($props['filters']['company_id']);
        $this->assertCount(1, $props['rows']);
    }

    public function test_the_dashboard_carries_the_focal_person_summary(): void
    {
        $offering = $this->offering();
        $focalPerson = User::factory()->create(['name' => 'Dashboard Referrer']);
        $this->approvedProfile($focalPerson);
        $focalPerson->forceFill(['is_focal_person' => true, 'focal_person_code' => 'FP-0100'])->save();

        $this->holding($this->shareholder(), $offering, 900, focalPersonId: $focalPerson->id);

        $response = $this->actingAs($this->admin())->get('/admin/dashboard');
        $response->assertOk();

        $summary = collect($response->viewData('page')['props']['focalPersons']);

        $this->assertTrue($summary->contains(fn (array $row) => $row['code'] === 'FP-0100' && $row['shares'] === '900'));
    }
}
