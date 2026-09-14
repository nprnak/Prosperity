<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\JarManagement\Enums\JarReceiptDecision;
use Modules\JarManagement\Enums\QuarantineReason;

class JarFactoryReceiptItem extends Model
{
    protected $fillable = [
        'jar_factory_receipt_id', 'jar_id', 'decision', 'quarantine_reason',
        'matched_to_vehicle', 'scanned_by', 'scanned_at',
    ];

    protected $casts = [
        'decision' => JarReceiptDecision::class,
        'quarantine_reason' => QuarantineReason::class,
        'matched_to_vehicle' => 'boolean',
        'scanned_at' => 'datetime',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(JarFactoryReceipt::class, 'jar_factory_receipt_id');
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
