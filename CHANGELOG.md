# Changelog

All notable changes to BiasharaOS are documented here, sprint by sprint.

## Sprint 1 — Authentication, Business Registration, Trial, Subscriptions, Roles & Permissions, Dashboard, Settings

### Added

**Platform foundation**
- Scaffolded Laravel 12 + React/TypeScript/Inertia.js (via Laravel Breeze) on a single codebase.
- Configured PostgreSQL as the primary database and Redis (via `predis`) for cache, session and queue drivers.
- Installed and configured Laravel Sanctum (session auth for the web app today; token auth ready for the future mobile/API clients) and Laravel Horizon (queue dashboard, gated to the `platform` guard).
- Adopted a domain-modular folder structure (`app/Modules/{Authentication,Business,RBAC,Subscription,Shared}`) instead of Laravel's flat `app/Models` / `app/Http/Controllers` layout, per the architecture standard of organizing by business domain.

**Database**
- All primary keys are UUIDs (`HasUuids`). Pivot tables use composite primary keys.
- Added `platform_users` (Super Admin, fully separate from tenant users — no shared table, no shared scope), `businesses`, `subscription_plans`, `subscriptions`, `permissions`, `roles`, `permission_role`, `audit_logs`; extended `users` with `business_id`, `role_id`, `invited_by`, and userstamp columns.
- Soft deletes on `users`, `platform_users`, `businesses`. Userstamp columns (`created_by`/`updated_by`/`deleted_by`) on business-owned records.
- `Auditable` trait writes an immutable `audit_logs` row on create/update/delete for any model that opts in, capturing the acting guard (business user vs platform user), old/new values, IP and user agent.
- `BelongsToTenant` trait provides automatic tenant-scoping (global query scope) plus auto-fill of `business_id` on create. Deliberately **not** applied to the `User` model itself, since the auth guard's own user-resolution query would otherwise recurse into the scope it's trying to evaluate.

**Authentication**
- Standard Breeze login/logout/password-reset/email-verification flows, moved into `App\Modules\Authentication`.
- Login now stamps `last_login_at` on successful authentication.

**Business Registration & 30-Day Trial**
- Single combined registration flow (`BusinessRegistrationService`): owner account, business record, the five default roles, and a 30-day trial subscription are created atomically in one DB transaction. Any failure rolls back the whole thing — there is no code path that can leave a business without an owner, without roles, or without a subscription record.
- Business slugs are auto-generated and de-duplicated (`store`, `store-1`, `store-2`, ...).

**Subscriptions**
- Three seeded plans (Starter, Growth, Enterprise) with monthly/quarterly/yearly pricing and a 30-day trial.
- `SubscriptionService::startTrial()` sets both the `Business.status` and the `Subscription` row so there's a single source of truth for trial state.
- Read-only Subscription page showing current plan, trial countdown and available plans. Upgrades/billing-cycle changes are deferred until payment processing is integrated in a later sprint.

**Roles & Permissions**
- Every business is provisioned with five default roles on registration: Business Owner (all permissions), Manager, Cashier, Inventory Officer, Accountant — matching the platform's permission matrix.
- Owners can create custom roles and assign permissions from the current permission set (`dashboard`, `business`, `employees`, `roles`, `subscription` modules — more modules will register their own permissions as they ship).
- System roles (the five defaults) cannot be deleted, so a business can never end up without an administrative role.
- All role mutations are tenant-scoped at the query level (`Role::class` uses `BelongsToTenant`), so a cross-tenant role ID resolves to 404, not 403 — the response never confirms the role exists.

**Business Dashboard & Settings**
- Dashboard shows business profile, subscription/trial status, employee count, and quick links. Sales/inventory KPIs will be added once those modules exist — Sprint 1 intentionally does not fabricate metrics for features that don't exist yet.
- Business Settings page for updating profile, contact details, locale (country/currency/timezone).

### Architecture decisions worth knowing about

- **Separate `platform_users` table, not a shared `users` table with a flag.** Matches the documentation repository's table list and gives Super Admin a guard (`platform`) that can never be confused with tenant-scoped queries.
- **Role-per-business, not global roles.** The five default roles are *cloned per business* (each business gets its own `Role` rows), so an owner customizing the Cashier role's permissions never affects any other business.
- **`Auditable` and `HasUserstamps` are opt-in traits**, not base-model behavior, so models that don't need an audit trail (e.g. `Permission`, lookup tables) don't pay for it.
- **No payment processing yet.** Subscriptions track plan/status/trial dates only; billing integration is a separate, explicitly out-of-scope sprint.
- **No Employee invitation UI yet.** Roles & Permissions ships as a standalone capability; assigning roles to invited employees depends on the Employees module (Core Module, scheduled for a later sprint per the documentation repository's roadmap).

### Testing

37 automated tests (PHPUnit), run against a dedicated PostgreSQL database to match
production exactly: registration (including the atomic-transaction guarantee and
duplicate-email validation), business settings (ownership + cross-tenant denial),
role management (creation, system-role deletion protection, cross-tenant 404),
subscription trial lifecycle, plus the full set of Breeze auth/profile tests
adapted to the new UUID/modular `User` model.

### Known follow-ups for later sprints

- Employees module (invite/manage staff, assign roles) — Sprint 2 per the roadmap.
- Subscription upgrade/downgrade and payment processing.
- Super Admin (platform) UI — the `platform` guard and `PlatformUser` model exist; no screens yet.
- Permission set will grow as Inventory, POS, Purchasing, CRM, Accounting, etc. ship — each module seeds its own permissions rather than Sprint 1 pre-guessing them.
