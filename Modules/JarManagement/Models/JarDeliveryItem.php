<?php

namespace Modules\JarManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JarDeliveryItem extends Model
{
    protected $fillable = ['jar_delivery_id', 'jar_id', 'unit_price'];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(JarDelivery::class, 'jar_delivery_id');
    }

    public function jar(): BelongsTo
    {
        return $this->belongsTo(Jar::class);
    }
}
