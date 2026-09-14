<?php

namespace Modules\JarManagement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JarDriver extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'phone', 'license_no', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function lots(): HasMany
    {
        return $this->hasMany(JarVehicleLot::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('jar_driver')->logFillable()->logOnlyDirty();
    }
}
