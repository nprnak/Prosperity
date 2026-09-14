<?php

namespace Modules\JarManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Models\Jar;

class JarRepository extends Repository
{
    public function __construct(Jar $model)
    {
        parent::__construct($model);
    }

    public function findByCode(string $jarCode): ?Jar
    {
        return $this->query()->where('jar_code', $jarCode)->first();
    }

    public function withStatus(JarStatus $status)
    {
        return $this->query()->where('status', $status);
    }

    public function search(?string $term, ?JarStatus $status, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query()
            ->when($term, fn ($q) => $q->where('jar_code', 'like', "%{$term}%"))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
