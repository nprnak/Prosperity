<?php

namespace Modules\ApplicationManagement\Models;

use App\Models\User;
use Modules\ApplicantManagement\Models\Profile;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Modules\AllotmentManagement\Models\ShareAllotment;
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
    public const STATUS_SENT_TO_BANK = 'sent_to_bank';
    public const STATUS_BANK_ACCEPTED = 'bank_accepted';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PAYMENT_VERIFIED = 'payment_verified';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ALLOTTED = 'allotted';
    public const STATUS_PARTIALLY_ALLOTTED = 'partially_allotted';
    public const STATUS_NOT_ALLOTTED = 'not_allotted';
    public const STATUS_REFUND_INITIATED = 'refund_initiated';
    public const STATUS_REFUND_COMPLETED = 'refund_completed';
    public const STATUS_DEMAT_CREDITED = 'demat_credited';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FLOW = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_SENT_TO_BANK,
        self::STATUS_BANK_ACCEPTED,
        self::STATUS_BLOCKED,
        self::STATUS_PAYMENT_PENDING,
        self::STATUS_PAYMENT_VERIFIED,
        self::STATUS_REVIEWED,
        self::STATUS_VERIFIED,
        self::STATUS_APPROVED,
        self::STATUS_ALLOTTED,
        self::STATUS_PARTIALLY_ALLOTTED,
        self::STATUS_NOT_ALLOTTED,
        self::STATUS_REFUND_INITIATED,
        self::STATUS_REFUND_COMPLETED,
        self::STATUS_DEMAT_CREDITED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'applicant_id','share_offering_id','application_number','shares_applied','amount_per_share','total_amount_declared',
        'status','issue_code','asba_reference','bank_voucher_image','blocked_amount','blocked_at','refunded_amount','refunded_at',
        'submitted_at','reviewed_by','reviewed_at','verified_by','verified_at','approved_by','approved_at','rejection_reason',
    ];

    protected $hidden = ['bank_voucher_image'];

    protected $appends = ['has_bank_voucher_image'];

    protected $casts = [
        'amount_per_share' => 'decimal:2',
        'total_amount_declared' => 'decimal:2',
        'blocked_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'blocked_at' => 'datetime',
        'refunded_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
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
        return $this->belongsTo(Profile::class);
    }

    public function offering()
    {
        return $this->belongsTo(\Modules\CompanyManagement\Models\ShareOffering::class, 'share_offering_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function allotment()
    {
        return $this->hasOne(ShareAllotment::class);
    }

    public function events()
    {
        return $this->hasMany(\Modules\ApplicationManagement\Models\ApplicationEvent::class);
    }

    public function getHasBankVoucherImageAttribute(): bool
    {
        return $this->bank_voucher_image !== null;
    }

    public function canTransitionTo(string $targetStatus): bool
    {
        if (! in_array($targetStatus, self::STATUS_FLOW, true)) {
            return false;
        }

        if ($this->status === $targetStatus) {
            return true;
        }

        $from = array_search($this->status, self::STATUS_FLOW, true);
        $to = array_search($targetStatus, self::STATUS_FLOW, true);

        if ($from === false || $to === false) {
            return false;
        }

        if ($targetStatus === self::STATUS_REJECTED) {
            return true;
        }

        return $to >= $from;
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

        if (! in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_ALLOTTED,
            self::STATUS_PARTIALLY_ALLOTTED,
            self::STATUS_NOT_ALLOTTED,
            self::STATUS_DEMAT_CREDITED,
        ], true)) {
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
