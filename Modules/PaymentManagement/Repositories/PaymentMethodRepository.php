<?php

namespace Modules\PaymentManagement\Repositories;

use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Modules\PaymentManagement\Models\PaymentMethod;

class PaymentMethodRepository extends Repository
{
    public function __construct(PaymentMethod $model)
    {
        parent::__construct($model);
    }

    public function active(array $columns = ['*']): Collection
    {
        return $this->query()->active()->get($columns);
    }

    public function listForAdmin(): Collection
    {
        return $this->query()
            ->with('company:id,name,code')
            ->withCount('transactions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
