<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\JarManagement\Enums\JarBatchQualityResult;

class JarBatchItem extends Model
{
    protected $fillable = [
        'jar_production_batch_id', 'jar_id', 'quality_result',
        'rejection_reason', 'scanned_by', 'scanned_at',
    ];

    protected $casts = [
        'quality_result' => JarBatchQualityResult::class,
        'scanned_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(JarProductionBatch::class, 'jar_production_batch_id');
    }

    public function jar(): BelongsTo
    {
        return $this->belongsTo(Jar::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
