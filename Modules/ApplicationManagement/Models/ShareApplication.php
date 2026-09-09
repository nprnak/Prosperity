<?php

namespace Modules\ApplicationManagement\Models;

use App\Enums\WorkflowStage;
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
        'applicant_id', 'focal_person_id', 'share_offering_id', 'application_number', 'shares_applied', 'amount_per_share', 'total_amount_declared',
        'status', 'issue_code',
        'declaration_accepted', 'blocked_amount', 'blocked_at', 'refunded_amount', 'refunded_at',
        'submitted_at', 'reviewed_by', 'reviewed_at', 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $appends = ['status_label', 'pending_stage_label', 'can_send_back'];

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

    /**
     * The Application Verifier who filed this application from a paper form,
     * if it was staff-entered rather than self-submitted. Deliberately
     * absent from $fillable — only ApplicationWizardService sets it.
     */
    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function offering()
    {
        return $this->belongsTo(ShareOffering::class, 'share_offering_id');
    }

    /**
     * Who this application is credited to in the focal-person report. Copied
     * from the applicant's default when the draft is created; it is in
     * $fillable so LogsActivity records any later correction.
     */
    public function focalPerson()
    {
        return $this->belongsTo(User::class, 'focal_person_id');
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

    /** The applicant's declared deposits — slip plus transaction code, one row each. */
    public function vouchers()
    {
        return $this->hasMany(ShareApplicationVoucher::class);
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

    /** Human wording for the current status, so views don't re-map it. */
    public function getStatusLabelAttribute(): string
    {
        return $this->status->labelEn();
    }

    public function canTransitionTo(ApplicationStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    /** @return array<string, array{by: string, at: string}> */
    public function stageSignOffColumns(): array
    {
        return [
            WorkflowStage::Verifier->value => ['by' => 'verified_by', 'at' => 'verified_at'],
            WorkflowStage::Reviewer->value => ['by' => 'reviewed_by', 'at' => 'reviewed_at'],
            WorkflowStage::Approver->value => ['by' => 'approved_by', 'at' => 'approved_at'],
        ];
    }

    /**
     * Statuses in which the application is still finance's to move.
     *
     * Deliberately an allow-list. Listing the statuses to leave alone instead
     * is how Verified and Reviewed came to be rewound: a deny-list has to be
     * remembered every time a status is added, and forgetting silently undoes
     * somebody's sign-off.
     */
    private const FINANCE_OWNED_STATUSES = [
        ApplicationStatus::Submitted,
        ApplicationStatus::SentToBank,
        ApplicationStatus::BankAccepted,
        ApplicationStatus::Blocked,
        ApplicationStatus::PaymentPending,
        ApplicationStatus::PaymentVerified,
    ];

    /**
     * Recompute the payment status from the verified transactions.
     *
     * Once the review chain has taken the application on — Verified, Reviewed,
     * Approved and everything past them — the chain owns its status, so
     * re-totalling the payments must not move it. A payment rejected that late
     * needs a human decision, not a silent rewind into an earlier queue.
     */
    public function syncPaymentVerificationStatus(): void
    {
        if (! in_array($this->status, self::FINANCE_OWNED_STATUSES, true)) {
            return;
        }

        // Totalled from the deposits, since that is where a slip is actually
        // checked off — but only those under a transaction the two-officer
        // sign-off has cleared. Counting deposits alone would let an
        // application reach PaymentVerified on one officer's say-so, which is
        // exactly what the second signature exists to prevent.
        $verifiedTotal = (string) DB::table('payment_deposits')
            ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_deposits.payment_transaction_id')
            ->where('payment_transactions.share_application_id', $this->id)
            ->whereNull('payment_transactions.deleted_at')
            ->where('payment_transactions.verification_status', 'verified')
            ->where('payment_deposits.verification_status', 'verified')
            ->sum('payment_deposits.amount');

        $targetStatus = $this->toPaisa($verifiedTotal) >= $this->toPaisa((string) $this->total_amount_declared)
            ? ApplicationStatus::PaymentVerified
            : ApplicationStatus::PaymentPending;

        $this->update(['status' => $targetStatus]);
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
