<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQualityApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.production.manage');
    }

    public function rules(): array
    {
        return [
            'approved_jar_ids' => ['array'],
            'approved_jar_ids.*' => ['integer'],
            'rejections' => ['array'],
            'rejections.*.jar_id' => ['required_with:rejections', 'integer'],
            'rejections.*.reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
