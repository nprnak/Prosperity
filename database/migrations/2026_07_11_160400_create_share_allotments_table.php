<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('share_allotments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('shares_allotted');
            $table->date('allotment_date');
            $table->string('demat_account_no')->nullable();
            $table->string('dp_id')->nullable();
            $table->string('client_id')->nullable();
            $table->string('certificate_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_allotments');
    }
};
