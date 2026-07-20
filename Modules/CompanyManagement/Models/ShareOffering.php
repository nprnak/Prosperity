<?php

namespace Modules\CompanyManagement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ApplicationManagement\Models\ShareApplication;

class ShareOffering extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_UPCOMING,
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'company_id', 'title', 'fiscal_year', 'total_shares', 'share_rate',
        'min_shares', 'max_shares', 'opens_at', 'closes_at', 'status',
    ];

    protected $casts = [
        'share_rate' => 'decimal:2',
        'opens_at' => 'date',
        'closes_at' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function applications()
    {
        return $this->hasMany(ShareApplication::class);
    }

    /**
     * Open for new applications: status is open AND today falls inside
     * the opening window (null bounds are treated as unbounded).
     */
    public function isOpenForApplications(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->opens_at && $today->lt($this->opens_at)) {
            return false;
        }

        if ($this->closes_at && $today->gt($this->closes_at)) {
            return false;
        }

        return true;
    }

    /**
     * Shares still available: total minus everything applied on
     * applications that count toward subscription. Never negative.
     */
    public function sharesRemaining(): int
    {
        $subscribed = (int) $this->applications()->countsTowardSubscription()->sum('shares_applied');

        return max(0, (int) $this->total_shares - $subscribed);
    }

    public function scopeOpenNow($query)
    {
        $today = now()->toDateString();

        return $query->where('status', self::STATUS_OPEN)
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', $today));
    }
}
