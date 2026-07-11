<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

class StoreDraftStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'step' => ['required', 'integer', 'between:1,5'],
            'payload' => ['required', 'array'],
            'payload.full_name_nepali' => ['nullable', 'string', 'max:255'],
            'payload.full_name_english' => ['nullable', 'string', 'max:255'],
            'payload.date_of_birth' => ['nullable', 'date'],
            'payload.age' => ['nullable', 'integer', 'min:0'],
            'payload.mobile_number' => ['nullable', 'string', 'max:50'],
            'payload.email' => ['nullable', 'email', 'max:255'],
            'payload.shares_applied' => ['nullable', 'integer', 'min:1'],
            'payload.total_amount_declared' => ['nullable', 'numeric', 'min:0'],
            'payload.photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'payload.citizenship_doc' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'payload.national_id_doc' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'payload.pan_doc' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'payload.declaration_accepted' => ['nullable', 'boolean'],
        ];
    }
}
