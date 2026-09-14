<?php

namespace Modules\JarManagement\Services;

use Illuminate\Support\Carbon;
use Modules\JarManagement\Enums\JarMovementEvent;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Enums\VehicleLotStatus;
use Modules\JarManagement\Models\Jar;
use Modules\JarManagement\Models\JarCustomer;
use Modules\JarManagement\Models\JarDelivery;
use Modules\JarManagement\Models\JarMovement;
use Modules\JarManagement\Models\JarProductionBatch;
use Modules\JarManagement\Models\JarVehicleLot;

class DashboardMetricsService
{
    /**
     * "Today" counts are read off the jar_movements ledger rather than the
     * transactional tables directly, since the ledger already has exactly
     * one row per jar per lifecycle event — counting distinct jars there
     * avoids re-deriving the same logic per KPI.
     *
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $today = Carbon::today();

        $eventCounts = JarMovement::whereBetween('occurred_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->selectRaw('event_type, count(distinct jar_id) as cnt')
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type');

        return [
            'produced_today' => (int) ($eventCounts[JarMovementEvent::AddedToBatch->value] ?? 0),
            'dispatched_today' => (int) ($eventCounts[JarMovementEvent::Dispatched->value] ?? 0),
            'delivered_today' => (int) ($eventCounts[JarMovementEvent::Delivered->value] ?? 0),
            'empty_returned_today' => (int) ($eventCounts[JarMovementEvent::CollectedEmpty->value] ?? 0),
            'factory_accepted_today' => (int) ($eventCounts[JarMovementEvent::FactoryAccepted->value] ?? 0),
            'quarantined_today' => (int) ($eventCounts[JarMovementEvent::FactoryQuarantined->value] ?? 0),
            // "In vehicles" = physically on a vehicle right now: loaded/out
            // for delivery, or collected empty but not yet back at the
            // factory. WithCustomer is deliberately excluded — that jar is
            // at the customer's premises, not in the vehicle.
            'currently_in_vehicles' => Jar::whereIn('status', [JarStatus::Dispatched, JarStatus::EmptyCollected])->count(),
            'customer_outstanding_jars' => (int) JarCustomer::sum('jars_outstanding'),
            'revenue_today' => (float) JarDelivery::whereDate('delivered_at', $today)->sum('total_amount'),
            'active_trips' => JarVehicleLot::where('status', VehicleLotStatus::Dispatched)->count(),
        ];
    }

    public function vehicleStatus()
    {
        return JarVehicleLot::whereDate('created_at', Carbon::today())
            ->with(['vehicle', 'driver', 'assignedStaff'])
            ->withCount('items')
            ->orderByDesc('id')
            ->get()
            ->map(fn (JarVehicleLot $lot) => [
                'lot_code' => $lot->lot_code,
                'vehicle' => $lot->vehicle?->vehicle_number,
                'driver' => $lot->driver?->name,
                'staff' => $lot->assignedStaff?->name,
                'status' => $lot->status,
                'jars_loaded' => $lot->items_count,
                'route_area' => $lot->route_area,
            ]);
    }

    public function productionStatus()
    {
        return JarProductionBatch::whereDate('production_date', Carbon::today())
            ->withCount('items')
            ->orderByDesc('id')
            ->get(['id', 'batch_code', 'status']);
    }

    public function jarConditionBreakdown(): array
    {
        return Jar::selectRaw('condition, count(*) as cnt')
            ->groupBy('condition')
            ->pluck('cnt', 'condition')
            ->toArray();
    }

    /** Today's deliveries grouped by how the filled/empty count worked out. */
    public function reconciliationBreakdown(): array
    {
        return JarDelivery::whereDate('delivered_at', Carbon::today())
            ->selectRaw('reconciliation_status, count(*) as cnt')
            ->groupBy('reconciliation_status')
            ->pluck('cnt', 'reconciliation_status')
            ->toArray();
    }
}
