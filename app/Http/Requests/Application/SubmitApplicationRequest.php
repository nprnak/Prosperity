<?php

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'declaration_accepted' => ['required', 'accepted'],
        ];
    }
}
