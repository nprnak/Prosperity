<?php

namespace Modules\JarManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jar.dispatch.manage');
    }

    public function rules(): array
    {
        return [
            'jar_vehicle_id' => ['required', 'exists:jar_vehicles,id'],
            'jar_driver_id' => ['nullable', 'exists:jar_drivers,id'],
            'assigned_staff_id' => ['required', 'exists:users,id'],
            'route_area' => ['nullable', 'string', 'max:100'],
            'max_jars' => ['nullable', 'integer', 'min:1', 'max:80'],
        ];
    }
}
