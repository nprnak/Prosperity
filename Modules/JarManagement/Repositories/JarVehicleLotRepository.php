<?php

namespace Modules\JarManagement\Repositories;

use App\Repositories\Repository;
use Modules\JarManagement\Models\JarVehicleLot;

class JarVehicleLotRepository extends Repository
{
    public function __construct(JarVehicleLot $model)
    {
        parent::__construct($model);
    }

    public function paginateLatest(int $perPage = 20)
    {
        return $this->query()
            ->with(['vehicle', 'driver', 'assignedStaff'])
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** The lots currently assigned to a field-staff user that they can still act on. */
    public function activeForStaff(int $userId)
    {
        return $this->query()
            ->where('assigned_staff_id', $userId)
            ->whereIn('status', ['loading', 'dispatched'])
            ->with(['vehicle', 'driver', 'items.jar'])
            ->orderByDesc('id')
            ->get();
    }
}
