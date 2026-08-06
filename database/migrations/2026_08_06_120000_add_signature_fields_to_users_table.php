<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_name')->nullable()->after('email');
            $table->string('signature_designation')->nullable()->after('signature_name');
            $table->string('signature_path')->nullable()->after('signature_designation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['signature_name', 'signature_designation', 'signature_path']);
        });
    }
};
