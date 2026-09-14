<?php

namespace Modules\JarManagement\Services;

use Illuminate\Validation\ValidationException;
use Modules\JarManagement\Models\Jar;

/** Jar-level search: given a code, the current state and full history. */
class JarTraceService
{
    public function trace(string $jarCode): Jar
    {
        $jar = Jar::where('jar_code', $jarCode)
            ->with([
                'currentBatch', 'currentVehicleLot', 'currentCustomer', 'registeredBy',
                'movements.batch', 'movements.lot', 'movements.customer',
                'movements.delivery', 'movements.receipt', 'movements.recordedBy',
            ])
            ->first();

        if (! $jar) {
            throw ValidationException::withMessages(['jar_code' => "No jar is registered with code {$jarCode}."]);
        }

        return $jar;
    }
}
