<?php

namespace Modules\ApprovalManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // reviewers and verifiers may reject within their own stage; the
        // stage routes enforce the specific permission via middleware.
        return $this->user()?->canAny(['application.reject', 'application.review', 'application.verify']) ?? false;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
