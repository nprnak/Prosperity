<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\JarManagement\Enums\JarPaymentMethod;
use Modules\JarManagement\Enums\PaymentStatus;
use Modules\JarManagement\Enums\ReconciliationStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JarDelivery extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'jar_vehicle_lot_id', 'jar_customer_id', 'staff_id', 'delivered_at',
        'filled_jars_delivered_count', 'price_per_jar', 'total_amount', 'amount_paid',
        'payment_method', 'payment_status', 'empty_jars_collected_count',
        'reconciliation_status', 'outstanding_jars', 'excess_jars', 'notes',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'filled_jars_delivered_count' => 'integer',
        'price_per_jar' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'payment_method' => JarPaymentMethod::class,
        'payment_status' => PaymentStatus::class,
        'empty_jars_collected_count' => 'integer',
        'reconciliation_status' => ReconciliationStatus::class,
        'outstanding_jars' => 'integer',
        'excess_jars' => 'integer',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(JarVehicleLot::class, 'jar_vehicle_lot_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(JarCustomer::class, 'jar_customer_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JarDeliveryItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(JarDeliveryReturn::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('jar_delivery')->logFillable()->logOnlyDirty();
    }
}
