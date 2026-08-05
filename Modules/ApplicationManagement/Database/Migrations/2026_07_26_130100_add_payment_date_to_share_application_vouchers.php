<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the deposit was actually made.
 *
 * The wizard already collects a bank, a transaction code, an amount and a slip
 * per deposit, but never a date — so the receipt's "Date of Payment" could not
 * be reconstructed from anything the application stored, and finance's form
 * defaulted it to today instead of the day the money moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('share_application_vouchers', function (Blueprint $table) {
            $table->date('payment_date')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('share_application_vouchers', function (Blueprint $table) {
            $table->dropColumn('payment_date');
        });
    }
};
