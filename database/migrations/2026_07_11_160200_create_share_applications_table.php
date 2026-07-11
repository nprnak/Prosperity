<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('share_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('application_number')->unique();
            $table->unsignedInteger('shares_applied');
            $table->decimal('amount_per_share', 12, 2)->default(100);
            $table->decimal('total_amount_declared', 14, 2);
            $table->enum('status', ['draft', 'submitted', 'payment_pending', 'payment_verified', 'approved', 'allotted', 'rejected'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_applications');
    }
};
