<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The focal person this application is credited to. Written from the
     * applicant's default when the draft is created, and quoted by code in the
     * wizard when it differs — the focal-person report groups on this column,
     * so once submitted the figure is fixed.
     */
    public function up(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            // constrained() indexes the column for us — the report groups on it.
            $table->foreignId('focal_person_id')->nullable()->after('applicant_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('focal_person_id');
        });
    }
};
