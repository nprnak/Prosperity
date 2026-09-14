<?php

namespace Modules\JarManagement\Repositories;

use App\Repositories\Repository;
use Modules\JarManagement\Models\JarProductionBatch;

class JarProductionBatchRepository extends Repository
{
    public function __construct(JarProductionBatch $model)
    {
        parent::__construct($model);
    }

    public function paginateLatest(int $perPage = 20)
    {
        return $this->query()->withCount('items')->orderByDesc('id')->paginate($perPage);
    }
}
