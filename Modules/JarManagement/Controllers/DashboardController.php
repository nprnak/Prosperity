<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\JarManagement\Services\DashboardMetricsService;

class DashboardController extends Controller
{
    public function index(DashboardMetricsService $metrics)
    {
        return Inertia::render('JarManagement/Dashboard', [
            'summary' => $metrics->summary(),
            'vehicleStatus' => $metrics->vehicleStatus(),
            'productionStatus' => $metrics->productionStatus(),
            'jarConditionBreakdown' => $metrics->jarConditionBreakdown(),
            'reconciliationBreakdown' => $metrics->reconciliationBreakdown(),
        ]);
    }
}
