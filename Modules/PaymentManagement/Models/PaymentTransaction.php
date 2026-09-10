<?php

namespace Modules\PaymentManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\VoucherManagement\Models\Voucher;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PaymentTransaction extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * A transaction is one receipt. The bank, reference, cheque number and
     * date are facts about an individual deposit, so they live on
     * payment_deposits — a receipt may acknowledge several.
     */
    protected $fillable = [
        'share_application_id', 'receipt_number', 'amount', 'payment_mode', 'payment_method_id',
        'holding_id_no', 'id_type', 'verification_status', 'checked_by', 'checked_at', 'verified_by', 'verified_at', 'issued_by', 'approved_by', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'checked_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /** First finance officer sign-off ("Checked By"). */
    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /** Second finance officer sign-off ("Reviewed By"). */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** Final approval sign-off ("Approved By"). */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payment_transaction')
            ->logFillable()
            ->logOnlyDirty();
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function shareApplication()
    {
        return $this->belongsTo(ShareApplication::class);
    }

    public function voucher()
    {
        return $this->hasOne(Voucher::class);
    }

    /** The bank transfers this receipt acknowledges. */
    public function deposits()
    {
        return $this->hasMany(PaymentDeposit::class);
    }
}
