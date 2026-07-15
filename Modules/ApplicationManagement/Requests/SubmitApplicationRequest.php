<?php

namespace Modules\ApplicationManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ShareApplicationPolicy::submit — owner only (admin via Gate::before).
        return $this->user()?->can('submit', $this->route('application')) ?? false;
    }

    public function rules(): array
    {
        return [
            'declaration_accepted' => ['required', 'accepted'],
            'asba_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
