<?php

namespace Modules\ApplicantManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileAddress extends Model
{
    protected $fillable = ['profile_id', 'type', 'province', 'district', 'local_level', 'ward_no', 'tole', 'street'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
