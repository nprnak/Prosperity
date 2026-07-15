<?php

namespace Modules\ApplicantManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicantProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'full_name_nepali' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:255'],
            'grandfather_name' => ['required', 'string', 'max:255'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'education' => ['required', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:50'],
            'permanent_district' => ['required', 'string', 'max:255'],
            'permanent_municipality' => ['required', 'string', 'max:255'],
            'permanent_ward' => ['required', 'string', 'max:50'],
            'permanent_tole' => ['nullable', 'string', 'max:255'],
            'temporary_district' => ['nullable', 'string', 'max:255'],
            'temporary_municipality' => ['nullable', 'string', 'max:255'],
            'temporary_ward' => ['nullable', 'string', 'max:50'],
            'temporary_tole' => ['nullable', 'string', 'max:255'],
            'citizenship_number' => ['nullable', 'string', 'max:255'],
            'citizenship_issue_district' => ['nullable', 'string', 'max:255'],
            'citizenship_issue_date' => ['nullable', 'date'],
            'national_id_number' => ['nullable', 'string', 'max:255'],
            'pan_number' => ['nullable', 'string', 'max:255'],
            'boid' => ['required', 'digits:16', Rule::unique('applicants', 'boid')->ignore($this->user()?->id, 'user_id')],
            'crn_number' => ['required', 'string', 'min:8', 'max:20'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_code' => ['nullable', 'string', 'max:20'],
            'bank_branch' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'asba_consent' => ['required', 'accepted'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'citizenship_doc' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'national_id_doc' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'pan_doc' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
