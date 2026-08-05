# Copilot instructions for Prosperity repository

This file provides concise, actionable guidance for future Copilot sessions working in this repository.

## Build, test, and lint (commands)
- Install dependencies:
  - PHP/composer: `composer install` (uses PHP ^8.2)
  - Node: `npm install`
- Build frontend: `npm run build` (Vite)
- Local multi-service dev (starts server, queue, vite, logs): `composer run dev`

- Run app locally (artisan):
  - `php artisan serve`
  - start worker: `php artisan queue:work` (or use `composer run dev` which starts queue listeners)

- Tests:
  - Run full test suite: `php artisan test`
  - Run a single test file: `php artisan test tests/Feature/SomeTest.php` or `php artisan test tests/Unit/SomeTest.php`
  - Run a single test method: `php artisan test --filter ClassName::methodName` or `php artisan test --filter methodName`
  - PHPUnit binary: `./vendor/bin/phpunit --filter ...` (phpunit.xml is present)
  - Note: phpunit.xml is configured to run tests with an in-memory SQLite database (no DB config required for tests).

- Lint / style:
  - PHP Pint is available: `./vendor/bin/pint` (no JS linter configured out-of-the-box).

## High-level architecture (big picture)
- Laravel 11 application with modular domain architecture under `Modules/`.
- Modules are described by a `module.php` manifest and auto-registered via `App\Providers\ModuleServiceProvider`.
  - Each module typically contains: Routes, Controllers, Models, Requests, Notifications, Database/Migrations, Resources/views, and Vue pages under `Vue/Pages`.
- Frontend: Laravel Breeze + Inertia + Vue 3 + Vite. Inertia pages are resolved first from `resources/js/Pages/`, then from `Modules/*/Vue/Pages` via the project's page resolver.
- Shared/core code lives in `app/` (User model, numbering services, base Controllers, policies). Domain logic (applications, payments, allotments, vouchers) lives in modules.
- Background jobs and queues are used for email notifications and long-running tasks (queue connection defaults to sync for tests).
- PDFs (vouchers) are generated with DomPDF; Excel exports use maatwebsite/excel. Permissions via spatie/laravel-permission; activity logging via spatie/laravel-activitylog.

## Key conventions and repository-specific patterns
- Modules convention:
  - `Modules/<Name>/module.php` declares module slug/name and providers.
  - Controllers namespace: `Modules\<Name>\Controllers`.
  - Module views are registered with a `<slug>::` namespace.
  - Vue/Inertia pages live under `Modules/<Name>/Vue/Pages` and are discovered by the resolver.
- Permissions & authorization:
  - Permissions are named like `application.submit`, `payment.record`, `payment.verify`, etc.; routes use `can:` middleware; FormRequests and Policies enforce object-level rules.
  - Permission list is exposed to the frontend via `Inertia` (e.g., `page.props.auth.permissions`) — front-end components rely on these for UI gating.
- Numbering and formatting:
  - `NumberGeneratorService` produces application and voucher numbering using a fiscal-year and padded sequences (PHL-{year}-{6-digit}).
- Seeders and environment:
  - Re-run `RolesAndPermissionsSeeder` after pulling to restore permission/role data; `AdminUserSeeder` seeds pre-verified staff accounts.
  - README lists local setup steps (migrations, seeders, `php artisan key:generate`).
- Testing environment:
  - phpunit.xml uses in-memory SQLite and configures test env variables (queue sync, array session/cache) — tests should run without DB setup.

## Useful repo pointers (where to look)
- Module loader/service provider: `App\Providers\ModuleServiceProvider`
- Inertia resolver: `resources/js/resolvePage.js` (or similar) to understand how pages are located
- Numbering service: search for `NumberGeneratorService` or `NumberingSequence` model
- Permission seeder: `database/seeders/RolesAndPermissionsSeeder.php`
- Tests: `tests/Unit` and `tests/Feature`
- CI / GitHub workflows: (none detected in repository root); if present, they would live in `.github/workflows/`.

---

For deeper details refer to README.md (root) for full module list and manual test checklist.

*Created: copilot-instructions.md*