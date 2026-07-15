<?php

namespace Modules\AllotmentManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAllotmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('allotment.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'shares_allotted' => ['required', 'integer', 'min:1'],
            'allotment_date' => ['required', 'date'],
            'demat_account_no' => ['nullable', 'string', 'max:255'],
            'dp_id' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
