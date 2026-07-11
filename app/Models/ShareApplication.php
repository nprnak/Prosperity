<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ShareApplication extends Model
{
    use HasFactory, LogsActivity;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PAYMENT_VERIFIED = 'payment_verified';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ALLOTTED = 'allotted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'applicant_id','application_number','shares_applied','amount_per_share','total_amount_declared',
        'status','submitted_at','reviewed_by','reviewed_at','rejection_reason',
    ];

    protected $casts = [
        'amount_per_share' => 'decimal:2',
        'total_amount_declared' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('share_application')
            ->logFillable()
            ->logOnlyDirty();
    }

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function allotment()
    {
        return $this->hasOne(ShareAllotment::class);
    }

    public function syncPaymentVerificationStatus(): void
    {
        $verifiedTotal = (string) $this->paymentTransactions()
            ->where('verification_status', 'verified')
            ->select(DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->value('total');

        $targetStatus = $this->toPaisa($verifiedTotal) >= $this->toPaisa((string) $this->total_amount_declared)
            ? self::STATUS_PAYMENT_VERIFIED
            : self::STATUS_PAYMENT_PENDING;

        if ($this->status !== self::STATUS_APPROVED && $this->status !== self::STATUS_REJECTED && $this->status !== self::STATUS_ALLOTTED) {
            $this->update(['status' => $targetStatus]);
        }
    }

    private function toPaisa(string $amount): int
    {
        $normalized = preg_replace('/[^0-9.]/', '', $amount) ?: '0';
        [$rupees, $paisa] = array_pad(explode('.', $normalized, 2), 2, '0');
        $paisa = str_pad(substr($paisa, 0, 2), 2, '0');

        return ((int) $rupees * 100) + (int) $paisa;
    }
}
