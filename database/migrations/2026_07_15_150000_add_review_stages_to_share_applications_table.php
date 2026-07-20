<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            $table->foreignId('verified_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->foreignId('approved_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE share_applications MODIFY COLUMN status ENUM('draft','submitted','sent_to_bank','bank_accepted','blocked','payment_pending','payment_verified','reviewed','verified','approved','allotted','partially_allotted','not_allotted','refund_initiated','refund_completed','demat_credited','rejected') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE share_applications MODIFY COLUMN status ENUM('draft','submitted','sent_to_bank','bank_accepted','blocked','payment_pending','payment_verified','approved','allotted','partially_allotted','not_allotted','refund_initiated','refund_completed','demat_credited','rejected') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['verified_at', 'approved_at']);
        });
    }
};
