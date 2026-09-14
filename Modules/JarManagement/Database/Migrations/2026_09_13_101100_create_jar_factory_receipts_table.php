<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_factory_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jar_vehicle_lot_id')->constrained('jar_vehicle_lots')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->unsignedInteger('total_expected')->default(0);
            $table->unsignedInteger('total_scanned')->default(0);
            $table->unsignedInteger('total_accepted')->default(0);
            $table->unsignedInteger('total_quarantined')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('jar_vehicle_lot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_factory_receipts');
    }
};
