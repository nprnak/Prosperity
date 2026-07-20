<?php

namespace Modules\AuditLogManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\AuditLogManagement\Repositories\ActivityLogRepository;
use Spatie\Activitylog\Models\Activity;

class AdminLogsController extends Controller
{
    public function __construct(private ActivityLogRepository $logs) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'log_name' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $logs = $this->logs->filtered($filters)
            ->through(fn (Activity $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at->format('Y-m-d H:i'),
                'causer' => $log->causer?->name,
                'log_name' => $log->log_name,
                'event' => $log->event,
                'description' => $log->description,
                'ip' => $log->properties['ip'] ?? null,
                'user_agent' => $log->properties['user_agent'] ?? null,
                'properties' => $log->properties,
            ]);

        return Inertia::render('Admin/Logs', [
            'logs' => $logs,
            'logNames' => $this->logs->logNames(),
            'filters' => $filters,
        ]);
    }
}
