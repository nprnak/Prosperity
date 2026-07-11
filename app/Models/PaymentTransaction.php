<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTransaction extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'share_application_id','receipt_number','amount','payment_mode','bank_name','payment_reference_no','cheque_no',
        'payment_date','holding_id_no','id_type','verification_status','verified_by','verified_at','issued_by','approved_by','notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payment_transaction')
            ->logFillable()
            ->logOnlyDirty();
    }

    public function shareApplication()
    {
        return $this->belongsTo(ShareApplication::class);
    }

    public function voucher()
    {
        return $this->hasOne(Voucher::class);
    }
}
