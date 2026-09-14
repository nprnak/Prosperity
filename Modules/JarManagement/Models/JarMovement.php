<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\JarManagement\Enums\JarMovementEvent;
use Modules\JarManagement\Enums\JarStatus;

/**
 * Append-only ledger — never updated after insert. This, not the
 * denormalized jars.status/current_* columns, is what a jar's full history
 * (movement history requirement) is read from.
 */
class JarMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'jar_id', 'event_type', 'from_status', 'to_status',
        'jar_production_batch_id', 'jar_vehicle_lot_id', 'jar_customer_id',
        'jar_delivery_id', 'jar_factory_receipt_id', 'recorded_by', 'occurred_at', 'notes',
    ];

    protected $casts = [
        'event_type' => JarMovementEvent::class,
        'from_status' => JarStatus::class,
        'to_status' => JarStatus::class,
        'occurred_at' => 'datetime',
    ];

    public function jar(): BelongsTo
    {
        return $this->belongsTo(Jar::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(JarProductionBatch::class, 'jar_production_batch_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(JarVehicleLot::class, 'jar_vehicle_lot_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(JarCustomer::class, 'jar_customer_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(JarDelivery::class, 'jar_delivery_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(JarFactoryReceipt::class, 'jar_factory_receipt_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
