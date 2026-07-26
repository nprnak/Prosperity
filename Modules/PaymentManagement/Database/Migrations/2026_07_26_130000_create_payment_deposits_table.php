<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance's verified record of each bank transfer behind a receipt.
 *
 * An applicant may pay their share capital in several deposits — the paper
 * receipt acknowledges them together, listing every reference and date under
 * one receipt number. Modelling each deposit as its own payment transaction
 * meant one receipt covered one deposit and quietly ignored the rest, and it
 * burned a receipt number per deposit at submission. So the transaction
 * becomes the receipt, and the deposits hang beneath it.
 *
 * Verification stays per deposit: finance checks each slip against its own
 * bank record, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();

            // Where this deposit came from, when it was declared by the
            // applicant. Null for one finance recorded directly.
            $table->foreignId('share_application_voucher_id')->nullable()
                ->constrained('share_application_vouchers')->nullOnDelete();

            $table->string('bank_name')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('cheque_no')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date')->nullable();

            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['payment_transaction_id', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_deposits');
    }
};
