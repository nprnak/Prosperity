<?php

namespace Modules\ApplicantManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileNominee extends Model
{
    protected $fillable = ['profile_id', 'full_name', 'relationship', 'mobile', 'address'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
