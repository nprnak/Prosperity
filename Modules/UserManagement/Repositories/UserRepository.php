<?php

namespace Modules\UserManagement\Repositories;

use App\Models\User;
use App\Repositories\Repository;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class UserRepository extends Repository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Users for the admin list, optionally filtered by role name.
     */
    public function listForAdmin(?string $role = null): Collection
    {
        return $this->query()
            ->with('roles:id,name')
            ->when(filled($role), fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $role)))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at']);
    }

    public function listByRole(string $role): Collection
    {
        return User::role($role)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at']);
    }

    public function countByRole(string $role): int
    {
        return User::role($role)->count();
    }

    public function roles(): Collection
    {
        return Role::query()->orderBy('name')->get(['id', 'name']);
    }

    public function ensureDefaultRoles(): void
    {
        foreach (['admin', 'finance_staff', 'approver', 'applicant'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }
}
