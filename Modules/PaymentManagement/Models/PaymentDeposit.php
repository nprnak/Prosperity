<?php

namespace Modules\PaymentManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\ApplicationManagement\Models\ShareApplicationVoucher;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One bank transfer behind a receipt.
 *
 * The receipt acknowledges the total; these are the deposits it is made of,
 * each verified against its own slip.
 */
class PaymentDeposit extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'payment_transaction_id', 'share_application_voucher_id',
        'bank_name', 'reference_no', 'cheque_no', 'amount', 'payment_date',
        'verification_status', 'verified_by', 'verified_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payment_deposit')
            ->logFillable()
            ->logOnlyDirty();
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /** The applicant's declared slip this deposit was recorded from. */
    public function voucher()
    {
        return $this->belongsTo(ShareApplicationVoucher::class, 'share_application_voucher_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** The code that identifies this deposit on a statement: cheque or transfer. */
    public function reference(): ?string
    {
        return $this->cheque_no ?: $this->reference_no;
    }
}
