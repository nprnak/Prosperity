<?php

namespace Modules\ReportManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminReportsController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Reports', [
            'reports' => [],
        ]);
    }
}
