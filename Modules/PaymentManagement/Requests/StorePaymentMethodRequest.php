<?php

namespace Modules\PaymentManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PaymentManagement\Models\PaymentMethod;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment-method.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'qr_image' => ['nullable', 'image', 'max:'.(int) \Modules\SettingsManagement\Models\Setting::get('max_upload_size_kb', 2048)],
            'status' => ['required', Rule::in([PaymentMethod::STATUS_ACTIVE, PaymentMethod::STATUS_INACTIVE])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
