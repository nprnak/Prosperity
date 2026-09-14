<?php

namespace Modules\JarManagement\Repositories;

use App\Repositories\Repository;
use Modules\JarManagement\Models\JarVehicle;

class JarVehicleRepository extends Repository
{
    public function __construct(JarVehicle $model)
    {
        parent::__construct($model);
    }
}
