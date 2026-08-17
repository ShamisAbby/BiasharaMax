# BiasharaMax — Backend

One Laravel app serving three guards/route-groups from the same models, services and
migrations: the REST API (`routes/api.php`, Sanctum, consumed by `../desktop-app`), the
client web app (`routes/web.php`, `web` guard), and the admin/platform dashboard
(`routes/platform.php`, `platform` guard). See `../docs/ADR/0001-consolidation.md` for
why these stay one codebase rather than three.

## Stack

- **Backend:** Laravel 12, PHP 8.4+
- **Frontend (web app today):** React, TypeScript, Inertia.js, TailwindCSS — being
  migrated to Livewire + Blade per `../docs/ADR/0001-consolidation.md`.
- **Admin dashboard today:** Inertia + React under the `Platform` module — being migrated
  to Filament per the same ADR.
- **Database:** PostgreSQL today; migrating to MySQL 8 per the ADR.
- **Cache / Queue:** Redis, Laravel Horizon
- **Auth:** Laravel Sanctum (session-based for the web app and admin, device-scoped
  tokens with the `desktop` ability for the Flutter client)

## Architecture

The application is organized by business domain rather than technical layer:

```
app/Domain/
  Authentication/   Login, registration support, profile self-service, employee invitation acceptance
  Business/          Business registration, settings, branches, warehouses, employees, tenant model
  RBAC/               Roles & permissions
  Subscription/      Plans, trials, subscription status
  Licensing/          Device licences, activation, offline certificates (for the desktop client)
  Inventory/          Products, taxonomy, multi-warehouse stock, the stock_movements ledger,
                       adjustments, transfers, physical counts, bulk import/export
  Purchasing/         Suppliers, goods received
  Sales/              Sales, sale items, returns
  Finance/            Chart of accounts, double-entry journal, auto-posting, bank
                       reconciliation, fixed assets, payment gateways — the ledger.
  Accounting/         Cash-basis expense/income tracking — being merged into Finance
                       per docs/ADR/0001-consolidation.md.
  CRM / Payroll / Platform / Reports / Localization / ModuleManagement / Monitoring /
  Notifications / Integrations / AiInsights / Backup / Security / Settings / Support /
  Website / WebsiteTemplates / Developer / Shared
```

Each module follows the same internal shape: `Models/`, `Services/`, `Http/Controllers/`,
`Http/Requests/`, `Http/Resources/`, `Policies/`. Controllers stay thin; business logic
lives in Services; authorization lives in Policies.

### The inventory ledger

Every stock-quantity change in the platform — regardless of which module triggers it —
goes through `App\Modules\Inventory\Services\StockMovementService::record()`. It
atomically updates the live `inventories` row and appends an immutable entry to
`stock_movements` (quantity_before/quantity_after snapshots, never updated or deleted —
`StockMovement::delete()` throws). Adjustments, transfers and physical counts are all
draft/pending → completed workflows that call into this same service; none of them
mutate stock directly.

## Local development setup

Requires PHP 8.4+, Composer, Node 20+, PostgreSQL 16 (moving to MySQL 8), and Redis.
From the repo root:

```bash
cd backend
composer install
npm install
cp .env.example .env
php artisan key:generate

# create the database (adjust to your local Postgres superuser; MySQL steps land
# alongside the docs/ADR 0001 database-engine migration)
createuser -s biasharamax
psql -d postgres -c "ALTER USER biasharamax WITH PASSWORD 'biasharaos_dev_pw';"
createdb -O biasharamax biasharamax

php artisan migrate --seed
npm run build   # or `npm run dev` for hot reload
php artisan serve
```

Or use `../scripts/install.sh` (`.bat` on Windows), which wraps the same steps.

### Running tests

```bash
createdb -O biasharamax biasharaos_testing
php artisan test
```

## Multi-tenancy

Every business-scoped table carries a `business_id`. Tenant isolation is enforced
at the query level via the `BelongsToTenant` Eloquent trait (global scope), not by
convention in controllers. Platform Super Admins (`platform_users` table, `platform`
guard) bypass tenant scoping; everyone else is automatically restricted to their own
business's data.

## Documentation

See `../CHANGELOG.md` for sprint-by-sprint history of what shipped and why, and
`../docs/ADR/` for architecture decisions.
