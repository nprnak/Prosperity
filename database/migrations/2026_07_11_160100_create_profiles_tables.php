<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->enum('applicant_type', ['individual', 'company'])->default('individual');

            $table->string('title')->nullable();
            $table->string('full_name_en');
            $table->string('full_name_np')->nullable();

            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable()->default('Nepali');
            $table->string('marital_status')->nullable();

            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('grandfather_name')->nullable();
            $table->string('spouse_name')->nullable();

            $table->string('occupation')->nullable();
            $table->string('education')->nullable();

            $table->string('mobile')->nullable();
            $table->string('email')->nullable();

            $table->string('pan_number')->nullable();
            $table->string('citizenship_number')->nullable();
            $table->string('citizenship_issued_district')->nullable();
            $table->date('citizenship_issued_date')->nullable();
            $table->string('national_id_number')->nullable();

            // MeroShare / C-ASBA details required downstream by allotment and payments.
            $table->string('boid', 16)->nullable()->unique();
            $table->string('bank_name')->nullable();
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('account_holder_name')->nullable();
            $table->boolean('asba_consent')->default(false);

            $table->boolean('declaration_accepted')->default(false);
            $table->timestamp('declaration_accepted_at')->nullable();

            $table->string('profile_status', 20)->default('incomplete');
            $table->timestamp('profile_submitted_at')->nullable();
            $table->foreignId('profile_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('profile_reviewed_at')->nullable();
            $table->text('profile_rejection_reason')->nullable();

            $table->timestamps();

            $table->index('profile_status');
        });

        Schema::create('profile_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['permanent', 'temporary']);

            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('local_level')->nullable();
            $table->string('ward_no')->nullable();
            $table->string('tole')->nullable();
            $table->string('street')->nullable();

            $table->timestamps();

            $table->unique(['profile_id', 'type']);
        });

        Schema::create('profile_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->string('file_path');

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->unique(['profile_id', 'document_type']);
        });

        Schema::create('profile_source_of_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            $table->string('source_type');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['profile_id', 'source_type']);
        });

        Schema::create('profile_nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            $table->string('full_name');
            $table->string('relationship');
            $table->string('mobile')->nullable();
            $table->text('address')->nullable();

            $table->timestamps();
        });

        Schema::create('profile_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            $table->string('organization_name');
            $table->text('address')->nullable();
            $table->string('position')->nullable();
            $table->decimal('years', 5, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_experiences');
        Schema::dropIfExists('profile_nominees');
        Schema::dropIfExists('profile_source_of_funds');
        Schema::dropIfExists('profile_documents');
        Schema::dropIfExists('profile_addresses');
        Schema::dropIfExists('profiles');
    }
};
