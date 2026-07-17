<?php

namespace Modules\SettingsManagement\Models;

use Illuminate\Database\Eloquent\Model;

class LocalLevel extends Model
{
    protected $fillable = ['province_id', 'district_id', 'name_en', 'name_np', 'type'];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }
}
