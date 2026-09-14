<?php

namespace Modules\JarManagement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JarVehicle extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['vehicle_number', 'name', 'max_jars', 'active'];

    protected $casts = [
        'active' => 'boolean',
        'max_jars' => 'integer',
    ];

    public function lots(): HasMany
    {
        return $this->hasMany(JarVehicleLot::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('jar_vehicle')->logFillable()->logOnlyDirty();
    }
}
