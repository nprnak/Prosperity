<?php

namespace Modules\ApplicantManagement\Models;

use App\Models\User;
use App\Workflow\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\ApplicantManagement\Enums\EducationLevel;
use Modules\ApplicantManagement\Enums\Gender;
use Modules\ApplicantManagement\Enums\MaritalStatus;
use Modules\ApplicantManagement\Enums\ProfileStatus;
use Modules\ApplicantManagement\Enums\Title;
use Modules\ApplicationManagement\Models\ShareApplication;

class Profile extends Model
{
    use HasFactory;
    use HasWorkflow;

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
        'title' => Title::class,
        'gender' => Gender::class,
        'marital_status' => MaritalStatus::class,
        'education' => EducationLevel::class,
        'profile_status' => ProfileStatus::class,
        'date_of_birth' => 'date:Y-m-d',
        'citizenship_issued_date' => 'date:Y-m-d',
        'asba_consent' => 'boolean',
        'declaration_accepted' => 'boolean',
        'declaration_accepted_at' => 'datetime',
        'profile_submitted_at' => 'datetime',
        'profile_reviewed_at' => 'datetime',
    ];

    protected $appends = ['age', 'title_label_np', 'marital_status_label_np', 'education_label_np', 'pending_stage_label', 'can_send_back', 'profile_status_label'];

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

    public function workflowSubject(): string
    {
        return 'profile';
    }

    public function workflowStatusColumn(): string
    {
        return 'profile_status';
    }

    public function workflowStatusEnum(): string
    {
        return ProfileStatus::class;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /** Human wording for the KYC status, so views never re-map it. */
    public function getProfileStatusLabelAttribute(): ?string
    {
        return $this->profile_status?->labelEn();
    }

    /**
     * Nepali labels for the printed share application form, which renders
     * these fields in Nepali only. Null when the field is unset — the print
     * view supplies its own placeholder in that case.
     */
    public function getTitleLabelNpAttribute(): ?string
    {
        return $this->title?->labelNp();
    }

    public function getMaritalStatusLabelNpAttribute(): ?string
    {
        return $this->marital_status?->labelNp();
    }

    public function getEducationLabelNpAttribute(): ?string
    {
        return $this->education?->labelNp();
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
        return $this->profile_status === ProfileStatus::Approved;
    }
}
