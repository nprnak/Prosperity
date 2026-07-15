<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Discovers and registers every enabled module found under Modules/.
 *
 * Each module is self-contained and declares itself through a module.php
 * manifest. For every enabled module this provider registers:
 *   - web routes   (wrapped in the "web" middleware group)
 *   - api routes   (prefixed api/v1/{slug}, "api" middleware group)
 *   - migrations   (Database/Migrations)
 *   - views        (Resources/views — both as a namespace and a plain location)
 *   - translations (Resources/lang, namespaced by module slug)
 *   - extra service providers listed in the manifest
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->manifests() as $manifest) {
            foreach ($manifest['providers'] ?? [] as $provider) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->manifests() as $manifest) {
            $this->bootModule($manifest);
        }
    }

    /**
     * @return array<int, array>
     */
    protected function manifests(): array
    {
        if (! config('modules.enabled', true)) {
            return [];
        }

        static $manifests = null;

        if ($manifests !== null) {
            return $manifests;
        }

        $manifests = [];

        foreach (glob(config('modules.path').'/*/module.php') ?: [] as $file) {
            $manifest = require $file;

            if (! is_array($manifest) || ! ($manifest['enabled'] ?? true)) {
                continue;
            }

            $manifest['base_path'] = dirname($file);
            $manifests[] = $manifest;
        }

        return $manifests;
    }

    protected function bootModule(array $manifest): void
    {
        $base = $manifest['base_path'];
        $slug = Str::kebab($manifest['name'] ?? basename($base));

        $webRoutes = $manifest['routes']['web'] ?? "{$base}/Routes/web.php";
        if (is_file($webRoutes)) {
            Route::middleware('web')->group($webRoutes);
        }

        $apiRoutes = $manifest['routes']['api'] ?? "{$base}/Routes/api.php";
        if (is_file($apiRoutes)) {
            Route::prefix($manifest['api_prefix'] ?? "api/v1/{$slug}")
                ->middleware('api')
                ->group($apiRoutes);
        }

        $migrations = $manifest['migrations'] ?? "{$base}/Database/Migrations";
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $views = $manifest['views'] ?? "{$base}/Resources/views";
        if (is_dir($views)) {
            $this->loadViewsFrom($views, $slug);
            View::addLocation($views);
        }

        $translations = $manifest['translations'] ?? "{$base}/Resources/lang";
        if (is_dir($translations)) {
            $this->loadTranslationsFrom($translations, $slug);
        }
    }
}
