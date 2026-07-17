<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\UserManagement\Repositories\UserRepository;

class AdminUsersController extends Controller
{
    public function __construct(private UserRepository $users)
    {
    }

    public function index(Request $request): Response
    {
        $this->users->ensureDefaultRoles();

        $roleFilter = $request->string('role')->toString();

        return Inertia::render('Admin/Users', [
            'users' => $this->users->listForAdmin($roleFilter ?: null),
            'roles' => $this->users->roles(),
            'selectedRole' => $roleFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->users->ensureDefaultRoles();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $user = $this->users->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->users->ensureDefaultRoles();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $attributes = ['name' => $validated['name'], 'email' => $validated['email']];

        if (! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        $this->users->update($user, $attributes);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return redirect()->route('admin.users')->withErrors([
                'delete' => 'You cannot delete your own account.',
            ]);
        }

        $this->users->destroy($user);

        return redirect()->route('admin.users');
    }
}
