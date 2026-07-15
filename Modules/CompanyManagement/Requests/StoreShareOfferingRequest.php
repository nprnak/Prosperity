<?php

namespace Modules\CompanyManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CompanyManagement\Models\ShareOffering;

class StoreShareOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['required', 'string', 'max:20'],
            'total_shares' => ['required', 'integer', 'min:1'],
            'share_rate' => ['required', 'numeric', 'min:0.01'],
            'min_shares' => ['required', 'integer', 'min:1'],
            'max_shares' => ['required', 'integer', 'gte:min_shares'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'status' => ['required', Rule::in(ShareOffering::STATUSES)],
        ];
    }
}
