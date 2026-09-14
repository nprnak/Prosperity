<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJarDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.vehicle.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'license_no' => ['nullable', 'string', 'max:40'],
            'active' => ['boolean'],
        ];
    }
}
