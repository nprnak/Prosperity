<?php

namespace Modules\ApplicantManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileSourceOfFund extends Model
{
    protected $table = 'profile_source_of_funds';

    protected $fillable = ['profile_id', 'source_type', 'description'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
