<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            $table->string('bank_voucher_image')->nullable()->after('asba_reference');
        });
    }

    public function down(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropColumn('bank_voucher_image');
        });
    }
};
