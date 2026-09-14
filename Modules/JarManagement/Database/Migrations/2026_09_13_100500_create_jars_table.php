<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jars', function (Blueprint $table) {
            $table->id();
            $table->string('jar_code', 30)->unique();
            $table->string('status', 20)->default('registered');
            $table->string('condition', 20)->default('good');

            // Denormalized "where is it right now" pointers — the
            // jar_movements ledger is the source of truth for history, these
            // three columns just make "where is this jar" a single-row
            // lookup instead of a query over the ledger.
            $table->foreignId('current_batch_id')->nullable()->constrained('jar_production_batches')->nullOnDelete();
            $table->foreignId('current_vehicle_lot_id')->nullable()->constrained('jar_vehicle_lots')->nullOnDelete();
            $table->foreignId('current_customer_id')->nullable()->constrained('jar_customers')->nullOnDelete();

            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jars');
    }
};
