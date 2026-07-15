<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;
use Modules\SettingsManagement\Models\Setting;
use Throwable;

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
            'settings' => $this->publicSettings(),
            'notifications' => $user ? [
                'unread_count' => $user->unreadNotifications()->count(),
                'recent' => $user->notifications()->latest()->limit(10)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? 'Notification',
                    'message' => $n->data['message'] ?? '',
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at->diffForHumans(),
                ]),
            ] : null,
        ];
    }

    /**
     * Admin-managed site settings that are safe to expose to every page.
     * Empty on fresh installs where the settings table does not exist yet.
     *
     * @return array<string, string|null>
     */
    protected function publicSettings(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            $settings = Setting::allCached();
        } catch (Throwable) {
            return [];
        }

        return [
            'org_name' => $settings['org_name'] ?? 'Prosperity',
            'contact_email' => $settings['contact_email'] ?? null,
            'support_phone' => $settings['support_phone'] ?? null,
            'currency_code' => $settings['currency_code'] ?? 'NPR',
            'currency_symbol' => $settings['currency_symbol'] ?? 'Rs.',
            'max_upload_size_kb' => $settings['max_upload_size_kb'] ?? '5120',
        ];
    }
}
