<?php

namespace Modules\VoucherManagement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\PaymentManagement\Models\PaymentTransaction;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_transaction_id', 'voucher_number', 'verification_code', 'pdf_path', 'generated_by', 'generated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Voucher $voucher) {
            $voucher->verification_code ??= self::generateVerificationCode();
        });
    }

    public static function generateVerificationCode(): string
    {
        do {
            $code = Str::upper(Str::random(12));
        } while (static::withTrashed()->where('verification_code', $code)->exists());

        return $code;
    }

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
