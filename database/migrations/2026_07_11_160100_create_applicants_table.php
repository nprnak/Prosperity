<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name_nepali');
            $table->string('full_name_english');
            $table->date('date_of_birth');
            $table->unsignedInteger('age');
            $table->string('nationality')->default('Nepali');
            $table->string('father_name');
            $table->string('grandfather_name');
            $table->string('marital_status');
            $table->string('spouse_name')->nullable();
            $table->string('education')->nullable();
            $table->string('occupation')->nullable();
            $table->string('permanent_district');
            $table->string('permanent_municipality');
            $table->string('permanent_ward');
            $table->string('permanent_tole')->nullable();
            $table->string('temporary_district')->nullable();
            $table->string('temporary_municipality')->nullable();
            $table->string('temporary_ward')->nullable();
            $table->string('temporary_tole')->nullable();
            $table->string('citizenship_number')->nullable();
            $table->string('citizenship_issue_district')->nullable();
            $table->date('citizenship_issue_date')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('mobile_number');
            $table->string('email')->nullable();
            $table->enum('investment_source', ['salary', 'dividend', 'property_sale', 'house_rent', 'share_trading', 'other'])->nullable();
            $table->string('investment_source_other')->nullable();
            $table->string('share_heir_name')->nullable();
            $table->string('share_heir_relation')->nullable();
            $table->string('share_heir_mobile')->nullable();
            $table->json('work_experience')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('citizenship_doc_path')->nullable();
            $table->string('national_id_doc_path')->nullable();
            $table->string('pan_doc_path')->nullable();
            $table->boolean('declaration_accepted')->default(false);
            $table->timestamp('declaration_accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
