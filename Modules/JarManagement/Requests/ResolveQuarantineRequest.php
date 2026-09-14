<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveQuarantineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.receipt.manage') || $this->user()->can('jar.inventory.manage');
    }

    public function rules(): array
    {
        return [
            'release_to_cleaning_queue' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
