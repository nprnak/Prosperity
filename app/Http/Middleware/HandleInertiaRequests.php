<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Eager load roles if user is authenticated
        if ($user) {
            $user->load('roles');
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                // Flat permission names so Vue can gate UI with
                // page.props.auth.permissions.includes('...').
                'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
        ];
    }
}
