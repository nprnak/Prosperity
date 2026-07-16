<?php

namespace Modules\ApplicantManagement\Models;

use App\Models\User;
use Modules\ApplicationManagement\Models\ShareApplication;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    public const PROFILE_INCOMPLETE = 'incomplete';
    public const PROFILE_SUBMITTED = 'submitted';
    public const PROFILE_APPROVED = 'approved';
    public const PROFILE_REJECTED = 'rejected';

    /**
     * Document types the KYC review requires before a profile can be submitted.
     */
    public const REQUIRED_DOCUMENT_TYPES = [
        'photo',
        'citizenship_front',
        'citizenship_back',
        'national_id',
        'pan',
        'signature',
    ];

    /**
     * Scalar fields the KYC review requires before a profile can be submitted.
     */
    public const REQUIRED_PROFILE_FIELDS = [
        'full_name_en',
        'full_name_np',
        'date_of_birth',
        'father_name',
        'mother_name',
        'grandfather_name',
        'education',
        'mobile',
        'citizenship_number',
        'national_id_number',
        'boid',
        'bank_name',
        'bank_branch',
        'bank_account_number',
        'account_holder_name',
        'asba_consent',
    ];

    protected $fillable = [
        'user_id', 'applicant_type', 'title', 'full_name_en', 'full_name_np', 'gender', 'date_of_birth',
        'nationality', 'marital_status', 'father_name', 'mother_name', 'grandfather_name', 'spouse_name',
        'occupation', 'education', 'mobile', 'email', 'pan_number', 'citizenship_number',
        'citizenship_issued_district', 'citizenship_issued_date', 'national_id_number',
        'boid', 'bank_name', 'bank_code', 'bank_branch', 'bank_account_number',
        'account_holder_name', 'asba_consent', 'declaration_accepted', 'declaration_accepted_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'citizenship_issued_date' => 'date:Y-m-d',
        'asba_consent' => 'boolean',
        'declaration_accepted' => 'boolean',
        'declaration_accepted_at' => 'datetime',
        'profile_submitted_at' => 'datetime',
        'profile_reviewed_at' => 'datetime',
    ];

    protected $appends = ['age'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shareApplications()
    {
        return $this->hasMany(ShareApplication::class, 'applicant_id');
    }

    public function profileReviewer()
    {
        return $this->belongsTo(User::class, 'profile_reviewed_by');
    }

    public function addresses()
    {
        return $this->hasMany(ProfileAddress::class);
    }

    public function permanentAddress()
    {
        return $this->hasOne(ProfileAddress::class)->where('type', 'permanent');
    }

    public function temporaryAddress()
    {
        return $this->hasOne(ProfileAddress::class)->where('type', 'temporary');
    }

    public function documents()
    {
        return $this->hasMany(ProfileDocument::class);
    }

    public function sourcesOfFunds()
    {
        return $this->hasMany(ProfileSourceOfFund::class);
    }

    public function nominees()
    {
        return $this->hasMany(ProfileNominee::class);
    }

    public function experiences()
    {
        return $this->hasMany(ProfileExperience::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function document(string $type): ?ProfileDocument
    {
        return $this->documents->firstWhere('document_type', $type);
    }

    /**
     * Every requirement the profile must satisfy before KYC submission,
     * as label => satisfied pairs. Drives both completeness and the
     * completion percentage shown on the profile page.
     */
    public function completionChecks(): array
    {
        $checks = [];

        foreach (self::REQUIRED_PROFILE_FIELDS as $field) {
            $value = $this->{$field};

            $checks[$field] = ! blank($value)
                && ! (is_string($value) && in_array(trim($value), ['-', 'N/A'], true));
        }

        $permanent = $this->permanentAddress;

        foreach (['province', 'district', 'local_level', 'ward_no'] as $part) {
            $checks['permanent_'.$part] = ! blank($permanent?->{$part});
        }

        $documents = $this->documents->pluck('document_type')->all();

        foreach (self::REQUIRED_DOCUMENT_TYPES as $type) {
            $checks['document_'.$type] = in_array($type, $documents, true);
        }

        $checks['declaration_accepted'] = (bool) $this->declaration_accepted;

        return $checks;
    }

    public function isProfileComplete(): bool
    {
        return ! in_array(false, $this->completionChecks(), true);
    }

    public function completionPercent(): int
    {
        $checks = $this->completionChecks();

        if ($checks === []) {
            return 0;
        }

        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }

    public function isProfileApproved(): bool
    {
        return $this->profile_status === self::PROFILE_APPROVED;
    }
}
