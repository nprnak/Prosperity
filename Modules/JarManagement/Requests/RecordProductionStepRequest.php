<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Shared by the cleaning/refilling/sealing "record this step" endpoints. */
class RecordProductionStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.production.manage');
    }

    public function rules(): array
    {
        return [
            'at' => ['nullable', 'date'],
        ];
    }
}
