<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JarFactoryReceipt extends Model
{
    use LogsActivity;

    protected $fillable = [
        'jar_vehicle_lot_id', 'received_by', 'received_at',
        'total_expected', 'total_scanned', 'total_accepted', 'total_quarantined', 'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'total_expected' => 'integer',
        'total_scanned' => 'integer',
        'total_accepted' => 'integer',
        'total_quarantined' => 'integer',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(JarVehicleLot::class, 'jar_vehicle_lot_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JarFactoryReceiptItem::class, 'jar_factory_receipt_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('jar_factory_receipt')->logFillable()->logOnlyDirty();
    }
}
