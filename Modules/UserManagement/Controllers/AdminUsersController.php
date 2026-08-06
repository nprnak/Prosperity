<?php

namespace Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use Modules\UserManagement\Repositories\UserRepository;
use App\Notifications\GeneratedPasswordNotification;

class AdminUsersController extends Controller
{
    public function __construct(private UserRepository $users) {}

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

        // Only super_admin may set a custom password during user creation.
        $passwordRule = $request->user()?->hasRole('super_admin') ? ['nullable', 'string', 'min:8'] : ['prohibited'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $passwordRule,
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        // If a non-super_admin created this user and didn't supply a password,
        // generate a random one so the account remains secure.
        $password = $validated['password'] ?? Str::random(12);

        $user = $this->users->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $password,
        ]);

        // Accept multiple roles
        $user->syncRoles($validated['roles']);

        // If password was auto-generated (creator didn't provide one), create a password reset
        // token and email the user a secure one-time link to set their password.
        if (! array_key_exists('password', $validated) || empty($validated['password'])) {
            $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
            $resetUrl = route('password.reset', ['token' => $token]) . '?email=' . urlencode($user->email);

            $user->notify(new GeneratedPasswordNotification($resetUrl, $request->user()?->name ?? null));

            // Audit log: record that an admin created the user and sent a password setup link
            if (function_exists('activity')) {
                activity()
                    ->causedBy($request->user())
                    ->performedOn($user)
                    ->withProperties(['action' => 'created_user', 'password_setup_sent' => true])
                    ->log('Admin created user and sent password setup link');
            }
        }

        return redirect()->route('admin.users')->with('success', "User {$user->name} created.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->users->ensureDefaultRoles();

        // Only super_admins may change another user's password from the admin UI.
        $passwordRule = $request->user()?->hasRole('super_admin') ? ['nullable', 'string', 'min:8'] : ['prohibited'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => $passwordRule,
            'roles' => ['required', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $attributes = ['name' => $validated['name'], 'email' => $validated['email']];

        // Apply password only if the current user is a super_admin and provided one.
        if ($request->user()?->hasRole('super_admin') && ! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        $this->users->update($user, $attributes);
        // Accept multiple roles
        $user->syncRoles($validated['roles']);

        return redirect()->route('admin.users')->with('success', "User {$user->name} updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return redirect()->route('admin.users')->withErrors([
                'delete' => 'You cannot delete your own account.',
            ]);
        }

        $name = $user->name;

        $this->users->destroy($user);

        return redirect()->route('admin.users')
            ->with('success', "User {$name} deleted.");
    }
}
