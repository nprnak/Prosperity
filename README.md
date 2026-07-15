# Prosperity MIS

Laravel 11 MIS for Prosperity Holdings Ltd with Breeze (Vue + Inertia), role-based workflow, application/payment approval flow, PDF voucher generation, and shareholder register export.

## Implemented modules

- Laravel 11 + Breeze (Vue/Inertia) bootstrap
- Enforced email verification (`MustVerifyEmail` + `verified` middleware on all
  module routes); registration emails a signed verification link, unverified
  users are held at `/verify-email`. Seeded staff accounts are pre-verified
  (re-running `AdminUserSeeder` backfills existing rows)
- Roles: `applicant`, `finance_staff`, `approver`, `admin` with granular permissions
  (`application.submit`, `payment.record`, `payment.verify`, `application.approve`,
  `allotment.manage`, `user.manage`, `report.view`, `audit.view`, …) — see
  `RolesAndPermissionsSeeder`. Routes are gated with `can:` middleware, FormRequests
  check permissions, and object-level rules live in module Policies
  (`ShareApplicationPolicy` ownership on submit, `VoucherPolicy` ownership on download).
  Permission names are shared with Vue via Inertia (`page.props.auth.permissions`).
  After pulling, re-run `php artisan db:seed --class=RolesAndPermissionsSeeder`.
- Domain schema + models:
  - `applicants`
  - `share_applications`
  - `payment_transactions` (soft deletes)
  - `share_allotments`
  - `vouchers` (soft deletes)
  - `numbering_sequences`
- Transaction-safe `NumberGeneratorService`:
  - Application: `PHL-{fiscal_year}-{6-digit}`
  - Receipt/Voucher: sequential padded numbers
- Applicant profile (KYC) approval workflow: applicants complete their profile and
  submit it for review (`profile_status`: draft → submitted → approved/rejected);
  users with `profile.review` (approver, admin) work a review queue at
  `/applicants/review` with approve/reject + reason; applicants are emailed the
  outcome and can only draft/submit share applications once approved
- Multi-company share offerings: admins manage companies and their offerings
  (fiscal year, rate, min/max shares, open/close window, status lifecycle) at
  `/admin/companies` (`company.manage` permission). Applicants apply against an
  open offering — the wizard snapshots the rate server-side (client-supplied
  amounts are ignored), enforces per-offering share limits, and re-checks the
  offering window at submission. Seeded with Prosperity's own offering
  (`CompanySeeder`)
- Payment methods management: admins configure methods (bank/wallet details,
  instructions, QR image upload) at `/admin/payment-methods`
  (`payment-method.manage`); applicants see a "How to Pay" section on the wizard
  with QR + instructions; finance staff tag recorded payments with a method
  (active methods only). Seeded defaults via `PaymentMethodSeeder`
- Reports: `/admin/reports` (`report.view`) filters applications by company,
  offering, status, payment method, and date range with live summary totals
  (applications, shares, declared, verified payments, allotted) and exports the
  filtered set to Excel, CSV, or PDF
- Applicant 5-step scaffolded wizard (draft save + submit)
- Finance dashboard:
  - submitted/payment-pending lists
  - multiple payment records per application
  - verify/reject payment actions
- Approver dashboard:
  - review `payment_verified` applications
  - approve -> generate voucher PDF
  - reject with reason
- Voucher PDF template (DomPDF) including amount in words service
- Share allotment + shareholder register + Excel export
- Admin dashboard cards + Chart.js capital-over-time chart
- Queued email notifications for submit / payment verified / approved / rejected
- Activity logging traits on share applications and payment transactions

## Stack

- Laravel 11
- Breeze (Vue 3 + Inertia)
- MySQL
- spatie/laravel-permission
- spatie/laravel-activitylog
- barryvdh/laravel-dompdf
- maatwebsite/excel

## Local setup

```bash
git clone https://github.com/nprnak/Prosperity.git
cd Prosperity

cp .env.example .env
# set DB_* values for MySQL

composer install --no-security-blocking
npm install
npm run build

php artisan key:generate
php artisan migrate
php artisan db:seed

php artisan queue:work
php artisan serve
```

## Useful commands

```bash
php artisan test
composer run dev
php artisan make:module CompanyManagement   # scaffold a new module
```

## Modular architecture

Domain code lives in self-contained modules under `Modules/`, each declared by a
`module.php` manifest and auto-discovered by `App\Providers\ModuleServiceProvider`
(web/api routes, migrations, views, translations, extra providers). Shared core —
`User`, `NumberingSequence`, base `Controller`, Breeze auth, and the number/amount
services — stays in `app/`.

```
Modules/<Name>/
├── module.php            # manifest: name, enabled, providers
├── Routes/web.php        # module routes (same names/URIs as before)
├── Controllers/          # namespace Modules\<Name>\Controllers
├── Models/
├── Requests/
├── Notifications/
├── Database/Migrations/  # auto-loaded; new tables go here
├── Resources/views/      # blade views (registered as plain location + "<slug>::" namespace)
└── Vue/Pages/            # Inertia pages, resolved by resources/js/resolvePage.js
```

Current modules: `UserManagement`, `ApplicantManagement`, `ApplicationManagement`,
`PaymentManagement`, `ApprovalManagement`, `AllotmentManagement`, `VoucherManagement`,
`Dashboard`, `ReportManagement`, `SettingsManagement`, `AuditLogManagement`.

Inertia page names are unchanged (`Inertia::render('Admin/Users')`); the resolver
finds the component in `resources/js/Pages` first, then any module's `Vue/Pages`.
Tailwind scans `Modules/**/Vue` and module blade views (see `tailwind.config.js`).

## Workflow routes

- Applicant wizard: `/applications/wizard`
- Profile review queue: `/applicants/review`
- Finance dashboard: `/finance/dashboard`
- Approver dashboard: `/approver/dashboard`
- Shareholder register: `/allotments/register`
- Admin dashboard: `/admin/dashboard`

## Manual test checklist

1. Register a user and confirm `applicant` role assignment.
2. Save draft in wizard and verify draft persists.
3. Submit application and verify status -> `submitted` and email queued.
4. Login as finance staff/admin; record two partial payments for one application.
5. Verify payments; confirm application moves to `payment_verified` only after verified total reaches declared total.
6. Login as approver/admin; approve application and verify voucher PDF is generated/stored and approval email is queued.
7. Reject another application and verify rejection reason + email.
8. Create share allotment from approved application; verify status -> `allotted`.
9. Open register and export Excel.
10. Open admin dashboard and verify summary metrics + chart data.
