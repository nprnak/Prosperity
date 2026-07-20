<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Global base repository. Module repositories extend this class and only add
 * queries specific to their own domain — generic CRUD lives here once.
 */
abstract class Repository
{
    /**
     * Relations to eager-load on the next query. Reset after each call.
     *
     * @var array<int|string, mixed>
     */
    protected array $with = [];

    public function __construct(protected Model $model) {}

    public function all(array $columns = ['*']): Collection
    {
        return $this->query()->get($columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage, $columns);
    }

    public function find(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->query()->find($id, $columns);
    }

    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->query()->findOrFail($id, $columns);
    }

    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(Model|int|string $model, array $attributes): Model
    {
        $model = $model instanceof Model ? $model : $this->findOrFail($model);

        $model->fill($attributes)->save();

        return $model;
    }

    public function destroy(Model|int|string $model): bool
    {
        $model = $model instanceof Model ? $model : $this->find($model);

        return (bool) $model?->delete();
    }

    /**
     * Queue relations for eager loading on the next query.
     */
    public function with(array|string $relations): static
    {
        $this->with = array_merge($this->with, (array) $relations);

        return $this;
    }

    /**
     * Fresh query builder with any queued eager loads applied.
     */
    public function query(): Builder
    {
        $query = $this->model->newQuery()->with($this->with);

        $this->with = [];

        return $query;
    }

    public function model(): Model
    {
        return $this->model;
    }
}
