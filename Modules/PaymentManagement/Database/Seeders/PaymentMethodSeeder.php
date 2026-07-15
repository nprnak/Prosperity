<?php

namespace Modules\PaymentManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\PaymentManagement\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Bank Deposit',
                'bank_name' => 'Nepal Bank Limited',
                'account_name' => 'Prosperity Holdings Limited',
                'account_number' => '01234567890123',
                'instructions' => 'Deposit the total amount at any branch and keep the voucher number as your payment reference.',
                'sort_order' => 1,
            ],
            [
                'name' => 'ConnectIPS',
                'instructions' => 'Transfer to the Prosperity Holdings account and note the transaction ID as your payment reference.',
                'sort_order' => 2,
            ],
            [
                'name' => 'eSewa',
                'account_name' => 'Prosperity Holdings Limited',
                'instructions' => 'Scan the QR from the eSewa app and enter the total amount, then note the transaction code.',
                'sort_order' => 3,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['name' => $method['name']],
                [...$method, 'status' => PaymentMethod::STATUS_ACTIVE],
            );
        }
    }
}
