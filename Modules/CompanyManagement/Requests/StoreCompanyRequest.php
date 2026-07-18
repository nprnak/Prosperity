<?php

namespace Modules\CompanyManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CompanyManagement\Models\Company;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('companies', 'code')->ignore($this->route('company')),
            ],
            'name_np' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_np' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:'.(int) \Modules\SettingsManagement\Models\Setting::get('max_upload_size_kb', 2048)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Company::STATUS_ACTIVE, Company::STATUS_INACTIVE])],
        ];
    }
}
