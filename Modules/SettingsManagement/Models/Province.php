<?php

namespace Modules\SettingsManagement\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $fillable = ['name_en', 'name_np'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
