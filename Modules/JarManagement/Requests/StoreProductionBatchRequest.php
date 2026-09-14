<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.production.manage');
    }

    public function rules(): array
    {
        return [
            'production_date' => ['nullable', 'date'],
        ];
    }
}
