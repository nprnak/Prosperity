<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Loads every module enabled in the config/modules.php registry.
 *
 * The registry is the single source of truth for which modules load and how
 * their routes are mounted (api prefix + middleware). A module may still ship
 * a module.php manifest for per-module extras: additional service providers
 * and path overrides for routes, migrations, views, and translations.
 *
 * For every enabled module this provider registers:
 *   - web routes   (Routes/web.php, wrapped in the "web" middleware group)
 *   - api routes   (Routes/api.php, mounted at api/{prefix} with the
 *                   configured middleware — route files must not repeat
 *                   the prefix)
 *   - migrations   (Database/Migrations)
 *   - views        (Resources/views — both as a namespace and a plain location)
 *   - translations (Resources/lang, namespaced by module slug)
 *   - extra service providers listed in the manifest
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->modules() as $module) {
            foreach ($module['providers'] ?? [] as $provider) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->modules() as $module) {
            $this->bootModule($module);
        }
    }

    /**
     * Enabled modules from the registry, merged with each module's optional
     * module.php manifest (registry values win).
     *
     * @return array<string, array>
     */
    protected function modules(): array
    {
        if (! config('modules.enabled', true)) {
            return [];
        }

        static $modules = null;

        if ($modules !== null) {
            return $modules;
        }

        $modules = [];
        $basePath = config('modules.path');

        foreach (config('modules.modules', []) as $name => $settings) {
            if (! ($settings['enabled'] ?? true)) {
                continue;
            }

            $path = "{$basePath}/{$name}";

            if (! is_dir($path)) {
                continue;
            }

            $manifest = is_file("{$path}/module.php") ? require "{$path}/module.php" : [];
            $manifest = is_array($manifest) ? $manifest : [];

            $modules[$name] = array_merge($manifest, $settings, [
                'name' => $name,
                'base_path' => $path,
            ]);
        }

        return $modules;
    }

    protected function bootModule(array $module): void
    {
        $base = $module['base_path'];
        $slug = Str::kebab($module['name']);

        $webRoutes = $module['routes']['web'] ?? "{$base}/Routes/web.php";
        if (is_file($webRoutes)) {
            Route::middleware('web')->group($webRoutes);
        }

        $apiRoutes = $module['routes']['api'] ?? "{$base}/Routes/api.php";
        if (is_file($apiRoutes)) {
            Route::prefix('api/'.trim($module['prefix'] ?? "v1/{$slug}", '/'))
                ->middleware($module['middleware'] ?? ['api'])
                ->group($apiRoutes);
        }

        $migrations = $module['migrations'] ?? "{$base}/Database/Migrations";
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $views = $module['views'] ?? "{$base}/Resources/views";
        if (is_dir($views)) {
            $this->loadViewsFrom($views, $slug);
            View::addLocation($views);
        }

        $translations = $module['translations'] ?? "{$base}/Resources/lang";
        if (is_dir($translations)) {
            $this->loadTranslationsFrom($translations, $slug);
        }
    }
}
