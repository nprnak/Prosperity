<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'declaration_accepted' => ['required', 'accepted'],
            'asba_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
