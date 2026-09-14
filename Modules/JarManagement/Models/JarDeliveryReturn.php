<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\JarManagement\Enums\JarCondition;
use Modules\JarManagement\Enums\JarReturnVerificationStatus;

class JarDeliveryReturn extends Model
{
    protected $fillable = [
        'jar_delivery_id', 'jar_id', 'is_new_registration', 'condition',
        'verification_status', 'notes', 'collected_by', 'collected_at',
    ];

    protected $casts = [
        'is_new_registration' => 'boolean',
        'condition' => JarCondition::class,
        'verification_status' => JarReturnVerificationStatus::class,
        'collected_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(JarDelivery::class, 'jar_delivery_id');
    }

    public function jar(): BelongsTo
    {
        return $this->belongsTo(Jar::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
