<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            // First finance sign-off; verified_by then holds the second officer's re-verification.
            $table->foreignId('checked_by')->nullable()->after('verification_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->after('checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_by');
            $table->dropColumn('checked_at');
        });
    }
};
