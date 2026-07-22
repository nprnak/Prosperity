<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An applicant may pay in several deposits, so the voucher image and the
     * transaction code that identifies it move onto their own rows — together,
     * since a code without its slip (or the reverse) can't be verified.
     * The paying bank, payment type, amount and ASBA reference travel with each
     * deposit too, all of them being facts about one bank's transfer.
     */
    public function up(): void
    {
        Schema::create('share_application_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_application_id')->constrained()->cascadeOnDelete();

            $table->string('payment_type')->nullable();
            $table->string('deposited_bank')->nullable();
            $table->string('transaction_code')->nullable();
            $table->string('asba_reference')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('amount', 15, 2)->nullable();

            $table->timestamps();

            $table->index('share_application_id');
        });

        Schema::table('share_applications', function (Blueprint $table) {
            $table->dropColumn([
                'asba_reference',
                'bank_voucher_image',
                'payment_type',
                'payment_deposited_bank',
                'payment_deposited_ref_no',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('share_applications', function (Blueprint $table) {
            $table->string('asba_reference')->nullable()->after('status');
            $table->string('bank_voucher_image')->nullable()->after('asba_reference');
            $table->string('payment_type')->nullable()->after('bank_voucher_image');
            $table->string('payment_deposited_bank')->nullable()->after('payment_type');
            $table->string('payment_deposited_ref_no')->nullable()->after('payment_deposited_bank');
        });

        Schema::dropIfExists('share_application_vouchers');
    }
};
