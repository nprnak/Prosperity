<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['finance_staff', 'admin']);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'in:cheque,self_cheque_deposit,online_transfer,cash,ips,mobile_banking'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'payment_reference_no' => ['nullable', 'string', 'max:255'],
            'cheque_no' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'holding_id_no' => ['nullable', 'string', 'max:255'],
            'id_type' => ['nullable', 'in:citizenship,national_id,pan'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
