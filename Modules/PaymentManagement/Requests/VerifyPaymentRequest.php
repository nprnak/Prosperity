<?php

namespace Modules\PaymentManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['finance_staff', 'admin']);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:verified,rejected'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
