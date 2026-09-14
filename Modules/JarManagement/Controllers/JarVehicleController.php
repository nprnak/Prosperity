<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\JarManagement\Models\JarDriver;
use Modules\JarManagement\Models\JarVehicle;
use Modules\JarManagement\Repositories\JarDriverRepository;
use Modules\JarManagement\Repositories\JarVehicleRepository;
use Modules\JarManagement\Requests\StoreJarDriverRequest;
use Modules\JarManagement\Requests\StoreJarVehicleRequest;

class JarVehicleController extends Controller
{
    public function __construct(
        private readonly JarVehicleRepository $vehicles,
        private readonly JarDriverRepository $drivers,
    ) {}

    public function index()
    {
        return Inertia::render('JarManagement/Vehicles/Index', [
            'vehicles' => $this->vehicles->all(),
            'drivers' => $this->drivers->all(),
        ]);
    }

    public function storeVehicle(StoreJarVehicleRequest $request)
    {
        $vehicle = JarVehicle::create($request->validated());

        return back()->with('success', "Vehicle {$vehicle->vehicle_number} added.");
    }

    public function updateVehicle(StoreJarVehicleRequest $request, JarVehicle $jarVehicle)
    {
        $jarVehicle->update($request->validated());

        return back()->with('success', "Vehicle {$jarVehicle->vehicle_number} updated.");
    }

    public function storeDriver(StoreJarDriverRequest $request)
    {
        $driver = JarDriver::create($request->validated());

        return back()->with('success', "Driver {$driver->name} added.");
    }

    public function updateDriver(StoreJarDriverRequest $request, JarDriver $jarDriver)
    {
        $jarDriver->update($request->validated());

        return back()->with('success', "Driver {$jarDriver->name} updated.");
    }
}
