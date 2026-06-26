# BiasharaOS

BiasharaOS is a modular Business Operating System for retail, hospitality, pharmacy,
wholesale and service businesses, supporting Cloud SaaS, Desktop and Hybrid
deployments from a single codebase.

This repository is the application codebase. Product/architecture documentation
lives in the separate [`BiasharaOS-Docs`](https://github.com/ShamisAbby/BiasharaOS-Docs)
repository, which is the source of truth for requirements and standards.

## Stack

- **Backend:** Laravel 12, PHP 8.4+
- **Frontend:** React, TypeScript, Inertia.js, TailwindCSS
- **Database:** PostgreSQL
- **Cache / Queue:** Redis, Laravel Horizon
- **Auth:** Laravel Sanctum (session-based for the web app, tokens for future mobile/API clients)

## Architecture

The application is organized by business domain rather than technical layer:

```
app/Modules/
  Authentication/   Login, registration support, profile self-service
  Business/          Business registration, settings, tenant model
  RBAC/               Roles & permissions
  Subscription/      Plans, trials, subscription status
  Shared/             Cross-cutting concerns: audit logging, tenant scoping, userstamps
```

Each module follows the same internal shape: `Models/`, `Services/`, `Http/Controllers/`,
`Http/Requests/`, `Http/Resources/`, `Policies/`. Controllers stay thin; business logic
lives in Services; authorization lives in Policies.

## Local development setup

Requires PHP 8.4+, Composer, Node 20+, PostgreSQL 16, and Redis.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# create the database (adjust to your local Postgres superuser)
createuser -s biasharaos
psql -d postgres -c "ALTER USER biasharaos WITH PASSWORD 'biasharaos_dev_pw';"
createdb -O biasharaos biasharaos

php artisan migrate --seed
npm run build   # or `npm run dev` for hot reload
php artisan serve
```

### Running tests

Tests run against a dedicated PostgreSQL database (`biasharaos_testing`) to match
the production database engine exactly — see `phpunit.xml`.

```bash
createdb -O biasharaos biasharaos_testing
php artisan test
```

## Multi-tenancy

Every business-scoped table carries a `business_id`. Tenant isolation is enforced
at the query level via the `BelongsToTenant` Eloquent trait (global scope), not by
convention in controllers. Platform Super Admins (`platform_users` table, `platform`
guard) bypass tenant scoping; everyone else is automatically restricted to their own
business's data.

## Documentation

See `CHANGELOG.md` for sprint-by-sprint history of what shipped and why.
