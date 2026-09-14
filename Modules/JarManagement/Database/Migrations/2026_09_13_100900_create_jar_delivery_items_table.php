<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jar_delivery_id')->constrained('jar_deliveries')->cascadeOnDelete();
            $table->foreignId('jar_id')->constrained('jars')->restrictOnDelete();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['jar_delivery_id', 'jar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_delivery_items');
    }
};
