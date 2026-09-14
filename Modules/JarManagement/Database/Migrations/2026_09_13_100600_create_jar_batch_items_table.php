<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jar_production_batch_id')->constrained('jar_production_batches')->cascadeOnDelete();
            $table->foreignId('jar_id')->constrained('jars')->restrictOnDelete();
            $table->string('quality_result', 20)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['jar_production_batch_id', 'jar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_batch_items');
    }
};
