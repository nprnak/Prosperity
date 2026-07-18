<?php

namespace Modules\CompanyManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CompanyManagement\Models\Company;
use Modules\CompanyManagement\Models\ShareOffering;

class CompanySeeder extends Seeder
{
    /**
     * Seeds Prosperity Holdings itself as the first issuer with an open
     * offering, so the applicant wizard works out of the box.
     */
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['code' => 'PHL'],
            [
                'name' => 'Prosperity Holdings Limited',
                'description' => 'Prosperity Holdings Limited public share issue.',
                'status' => Company::STATUS_ACTIVE,
            ]
        );

        // Backfill the print-form details on installs seeded before these columns existed.
        $company->fill([
            'name_np' => $company->name_np ?? 'प्रोस्पेरिटी होल्डिङ्स लिमिटेड',
            'address' => $company->address ?? 'Kathmandu Metropolitan City - 11, Kathmandu',
            'address_np' => $company->address_np ?? 'का.म.न.पा.- ११, काठमाडौँ।',
            'bank_name' => $company->bank_name ?? 'Nepal Bank Limited',
            'bank_account_number' => $company->bank_account_number ?? '01234567890123',
        ])->save();

        ShareOffering::firstOrCreate(
            ['company_id' => $company->id, 'fiscal_year' => '2082/83'],
            [
                'title' => 'Founder Share Issue',
                'total_shares' => 1000000,
                'share_rate' => '100.00',
                'min_shares' => 10,
                'max_shares' => 10000,
                'opens_at' => now()->subMonth()->toDateString(),
                'closes_at' => now()->addMonths(3)->toDateString(),
                'status' => ShareOffering::STATUS_OPEN,
            ]
        );
    }
}
