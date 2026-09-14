<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jar_delivery_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jar_delivery_id')->constrained('jar_deliveries')->cascadeOnDelete();
            $table->foreignId('jar_id')->constrained('jars')->restrictOnDelete();
            $table->boolean('is_new_registration')->default(false);
            $table->string('condition', 20)->default('good');
            $table->string('verification_status', 20)->default('verified');
            $table->text('notes')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->unique(['jar_delivery_id', 'jar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jar_delivery_returns');
    }
};
