<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\JarManagement\Enums\CustomerType;

class StoreJarCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.customer.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'type' => ['required', Rule::enum(CustomerType::class)],
            'address' => ['nullable', 'string'],
            'route_area' => ['nullable', 'string', 'max:100'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
            'active' => ['boolean'],
        ];
    }
}
