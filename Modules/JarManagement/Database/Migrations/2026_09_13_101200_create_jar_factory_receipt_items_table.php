<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_factory_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jar_factory_receipt_id')->constrained('jar_factory_receipts')->cascadeOnDelete();
            $table->foreignId('jar_id')->constrained('jars')->restrictOnDelete();
            $table->string('decision', 20);
            $table->string('quarantine_reason', 30)->nullable();
            $table->boolean('matched_to_vehicle')->default(true);
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['jar_factory_receipt_id', 'jar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_factory_receipt_items');
    }
};
