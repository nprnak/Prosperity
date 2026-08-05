<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The receipt's "Holding ID No." carries the applicant's BOID — their demat
 * holding account, and the only unique identifier a profile has — so the ID
 * type recorded against a payment has to be able to say so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->enum('id_type', ['citizenship', 'national_id', 'pan', 'boid'])
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->enum('id_type', ['citizenship', 'national_id', 'pan'])
                ->nullable()
                ->change();
        });
    }
};
