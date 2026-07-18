<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            $table->string('payment_type')->nullable()->after('bank_voucher_image');
            $table->string('payment_deposited_bank')->nullable()->after('payment_type');
            $table->string('payment_deposited_ref_no')->nullable()->after('payment_deposited_bank');
            $table->boolean('declaration_accepted')->default(false)->after('payment_deposited_ref_no');
        });
    }

    public function down(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropColumn([
                'payment_type',
                'payment_deposited_bank',
                'payment_deposited_ref_no',
                'declaration_accepted',
            ]);
        });
    }
};
