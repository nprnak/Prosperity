<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\JarManagement\Enums\VehicleLotStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JarVehicleLot extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'lot_code', 'jar_vehicle_id', 'jar_driver_id', 'assigned_staff_id',
        'route_area', 'status', 'max_jars', 'dispatched_at', 'returned_at', 'created_by',
    ];

    protected $casts = [
        'status' => VehicleLotStatus::class,
        'max_jars' => 'integer',
        'dispatched_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(JarVehicle::class, 'jar_vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(JarDriver::class, 'jar_driver_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JarLotItem::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(JarDelivery::class);
    }

    public function factoryReceipt(): HasOne
    {
        return $this->hasOne(JarFactoryReceipt::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('jar_vehicle_lot')->logFillable()->logOnlyDirty();
    }
}
