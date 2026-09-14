<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\JarManagement\Enums\JarCondition;
use Modules\JarManagement\Enums\JarStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The traceability spine of the module. status/condition/current_* are a
 * denormalized "current state" projection kept in sync by the Services/
 * classes; movements() is the append-only ledger those same services write
 * to and is the actual source of truth for a jar's history.
 */
class Jar extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'jar_code', 'status', 'condition',
        'current_batch_id', 'current_vehicle_lot_id', 'current_customer_id',
        'registered_by', 'registered_at',
    ];

    protected $casts = [
        'status' => JarStatus::class,
        'condition' => JarCondition::class,
        'registered_at' => 'datetime',
    ];

    public function currentBatch(): BelongsTo
    {
        return $this->belongsTo(JarProductionBatch::class, 'current_batch_id');
    }

    public function currentVehicleLot(): BelongsTo
    {
        return $this->belongsTo(JarVehicleLot::class, 'current_vehicle_lot_id');
    }

    public function currentCustomer(): BelongsTo
    {
        return $this->belongsTo(JarCustomer::class, 'current_customer_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(JarMovement::class)->orderBy('occurred_at');
    }

    public function batchItems(): HasMany
    {
        return $this->hasMany(JarBatchItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('jar')->logOnly(['jar_code', 'status', 'condition'])->logOnlyDirty();
    }
}
