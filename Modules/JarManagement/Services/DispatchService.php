<?php

namespace Modules\JarManagement\Services;

use App\Models\User;
use App\Services\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\JarManagement\Enums\JarLotItemStatus;
use Modules\JarManagement\Enums\JarMovementEvent;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Enums\VehicleLotStatus;
use Modules\JarManagement\Models\Jar;
use Modules\JarManagement\Models\JarLotItem;
use Modules\JarManagement\Models\JarVehicleLot;

class DispatchService
{
    public function __construct(
        private readonly NumberGeneratorService $numbers,
        private readonly JarMovementRecorder $movements,
    ) {}

    public function createLot(User $by, array $attributes): JarVehicleLot
    {
        return JarVehicleLot::create([
            'lot_code' => $this->numbers->generateJarLotCode(),
            'jar_vehicle_id' => $attributes['jar_vehicle_id'],
            'jar_driver_id' => $attributes['jar_driver_id'] ?? null,
            'assigned_staff_id' => $attributes['assigned_staff_id'],
            'route_area' => $attributes['route_area'] ?? null,
            'max_jars' => $attributes['max_jars'] ?? 80,
            'status' => VehicleLotStatus::Loading,
            'created_by' => $by->id,
        ]);
    }

    /**
     * Enforces the flowchart's 80-jar cap with a row lock on the lot, the
     * same concurrency idiom NumberGeneratorService uses for sequences —
     * two staff scanning into the same lot at once must still not exceed it.
     */
    public function loadJar(JarVehicleLot $lot, string $jarCode, User $by): JarLotItem
    {
        return DB::transaction(function () use ($lot, $jarCode, $by) {
            $lockedLot = JarVehicleLot::whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if ($lockedLot->status !== VehicleLotStatus::Loading) {
                throw ValidationException::withMessages(['jar_code' => 'This lot is no longer accepting jars.']);
            }

            $loadedCount = JarLotItem::where('jar_vehicle_lot_id', $lockedLot->id)->count();

            if ($loadedCount >= $lockedLot->max_jars) {
                throw ValidationException::withMessages([
                    'jar_code' => "This lot already has the maximum {$lockedLot->max_jars} jars loaded.",
                ]);
            }

            $jar = Jar::where('jar_code', $jarCode)->first();

            if (! $jar) {
                throw ValidationException::withMessages(['jar_code' => "No jar is registered with code {$jarCode}."]);
            }

            if ($jar->status !== JarStatus::Available) {
                throw ValidationException::withMessages([
                    'jar_code' => "Jar {$jarCode} is \"{$jar->status->labelEn()}\", not available for dispatch.",
                ]);
            }

            if (JarLotItem::where('jar_vehicle_lot_id', $lockedLot->id)->where('jar_id', $jar->id)->exists()) {
                throw ValidationException::withMessages(['jar_code' => "Jar {$jarCode} is already loaded on this lot."]);
            }

            $item = JarLotItem::create([
                'jar_vehicle_lot_id' => $lockedLot->id,
                'jar_id' => $jar->id,
                'status' => JarLotItemStatus::Loaded,
                'loaded_by' => $by->id,
                'loaded_at' => now(),
            ]);

            $jar->update(['current_vehicle_lot_id' => $lockedLot->id]);

            $this->movements->record(
                $jar, JarMovementEvent::Loaded, JarStatus::Available, JarStatus::Available, $by,
                ['jar_vehicle_lot_id' => $lockedLot->id],
            );

            return $item;
        });
    }

    public function dispatch(JarVehicleLot $lot, User $by): JarVehicleLot
    {
        return DB::transaction(function () use ($lot, $by) {
            $lockedLot = JarVehicleLot::whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if ($lockedLot->status !== VehicleLotStatus::Loading) {
                throw ValidationException::withMessages(['lot' => 'This lot has already been dispatched.']);
            }

            $items = JarLotItem::where('jar_vehicle_lot_id', $lockedLot->id)->with('jar')->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['lot' => 'Load at least one jar before dispatching.']);
            }

            $lockedLot->update(['status' => VehicleLotStatus::Dispatched, 'dispatched_at' => now()]);

            $items->each(function (JarLotItem $item) use ($lockedLot, $by) {
                $jar = $item->jar;
                $jar->update(['status' => JarStatus::Dispatched]);
                $this->movements->record(
                    $jar, JarMovementEvent::Dispatched, JarStatus::Available, JarStatus::Dispatched, $by,
                    ['jar_vehicle_lot_id' => $lockedLot->id],
                );
            });

            return $lockedLot->fresh();
        });
    }
}
