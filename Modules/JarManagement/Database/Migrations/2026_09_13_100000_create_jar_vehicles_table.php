<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_number', 30)->unique();
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('max_jars')->default(80);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_vehicles');
    }
};
