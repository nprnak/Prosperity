<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\JarManagement\Enums\JarReceiptDecision;
use Modules\JarManagement\Enums\QuarantineReason;

class FactoryReceiptScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.receipt.manage');
    }

    public function rules(): array
    {
        return [
            'jar_code' => ['required', 'string', 'max:30'],
            'decision' => ['required', Rule::enum(JarReceiptDecision::class)],
            'quarantine_reason' => ['required_if:decision,quarantined', 'nullable', Rule::enum(QuarantineReason::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
