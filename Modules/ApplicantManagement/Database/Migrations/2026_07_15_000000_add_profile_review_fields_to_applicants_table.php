<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('profile_status', 20)->default('draft')->after('declaration_accepted_at');
            $table->timestamp('profile_submitted_at')->nullable()->after('profile_status');
            $table->foreignId('profile_reviewed_by')->nullable()->after('profile_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('profile_reviewed_at')->nullable()->after('profile_reviewed_by');
            $table->text('profile_rejection_reason')->nullable()->after('profile_reviewed_at');

            $table->index('profile_status');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_reviewed_by');
            $table->dropIndex(['profile_status']);
            $table->dropColumn(['profile_status', 'profile_submitted_at', 'profile_reviewed_at', 'profile_rejection_reason']);
        });
    }
};
