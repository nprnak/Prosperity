<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('boid', 16)->nullable()->after('email');
            $table->string('crn_number', 20)->nullable()->after('boid');
            $table->string('bank_name')->nullable()->after('crn_number');
            $table->string('bank_code', 20)->nullable()->after('bank_name');
            $table->string('bank_branch')->nullable()->after('bank_code');
            $table->string('bank_account_number', 50)->nullable()->after('bank_branch');
            $table->string('account_holder_name')->nullable()->after('bank_account_number');
            $table->boolean('asba_consent')->default(false)->after('account_holder_name');

            $table->unique('boid');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique(['boid']);
            $table->dropColumn([
                'boid',
                'crn_number',
                'bank_name',
                'bank_code',
                'bank_branch',
                'bank_account_number',
                'account_holder_name',
                'asba_consent',
            ]);
        });
    }
};
