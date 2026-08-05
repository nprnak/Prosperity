<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Focal person is an attribution tag, not a role: it carries no
     * permissions, and the admin user editor syncs exactly one role per user,
     * so a Spatie role here would silently replace whatever role the person
     * already held.
     *
     * The code is what applicants quote on their application. It survives
     * un-tagging so re-tagging the same person keeps their existing code, and
     * historical assignments stay readable.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_focal_person')->default(false)->after('email_verified_at');
            $table->string('focal_person_code', 20)->nullable()->unique()->after('is_focal_person');

            $table->index('is_focal_person');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['focal_person_code']);
            $table->dropIndex(['is_focal_person']);
            $table->dropColumn(['is_focal_person', 'focal_person_code']);
        });
    }
};
