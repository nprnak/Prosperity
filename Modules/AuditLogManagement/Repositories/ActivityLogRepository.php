<?php

namespace Modules\AuditLogManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogRepository extends Repository
{
    public function __construct(Activity $model)
    {
        parent::__construct($model);
    }

    /**
     * Activity log filtered by log name and date range, newest first.
     */
    public function filtered(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with('causer')
            ->when($filters['log_name'] ?? null, fn ($q, $name) => $q->where('log_name', $name))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function logNames(): Collection
    {
        return $this->query()->select('log_name')->distinct()->orderBy('log_name')->pluck('log_name');
    }
}
