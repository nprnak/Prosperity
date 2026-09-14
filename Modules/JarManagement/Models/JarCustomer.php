<?php

namespace Modules\JarManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\JarManagement\Enums\CustomerType;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JarCustomer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'type', 'address', 'route_area',
        'default_price', 'jars_outstanding', 'active', 'created_by',
    ];

    protected $casts = [
        'type' => CustomerType::class,
        'default_price' => 'decimal:2',
        'jars_outstanding' => 'integer',
        'active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jars(): HasMany
    {
        return $this->hasMany(Jar::class, 'current_customer_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(JarDelivery::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('jar_customer')->logFillable()->logOnlyDirty();
    }
}
