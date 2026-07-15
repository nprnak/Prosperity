<?php

namespace Modules\ReportManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminReportsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        return Inertia::render('Admin/Reports', [
            'reports' => [],
        ]);
    }
}
