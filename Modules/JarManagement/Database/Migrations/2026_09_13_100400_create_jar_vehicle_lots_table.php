<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_vehicle_lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_code', 40)->unique();
            $table->foreignId('jar_vehicle_id')->constrained('jar_vehicles')->restrictOnDelete();
            $table->foreignId('jar_driver_id')->nullable()->constrained('jar_drivers')->nullOnDelete();
            // The app user (Delivery/Field Staff) responsible for this
            // lot's scans — distinct from the driver, who typically has no
            // system login. Policies restrict field-staff actions to lots
            // where this matches auth()->id().
            $table->foreignId('assigned_staff_id')->constrained('users')->restrictOnDelete();
            $table->string('route_area', 100)->nullable();
            $table->string('status', 20)->default('loading');
            $table->unsignedSmallInteger('max_jars')->default(80);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_vehicle_lots');
    }
};
