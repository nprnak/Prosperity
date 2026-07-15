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
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Company::STATUS_ACTIVE, Company::STATUS_INACTIVE])],
        ];
    }
}
