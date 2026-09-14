<?php

namespace Modules\JarManagement\Repositories;

use App\Repositories\Repository;
use Modules\JarManagement\Models\JarDelivery;

class JarDeliveryRepository extends Repository
{
    public function __construct(JarDelivery $model)
    {
        parent::__construct($model);
    }

    public function paginateLatest(int $perPage = 20)
    {
        return $this->query()
            ->with(['customer', 'lot.vehicle', 'staff'])
            ->orderByDesc('delivered_at')
            ->paginate($perPage);
    }

    public function forStaff(int $userId, int $perPage = 20)
    {
        return $this->query()
            ->where('staff_id', $userId)
            ->with(['customer', 'lot'])
            ->orderByDesc('delivered_at')
            ->paginate($perPage);
    }

    /** Excess or ownership-flagged returns still awaiting a manager's decision. */
    public function flaggedReturns()
    {
        return $this->model->newQuery()
            ->whereHas('returns', fn ($q) => $q->where('verification_status', 'flagged'))
            ->with(['customer', 'returns' => fn ($q) => $q->where('verification_status', 'flagged')->with('jar')])
            ->orderByDesc('delivered_at')
            ->get();
    }
}
