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
            $table->string('issue_code')->nullable()->after('application_number');
            $table->string('asba_reference')->nullable()->after('status');
            $table->decimal('blocked_amount', 14, 2)->nullable()->after('asba_reference');
            $table->timestamp('blocked_at')->nullable()->after('blocked_amount');
            $table->decimal('refunded_amount', 14, 2)->nullable()->after('blocked_at');
            $table->timestamp('refunded_at')->nullable()->after('refunded_amount');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE share_applications MODIFY COLUMN status ENUM('draft','submitted','sent_to_bank','bank_accepted','blocked','payment_pending','payment_verified','approved','allotted','partially_allotted','not_allotted','refund_initiated','refund_completed','demat_credited','rejected') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE share_applications MODIFY COLUMN status ENUM('draft','submitted','payment_pending','payment_verified','approved','allotted','rejected') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropColumn([
                'issue_code',
                'asba_reference',
                'blocked_amount',
                'blocked_at',
                'refunded_amount',
                'refunded_at',
            ]);
        });
    }
};
