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
        'name', 'name_np', 'code', 'description', 'address', 'address_np',
        'bank_name', 'bank_account_number', 'logo_path', 'status',
    ];

    public function offerings()
    {
        return $this->hasMany(ShareOffering::class);
    }
}
