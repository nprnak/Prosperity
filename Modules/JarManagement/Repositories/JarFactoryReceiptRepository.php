<?php

namespace Modules\JarManagement\Repositories;

use App\Repositories\Repository;
use Modules\JarManagement\Models\JarFactoryReceipt;

class JarFactoryReceiptRepository extends Repository
{
    public function __construct(JarFactoryReceipt $model)
    {
        parent::__construct($model);
    }

    public function paginateLatest(int $perPage = 20)
    {
        return $this->query()
            ->with(['lot.vehicle', 'receivedBy'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
