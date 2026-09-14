<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\JarManagement\Enums\ProductionBatchStatus;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JarProductionBatch extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'batch_code', 'production_date', 'status',
        'cleaning_at', 'cleaning_by', 'refilling_at', 'refilling_by',
        'sealing_at', 'sealing_by', 'quality_approved_by', 'quality_approved_at',
        'quality_notes', 'created_by',
    ];

    protected $casts = [
        'production_date' => 'date',
        'status' => ProductionBatchStatus::class,
        'cleaning_at' => 'datetime',
        'refilling_at' => 'datetime',
        'sealing_at' => 'datetime',
        'quality_approved_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JarBatchItem::class, 'jar_production_batch_id');
    }

    public function jars()
    {
        return $this->hasManyThrough(Jar::class, JarBatchItem::class, 'jar_production_batch_id', 'id', 'id', 'jar_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cleaningBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleaning_by');
    }

    public function refillingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refilling_by');
    }

    public function sealingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sealing_by');
    }

    public function qualityApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_approved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('jar_production_batch')->logFillable()->logOnlyDirty();
    }
}
