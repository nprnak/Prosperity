<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('fiscal_year', 20);
            $table->unsignedBigInteger('total_shares');
            $table->decimal('share_rate', 14, 2);
            $table->unsignedInteger('min_shares')->default(1);
            $table->unsignedInteger('max_shares');
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'opens_at', 'closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_offerings');
    }
};
