<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks who filed a record via staff paper-entry (null for the normal
     * self-service path), so a Verifier can find "profiles/applications I
     * entered" separately from the general review queue.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignId('entered_by')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('share_applications', function (Blueprint $table) {
            $table->foreignId('entered_by')->nullable()->after('applicant_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entered_by');
        });

        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entered_by');
        });
    }
};
