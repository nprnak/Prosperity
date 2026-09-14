<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jar_id')->constrained('jars')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();

            // Only the FK relevant to this event_type is populated — kept as
            // explicit nullable columns (rather than a polymorphic pair) so
            // every join stays a plain foreign key, matching how the rest of
            // the app models relations.
            $table->foreignId('jar_production_batch_id')->nullable()->constrained('jar_production_batches')->nullOnDelete();
            $table->foreignId('jar_vehicle_lot_id')->nullable()->constrained('jar_vehicle_lots')->nullOnDelete();
            $table->foreignId('jar_customer_id')->nullable()->constrained('jar_customers')->nullOnDelete();
            $table->foreignId('jar_delivery_id')->nullable()->constrained('jar_deliveries')->nullOnDelete();
            $table->foreignId('jar_factory_receipt_id')->nullable()->constrained('jar_factory_receipts')->nullOnDelete();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['jar_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_movements');
    }
};
