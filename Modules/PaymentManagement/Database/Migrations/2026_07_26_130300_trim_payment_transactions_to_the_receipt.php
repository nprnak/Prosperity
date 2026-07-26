<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment transaction is now one receipt, so the per-deposit facts move off
 * it onto payment_deposits, and the receipt number is no longer claimed until
 * a receipt is actually issued.
 *
 * The number mattered: generateReceiptNumber() used to run once per declared
 * deposit at submission, so a two-deposit application burned two numbers
 * before anyone had verified a rupee, and burned them again on applications
 * that were later rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('receipt_number')->nullable()->change();
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'payment_reference_no', 'cheque_no', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('payment_mode');
            $table->string('payment_reference_no')->nullable()->after('bank_name');
            $table->string('cheque_no')->nullable()->after('payment_reference_no');
            $table->date('payment_date')->nullable()->after('cheque_no');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('receipt_number')->nullable(false)->change();
        });
    }
};
