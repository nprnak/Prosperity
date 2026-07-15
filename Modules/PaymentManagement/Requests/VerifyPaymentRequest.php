<?php

namespace Modules\PaymentManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('payment.verify') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:verified,rejected'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
