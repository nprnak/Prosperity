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
        $filters = $request->validate([
            'log_name' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $logs = Activity::query()
            ->with('causer')
            ->when($filters['log_name'] ?? null, fn ($q, $name) => $q->where('log_name', $name))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(15)
            ->withQueryString()
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
            'logNames' => Activity::query()->select('log_name')->distinct()->orderBy('log_name')->pluck('log_name'),
            'filters' => $filters,
        ]);
    }
}
