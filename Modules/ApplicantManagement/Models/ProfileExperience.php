<?php

namespace Modules\ApplicantManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileExperience extends Model
{
    protected $fillable = ['profile_id', 'organization_name', 'address', 'position', 'years'];

    protected $casts = ['years' => 'float'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
