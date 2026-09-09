<?php

namespace Modules\ApplicantManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\ApplicantManagement\Enums\EducationLevel;
use Modules\ApplicantManagement\Enums\Gender;
use Modules\ApplicantManagement\Enums\MaritalStatus;
use Modules\ApplicantManagement\Enums\SourceOfFunds;
use Modules\ApplicantManagement\Enums\Title;
use Modules\ApplicantManagement\Models\Profile;
use Modules\SettingsManagement\Models\Setting;

class ApplicantProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Whose profile this request fills in. A KYC Verifier entering a paper
     * form routes through {applicant}; the applicant's own submission has no
     * such route parameter and falls back to themselves.
     */
    protected function targetUserId(): ?int
    {
        return $this->route('applicant')?->id ?? $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            // 1. Personal information
            'title' => ['nullable', Rule::enum(Title::class)],
            'full_name_np' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'nationality' => ['required', 'string', 'max:100'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'father_name_en' => ['required', 'string', 'max:255'],
            'father_name_np' => ['required', 'string', 'max:255'],
            'mother_name_en' => ['nullable', 'string', 'max:255'],
            'mother_name_np' => ['nullable', 'string', 'max:255'],
            'grandfather_name_en' => ['required', 'string', 'max:255'],
            'grandfather_name_np' => ['required', 'string', 'max:255'],
            'spouse_name_en' => ['nullable', 'string', 'max:255'],
            'spouse_name_np' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'education' => ['required', Rule::enum(EducationLevel::class)],

            // 2. Contact
            'mobile' => ['required', 'string', 'max:50'],

            // 3. Permanent address (as per NID) — names must exist in the geography tables.
            'permanent.province' => ['required', 'string', Rule::exists('provinces', 'name_en')],
            'permanent.district' => ['required', 'string', Rule::exists('districts', 'name_en')],
            'permanent.local_level' => ['required', 'string', Rule::exists('local_levels', 'name_en')],
            'permanent.ward_no' => ['required', 'string', 'max:10'],
            'permanent.tole' => ['nullable', 'string', 'max:255'],

            // 4. Temporary address
            'temporary_same_as_permanent' => ['boolean'],
            'temporary.province' => ['nullable', 'string', Rule::exists('provinces', 'name_en')],
            'temporary.district' => ['nullable', 'string', Rule::exists('districts', 'name_en')],
            'temporary.local_level' => ['nullable', 'string', Rule::exists('local_levels', 'name_en')],
            'temporary.ward_no' => ['nullable', 'string', 'max:10'],
            'temporary.tole' => ['nullable', 'string', 'max:255'],

            // 5. Identity — citizenship and national ID are compulsory.
            'citizenship_number' => ['required', 'string', 'max:50'],
            // As printed on the certificate in Devanagari numerals — optional,
            // since not every KYC form on file will have been asked for it.
            'citizenship_number_np' => ['nullable', 'string', 'max:50'],
            'citizenship_issued_district' => ['nullable', 'string', 'max:255'],
            'citizenship_issued_date' => ['nullable', 'date', 'before_or_equal:today'],
            'national_id_number' => ['required', 'digits:10'],
            'pan_number' => ['nullable', 'string', 'max:50'],

            // 6. Documents — required until a copy is on file.
            'photo' => $this->documentRules('photo', imageOnly: true),
            'citizenship_front' => $this->documentRules('citizenship_front'),
            'citizenship_back' => $this->documentRules('citizenship_back'),
            'national_id_front' => $this->documentRules('national_id_front'),
            'national_id_back' => $this->documentRules('national_id_back'),
            'pan_doc' => $this->documentRules('pan'),
            'signature' => $this->documentRules('signature', imageOnly: true),

            // 7. Source of investment — optional; "Other" may carry a free-text detail.
            'sources' => ['nullable', 'array'],
            'sources.*' => ['string', Rule::enum(SourceOfFunds::class)],
            'source_other_detail' => ['nullable', 'string', 'max:500'],

            // 8. Nominee
            'nominee.full_name' => ['nullable', 'string', 'max:255'],
            'nominee.relationship' => ['nullable', 'string', 'max:100', 'required_with:nominee.full_name'],
            'nominee.mobile' => ['nullable', 'string', 'max:50'],
            'nominee.address' => ['nullable', 'string', 'max:500'],

            // 9. Professional experience
            'experiences' => ['nullable', 'array', 'max:10'],
            'experiences.*.organization_name' => ['required', 'string', 'max:255'],
            'experiences.*.address' => ['nullable', 'string', 'max:255'],
            'experiences.*.position' => ['nullable', 'string', 'max:255'],
            'experiences.*.years' => ['nullable', 'numeric', 'min:0', 'max:99'],

            // 10. Declaration — one combined statement rather than three separate ticks.
            'declarations.accepted' => ['accepted'],

            // MeroShare / C-ASBA
            'boid' => ['required', 'digits:16', Rule::unique('profiles', 'boid')->ignore($this->targetUserId(), 'user_id')],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_branch' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'asba_consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'declarations.accepted.accepted' => 'You must accept the declaration before saving.',
            'permanent.*.required' => 'This field is required.',
        ];
    }

    /**
     * A document upload is required until the profile already has one on file.
     */
    protected function documentRules(string $documentType, bool $imageOnly = false): array
    {
        $maxKb = (int) Setting::get('max_upload_size_kb', 5120);

        $alreadyUploaded = Profile::query()
            ->where('user_id', $this->targetUserId())
            ->whereHas('documents', fn ($q) => $q->where('document_type', $documentType))
            ->exists();

        return [
            $alreadyUploaded ? 'nullable' : 'required',
            'file',
            $imageOnly ? 'mimes:jpg,jpeg,png' : 'mimes:jpg,jpeg,png,pdf',
            'max:'.$maxKb,
        ];
    }
}
