<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJarVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.vehicle.manage');
    }

    public function rules(): array
    {
        return [
            'vehicle_number' => ['required', 'string', 'max:30', Rule::unique('jar_vehicles', 'vehicle_number')->ignore($this->route('jarVehicle'))],
            'name' => ['nullable', 'string', 'max:255'],
            'max_jars' => ['required', 'integer', 'min:1', 'max:80'],
            'active' => ['boolean'],
        ];
    }
}
