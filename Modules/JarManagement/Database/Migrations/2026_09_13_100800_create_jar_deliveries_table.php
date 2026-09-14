<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jar_vehicle_lot_id')->constrained('jar_vehicle_lots')->restrictOnDelete();
            $table->foreignId('jar_customer_id')->constrained('jar_customers')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('delivered_at');

            $table->unsignedInteger('filled_jars_delivered_count')->default(0);
            $table->decimal('price_per_jar', 10, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_status', 20)->default('unpaid');

            $table->unsignedInteger('empty_jars_collected_count')->default(0);
            $table->string('reconciliation_status', 20)->default('settled');
            $table->unsignedInteger('outstanding_jars')->default(0);
            $table->unsignedInteger('excess_jars')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_deliveries');
    }
};
