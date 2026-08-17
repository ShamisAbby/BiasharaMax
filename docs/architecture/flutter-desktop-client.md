# BiasharaMax — Flutter Desktop Client Architecture

## Goal

Three client populations against one Laravel backend:

| Client | Who | Volume | Connectivity | Auth |
|---|---|---|---|---|
| **Flutter Desktop** | Business staff (cashiers, managers) — installed on a shop's PC/POS terminal | Large (majority of end users) | Offline-first, syncs when online | Sanctum token, gated by a per-business device license |
| **Web app** (existing React/Inertia) | Business staff who prefer/need a browser | Small minority | Always online | Session (`web` guard) — unchanged |
| **Admin Dashboard** (existing React/Inertia, Platform module) | BiasharaMax Super Admins | All of them | Always online | Session (`platform` guard) — unchanged |

The web app and Admin Dashboard need no architectural change — they already work. Everything below is new surface for the Flutter Desktop client, plus backend fixes required to make that surface safe.

## A bug this plan depended on fixing first — fixed

Three cross-cutting traits — `BelongsToTenant`, `HasUserstamps`, `Auditable` (all in `app/Modules/Shared/Concerns/`) — used to resolve the current actor by checking `Auth::guard('platform')` and `Auth::guard('web')` only, with no `Auth::guard('sanctum')` check, despite `Auditable`'s docblock already claiming otherwise. That's fixed: all three now check `sanctum` alongside `platform`/`web`, matching their docblocks. `tests/Feature/Api/SyncTenantScopingTest.php` proves it end-to-end over the real `/api/v1/*` routes: a Sanctum-authenticated request only ever sees/creates its own business's data, and `created_by`/audit `actor_id` are the real user, never null.

Historically, this mattered because any request authenticated via a Sanctum bearer token (i.e. every Flutter Desktop request) would otherwise either
- silently skip the tenant scope on `BelongsToTenant` models — a cross-tenant data leak, or
- create records with `business_id` / `created_by` / `updated_by` left null, and audit log entries with no actor.

## What already exists and is being reused, not rebuilt

The `Licensing` module (`app/Modules/Licensing/`) was already built for exactly this scenario — its own code comments say so explicitly ("Called by the BiasharaMax Desktop Edition client", "what's pending is the desktop client"). It is fully server-complete:

- `License` — one per business, `license_key`, `max_devices`, `status`, `expires_at`, `offline_activation_allowed`.
- `LicenseDevice` — a hardware-fingerprinted device activation record, capped at `max_devices` per license.
- `LicenseService::activate()` / `validate()` — online activation and periodic re-validation.
- `OfflineCertificateService` — RSA-2048/SHA-256 signed certificate a client can verify **without a network round-trip**, for genuinely offline license enforcement (e.g. first launch with no internet, or long stretches offline).
- `POST /api/v1/licenses/activate` and `POST /api/v1/licenses/validate` already routed and unauthenticated-by-necessity (rate-limited instead), since no session exists yet at install time.

Device licensing and user authentication are deliberately separate concerns: a license activates *the installation* (one per business, capped device count); Sanctum tokens authenticate *individual employees* on that installation, the same way the web app's session guard does today. This plan keeps that separation.

## New backend surface

All new endpoints live under `routes/api.php`, versioned `v1`, guarded by `auth:sanctum` unless noted.

### 1. Auth
- `POST /api/v1/auth/login` — email + password (same rules as the existing `LoginRequest`), returns a Sanctum token. Token abilities scoped to `['desktop']` so a stolen desktop token can't be reused to mint further tokens or hit platform-only routes.
- `POST /api/v1/auth/logout` — revokes the calling token only (`$request->user()->currentAccessToken()->delete()`), not all of the user's tokens (a user may be logged into more than one till).

### 2. Sync
A pull/push pattern, proven out for two resources in this pass (Products+Inventory, Sales) — the same shape extends to Purchasing, CRM, etc. later without a new mechanism, just new resource handlers.

**Pull** — `GET /api/v1/sync/products?since=2026-07-01T00:00:00Z`
Returns every product (and its per-warehouse inventory row) updated after `since`, scoped automatically to the caller's business via the now-fixed `BelongsToTenant` scope. The client stores `since` as a per-resource watermark and asks again on every reconnect; first sync just omits `since` and gets everything.

**Push** — `POST /api/v1/sync/sales`
Body is a batch of queued sales created while offline, each carrying a client-generated `idempotency_key` (UUID, generated at the moment the cashier hits "complete sale" on the desktop app — not when it eventually syncs). The endpoint loops the batch through the existing `SaleService::create()` — the same code path the web app's `SaleController` already uses, so there is no parallel "offline sale" business logic to maintain and no drift risk. Idempotency key is stored on `sales.idempotency_key` (new nullable unique column) so a retried push after a dropped connection can't double-create the sale.

Deliberately *not* rebuilt: `StockMovementService` and `SaleService`'s transactional guarantees. The offline queue's job is just to hold mutations until there's a network path to the same server-side code the web app already trusts — the ledger's append-only nature means a queued sale replays cleanly with no merge/conflict logic needed. This is why sales (and other ledger-shaped writes: stock adjustments, transfers) are the easy first sync target, and why something like "edit a product's price" is a harder future case — that one's a mutable-state conflict (two branches editing the same product offline) and needs a real conflict policy (last-write-wins by `updated_at`, to start) rather than pure append.

## Flutter Desktop app

`flutter_desktop_client/` at the repo root (sibling to `app/`, `resources/`, etc.) — a separate Flutter project, not part of the Laravel dependency tree.

- **Local storage:** SQLite via `drift`, mirroring the shape of the two synced resources (`products`, `inventory`, `sales`, `sale_items`) plus a `pending_mutations` outbox table (resource, payload JSON, idempotency key, created_at, synced_at nullable).
- **API client:** `dio`, base URL configurable (a shop's server might be `cloud.biasharamax.com` or a self-hosted box on the LAN), bearer token attached from secure local storage (`flutter_secure_storage`).
- **Startup flow:** first launch asks for a license key → `POST /v1/licenses/activate` with a computed hardware fingerprint → on success, stores the device confirmation and offline certificate locally → shows the employee login screen → `POST /v1/auth/login` → stores the Sanctum token → app is usable.
- **Sync manager:** runs on app start and on a timer/connectivity-restored event; pulls each resource's `since` watermark, then flushes the outbox in FIFO order, then updates watermarks. Failures leave the outbox item in place for the next attempt (at-least-once delivery, made safe by the idempotency key).
- **State management / scope for this pass:** Riverpod for state, `go_router` for navigation. This pass scaffolds the project structure, the local schema, the API client, license activation, login, and the sync manager wired to the two proven resources — not full POS screen parity with the web app. That's the natural next phase once this skeleton is validated end-to-end against the real backend.

## Rollout order

1. Fix the three shared traits (tenant scope / userstamps / audit) to recognize the `sanctum` guard. *(This pass.)*
2. Add `auth/login`, `auth/logout`, and the two sync endpoints (products pull, sales push) plus the `idempotency_key` migration. *(This pass.)*
3. Scaffold the Flutter project against that surface: activation → login → local cache → sync manager. *(This pass.)*
4. Validate end-to-end against a real running instance (this environment has no PHP runtime, so this pass is statically verified, not executed — running `php artisan test` and a real Flutter build locally is the next concrete step).
5. Extend the sync pattern resource-by-resource (Purchasing, CRM, stock adjustments/transfers) and build out full POS screens in Flutter.
6. Decide and implement the conflict policy for mutable-state resources (product edits, price changes) before syncing those — sales-style append-only sync does not need one, but product/customer edits do.
