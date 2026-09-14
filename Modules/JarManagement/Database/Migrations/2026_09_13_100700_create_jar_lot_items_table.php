<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_lot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jar_vehicle_lot_id')->constrained('jar_vehicle_lots')->cascadeOnDelete();
            $table->foreignId('jar_id')->constrained('jars')->restrictOnDelete();
            $table->string('status', 20)->default('loaded');
            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('loaded_at')->nullable();
            $table->timestamps();

            $table->unique(['jar_vehicle_lot_id', 'jar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_lot_items');
    }
};
