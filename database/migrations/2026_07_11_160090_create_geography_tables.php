<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_np')->nullable();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_np')->nullable();
            $table->timestamps();

            $table->index('province_id');
        });

        Schema::create('local_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_np')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();

            $table->index('district_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_levels');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
