<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every sign-off, rejection and return across both review chains.
        // Doubles as the audit trail and as the source for the act-once rule,
        // so a person can never occupy two stages of the same cycle.
        Schema::create('workflow_events', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('stage');
            $table->string('action');
            $table->string('from_status');
            $table->string('to_status');

            // Required by the engine — every action must be justified.
            $table->text('remarks');

            // Which pass through the chain this action belongs to. Applicant
            // edits bump the subject's cycle, retiring earlier sign-offs.
            $table->unsignedInteger('cycle')->default(1);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'cycle']);
            $table->index(['actor_id', 'cycle']);
        });

        // An applicant edit restarts the chain; this counter is what makes
        // prior sign-offs stale rather than silently carrying over.
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedInteger('workflow_cycle')->default(1)->after('profile_status');
        });

        Schema::table('share_applications', function (Blueprint $table) {
            $table->unsignedInteger('workflow_cycle')->default(1)->after('status');
        });

        // share_applications.status was a MySQL ENUM, so every new state cost
        // an ALTER. The PHP enum is the source of truth now.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE share_applications MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('workflow_cycle');
        });

        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropColumn('workflow_cycle');
        });

        Schema::dropIfExists('workflow_events');

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE share_applications MODIFY COLUMN status ENUM('draft','submitted','sent_to_bank','bank_accepted','blocked','payment_pending','payment_verified','reviewed','verified','approved','allotted','partially_allotted','not_allotted','refund_initiated','refund_completed','demat_credited','rejected') NOT NULL DEFAULT 'draft'");
        }
    }
};
