<?php

namespace Modules\AuditLogManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class AdminLogsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $logs = Activity::latest()->paginate(10);

        return Inertia::render('Admin/Logs', [
            'logs' => $logs,
        ]);
    }
}
