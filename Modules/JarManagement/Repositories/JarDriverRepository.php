<?php

namespace Modules\JarManagement\Repositories;

use App\Repositories\Repository;
use Modules\JarManagement\Models\JarDriver;

class JarDriverRepository extends Repository
{
    public function __construct(JarDriver $model)
    {
        parent::__construct($model);
    }
}
