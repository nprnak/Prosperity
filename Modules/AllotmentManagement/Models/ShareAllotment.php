<?php

namespace Modules\AllotmentManagement\Models;

use Modules\ApplicationManagement\Models\ShareApplication;
use Modules\ApplicantManagement\Models\Profile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShareAllotment extends Model
{
    use HasFactory;

    protected $fillable = [
        'share_application_id','applicant_id','shares_allotted','allotment_date','demat_account_no','dp_id','client_id','certificate_number',
    ];

    protected $casts = [
        'allotment_date' => 'date',
    ];

    public function shareApplication()
    {
        return $this->belongsTo(ShareApplication::class);
    }

    public function applicant()
    {
        return $this->belongsTo(Profile::class);
    }
}
