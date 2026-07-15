<?php

namespace Modules\ApprovalManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('application.approve') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
