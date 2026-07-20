<?php

namespace Modules\ApplicationManagement\Models;

use App\Models\User;
use App\Workflow\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\AllotmentManagement\Models\ShareAllotment;
use Modules\ApplicantManagement\Models\Profile;
use Modules\ApplicationManagement\Enums\ApplicationStatus;
use Modules\CompanyManagement\Models\ShareOffering;
use Modules\PaymentManagement\Models\PaymentTransaction;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ShareApplication extends Model
{
    use HasFactory, LogsActivity;
    use HasWorkflow;

    protected $fillable = [
        'applicant_id', 'share_offering_id', 'application_number', 'shares_applied', 'amount_per_share', 'total_amount_declared',
        'status', 'issue_code', 'asba_reference', 'bank_voucher_image', 'payment_type', 'payment_deposited_bank', 'payment_deposited_ref_no',
        'declaration_accepted', 'blocked_amount', 'blocked_at', 'refunded_amount', 'refunded_at',
        'submitted_at', 'reviewed_by', 'reviewed_at', 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $hidden = ['bank_voucher_image'];

    protected $appends = ['has_bank_voucher_image', 'status_label', 'pending_stage_label', 'can_send_back'];

    protected $casts = [
        'status' => ApplicationStatus::class,
        'declaration_accepted' => 'boolean',
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
        return $this->belongsTo(ShareOffering::class, 'share_offering_id');
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
        return $this->hasMany(ApplicationEvent::class);
    }

    /**
     * Applications that count toward an offering's subscribed shares:
     * everything except drafts, rejections, and applications that ended
     * with no allotment.
     */
    public function scopeCountsTowardSubscription($query)
    {
        return $query->whereNotIn('status', [
            ApplicationStatus::Draft,
            ApplicationStatus::Returned,
            ApplicationStatus::NotAllotted,
        ]);
    }

    public function getHasBankVoucherImageAttribute(): bool
    {
        return $this->bank_voucher_image !== null;
    }

    /** Human wording for the current status, so views don't re-map it. */
    public function getStatusLabelAttribute(): string
    {
        return $this->status->labelEn();
    }

    public function canTransitionTo(ApplicationStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    public function syncPaymentVerificationStatus(): void
    {
        $verifiedTotal = (string) $this->paymentTransactions()
            ->where('verification_status', 'verified')
            ->select(DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->value('total');

        $targetStatus = $this->toPaisa($verifiedTotal) >= $this->toPaisa((string) $this->total_amount_declared)
            ? ApplicationStatus::PaymentVerified
            : ApplicationStatus::PaymentPending;

        if (! in_array($this->status, [
            ApplicationStatus::Approved,
            ApplicationStatus::Returned,
            ApplicationStatus::Allotted,
            ApplicationStatus::PartiallyAllotted,
            ApplicationStatus::NotAllotted,
            ApplicationStatus::DematCredited,
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

    public function workflowSubject(): string
    {
        return 'application';
    }

    public function workflowStatusColumn(): string
    {
        return 'status';
    }

    public function workflowStatusEnum(): string
    {
        return ApplicationStatus::class;
    }
}
