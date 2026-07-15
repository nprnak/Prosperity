<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            // Nullable: applications created before multi-company support have no offering.
            $table->foreignId('share_offering_id')->nullable()->after('applicant_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('share_offering_id');
        });
    }
};
