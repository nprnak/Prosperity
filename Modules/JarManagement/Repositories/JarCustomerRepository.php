<?php

namespace Modules\JarManagement\Repositories;

use App\Repositories\Repository;
use Modules\JarManagement\Models\JarCustomer;

class JarCustomerRepository extends Repository
{
    public function __construct(JarCustomer $model)
    {
        parent::__construct($model);
    }

    public function search(?string $term, int $perPage = 20)
    {
        return $this->query()
            ->when($term, fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /** Lightweight list for the delivery screen's customer picker (typeahead). */
    public function quickSearch(string $term, int $limit = 10)
    {
        return $this->query()
            ->where('active', true)
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
            ->limit($limit)
            ->get(['id', 'name', 'phone', 'type', 'default_price', 'jars_outstanding']);
    }
}
