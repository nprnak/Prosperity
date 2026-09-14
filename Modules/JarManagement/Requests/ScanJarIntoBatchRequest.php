<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanJarIntoBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.production.manage');
    }

    public function rules(): array
    {
        return [
            'jar_code' => ['required', 'string', 'max:30'],
        ];
    }
}
