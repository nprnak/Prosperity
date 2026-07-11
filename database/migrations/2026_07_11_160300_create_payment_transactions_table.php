<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_application_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 14, 2);
            $table->enum('payment_mode', ['cheque', 'self_cheque_deposit', 'online_transfer', 'cash', 'ips', 'mobile_banking']);
            $table->string('bank_name')->nullable();
            $table->string('payment_reference_no')->nullable();
            $table->string('cheque_no')->nullable();
            $table->date('payment_date');
            $table->string('holding_id_no')->nullable();
            $table->enum('id_type', ['citizenship', 'national_id', 'pan'])->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
