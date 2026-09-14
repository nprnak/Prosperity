<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\JarManagement\Enums\JarLotItemStatus;

class JarLotItem extends Model
{
    protected $fillable = [
        'jar_vehicle_lot_id', 'jar_id', 'status', 'loaded_by', 'loaded_at',
    ];

    protected $casts = [
        'status' => JarLotItemStatus::class,
        'loaded_at' => 'datetime',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(JarVehicleLot::class, 'jar_vehicle_lot_id');
    }

    public function jar(): BelongsTo
    {
        return $this->belongsTo(Jar::class);
    }

    public function loadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }
}
