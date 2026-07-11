<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','full_name_nepali','full_name_english','date_of_birth','age','nationality','father_name','grandfather_name',
        'marital_status','spouse_name','education','occupation','permanent_district','permanent_municipality','permanent_ward',
        'permanent_tole','temporary_district','temporary_municipality','temporary_ward','temporary_tole','citizenship_number',
        'citizenship_issue_district','citizenship_issue_date','national_id_number','pan_number','mobile_number','email',
        'investment_source','investment_source_other','share_heir_name','share_heir_relation','share_heir_mobile','work_experience',
        'photo_path','citizenship_doc_path','national_id_doc_path','pan_doc_path','declaration_accepted','declaration_accepted_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'citizenship_issue_date' => 'date',
        'work_experience' => 'array',
        'declaration_accepted' => 'boolean',
        'declaration_accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shareApplications()
    {
        return $this->hasMany(ShareApplication::class);
    }
}
