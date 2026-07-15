<?php

namespace Modules\ApplicantManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('profile.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
