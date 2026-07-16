<?php

namespace Modules\ApplicantManagement\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileDocument extends Model
{
    protected $fillable = ['profile_id', 'document_type', 'document_number', 'file_path', 'status'];

    protected $hidden = ['file_path'];

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
