<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\JarManagement\Enums\CustomerType;
use Modules\JarManagement\Enums\JarPaymentMethod;

class RecordDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.delivery.manage');
    }

    public function rules(): array
    {
        return [
            'jar_customer_id' => ['nullable', 'exists:jar_customers,id'],
            'new_customer.name' => ['required_without:jar_customer_id', 'string', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:20'],
            'new_customer.type' => ['nullable', Rule::enum(CustomerType::class)],
            'new_customer.address' => ['nullable', 'string'],
            'new_customer.route_area' => ['nullable', 'string', 'max:100'],
            'new_customer.default_price' => ['nullable', 'numeric', 'min:0'],

            'jar_codes' => ['array'],
            'jar_codes.*' => ['string', 'max:30', 'distinct'],

            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::enum(JarPaymentMethod::class)],

            'returns' => ['array'],
            'returns.*.jar_code' => ['nullable', 'string', 'max:30'],
            'returns.*.is_new_registration' => ['boolean'],
            'returns.*.condition' => ['nullable', 'string'],
            'returns.*.notes' => ['nullable', 'string'],

            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $jarCodes = (array) $this->input('jar_codes', []);
            $returns = (array) $this->input('returns', []);

            if ($jarCodes === [] && $returns === []) {
                $validator->errors()->add('jar_codes', 'Record at least one delivered jar or one collected empty jar.');
            }

            foreach ($returns as $index => $return) {
                $hasCode = filled($return['jar_code'] ?? null);
                $isNew = (bool) ($return['is_new_registration'] ?? false);

                if (! $hasCode && ! $isNew) {
                    $validator->errors()->add("returns.{$index}.jar_code", 'Scan the jar code or mark it as a new/uncoded jar.');
                }
            }
        });
    }
}
