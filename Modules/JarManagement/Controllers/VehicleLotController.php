<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\JarManagement\Enums\VehicleLotStatus;
use Modules\JarManagement\Models\JarVehicleLot;
use Modules\JarManagement\Repositories\JarDriverRepository;
use Modules\JarManagement\Repositories\JarVehicleLotRepository;
use Modules\JarManagement\Repositories\JarVehicleRepository;
use Modules\JarManagement\Requests\LoadJarRequest;
use Modules\JarManagement\Requests\StoreVehicleLotRequest;
use Modules\JarManagement\Services\DispatchService;

class VehicleLotController extends Controller
{
    public function __construct(
        private readonly JarVehicleLotRepository $lots,
        private readonly JarVehicleRepository $vehicles,
        private readonly JarDriverRepository $drivers,
        private readonly DispatchService $service,
    ) {}

    public function index()
    {
        return Inertia::render('JarManagement/Dispatch/Lots', [
            'lots' => $this->lots->paginateLatest(),
            'vehicles' => $this->vehicles->all(['id', 'vehicle_number', 'name', 'max_jars']),
            'drivers' => $this->drivers->all(['id', 'name', 'phone']),
            'staffOptions' => User::permission('jar.delivery.manage')->get(['id', 'name']),
            'statusOptions' => VehicleLotStatus::options(),
        ]);
    }

    public function store(StoreVehicleLotRequest $request)
    {
        $lot = $this->service->createLot($request->user(), $request->validated());

        return redirect()->route('jar.dispatch.show', $lot)->with('success', "Lot {$lot->lot_code} created — load jars to dispatch it.");
    }

    public function show(JarVehicleLot $lot)
    {
        Gate::authorize('view', $lot);

        $lot->load(['vehicle', 'driver', 'assignedStaff', 'items.jar', 'items.loadedBy']);

        return Inertia::render('JarManagement/Dispatch/LotShow', [
            'lot' => $lot,
        ]);
    }

    public function loadJar(LoadJarRequest $request, JarVehicleLot $lot)
    {
        Gate::authorize('manage', $lot);

        $this->service->loadJar($lot, $request->string('jar_code')->trim()->upper()->toString(), $request->user());

        return back()->with('success', 'Jar loaded onto lot.');
    }

    public function dispatch(JarVehicleLot $lot)
    {
        Gate::authorize('manage', $lot);

        $this->service->dispatch($lot, request()->user());

        return back()->with('success', "Lot {$lot->lot_code} dispatched.");
    }
}
