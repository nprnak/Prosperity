<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('type', 20)->default('household');
            $table->text('address')->nullable();
            $table->string('route_area', 100)->nullable();
            $table->decimal('default_price', 10, 2)->nullable();
            // Denormalized running balance of empty jars this customer owes
            // back, updated transactionally by DeliveryService under a row
            // lock — read on nearly every delivery screen, so it isn't worth
            // recomputing from jar_deliveries history on every load.
            $table->unsignedInteger('jars_outstanding')->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_customers');
    }
};
