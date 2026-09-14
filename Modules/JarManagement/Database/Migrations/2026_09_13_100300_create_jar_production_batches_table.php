<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 40)->unique();
            $table->date('production_date');
            $table->string('status', 20)->default('open');

            $table->timestamp('cleaning_at')->nullable();
            $table->foreignId('cleaning_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refilling_at')->nullable();
            $table->foreignId('refilling_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sealing_at')->nullable();
            $table->foreignId('sealing_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('quality_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('quality_approved_at')->nullable();
            $table->text('quality_notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_production_batches');
    }
};
