<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('remarks')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['share_application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
    }
};
