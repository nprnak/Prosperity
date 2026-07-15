<?php

namespace Modules\CompanyManagement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name', 'code', 'description', 'logo_path', 'status',
    ];

    public function offerings()
    {
        return $this->hasMany(ShareOffering::class);
    }
}
