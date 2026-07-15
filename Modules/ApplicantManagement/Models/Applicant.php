<?php

namespace Modules\ApplicantManagement\Models;

use App\Models\User;
use Modules\ApplicationManagement\Models\ShareApplication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    public const PROFILE_DRAFT = 'draft';
    public const PROFILE_SUBMITTED = 'submitted';
    public const PROFILE_APPROVED = 'approved';
    public const PROFILE_REJECTED = 'rejected';

    /**
     * Fields the KYC review requires before a profile can be submitted.
     */
    public const REQUIRED_PROFILE_FIELDS = [
        'full_name_nepali',
        'full_name_english',
        'date_of_birth',
        'age',
        'father_name',
        'grandfather_name',
        'education',
        'permanent_district',
        'permanent_municipality',
        'permanent_ward',
        'mobile_number',
        'photo_path',
        'citizenship_doc_path',
        'national_id_doc_path',
        'pan_doc_path',
        'boid',
        'crn_number',
        'bank_name',
        'bank_branch',
        'bank_account_number',
        'account_holder_name',
        'asba_consent',
    ];

    protected $fillable = [
        'user_id','full_name_nepali','full_name_english','date_of_birth','age','nationality','father_name','grandfather_name',
        'marital_status','spouse_name','education','occupation','permanent_district','permanent_municipality','permanent_ward',
        'permanent_tole','temporary_district','temporary_municipality','temporary_ward','temporary_tole','citizenship_number',
        'citizenship_issue_district','citizenship_issue_date','national_id_number','pan_number','mobile_number','email',
        'boid','crn_number','bank_name','bank_code','bank_branch','bank_account_number','account_holder_name','asba_consent',
        'investment_source','investment_source_other','share_heir_name','share_heir_relation','share_heir_mobile','work_experience',
        'photo_path','citizenship_doc_path','national_id_doc_path','pan_doc_path','declaration_accepted','declaration_accepted_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'citizenship_issue_date' => 'date',
        'work_experience' => 'array',
        'asba_consent' => 'boolean',
        'declaration_accepted' => 'boolean',
        'declaration_accepted_at' => 'datetime',
        'profile_submitted_at' => 'datetime',
        'profile_reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shareApplications()
    {
        return $this->hasMany(ShareApplication::class);
    }

    public function profileReviewer()
    {
        return $this->belongsTo(User::class, 'profile_reviewed_by');
    }

    public function isProfileComplete(): bool
    {
        foreach (self::REQUIRED_PROFILE_FIELDS as $field) {
            $value = $this->{$field};

            if (blank($value)) {
                return false;
            }

            if (is_string($value) && in_array(trim($value), ['-', 'N/A'], true)) {
                return false;
            }
        }

        return true;
    }

    public function isProfileApproved(): bool
    {
        return $this->profile_status === self::PROFILE_APPROVED;
    }
}
