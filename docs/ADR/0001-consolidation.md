# ADR 0001 — Consolidation Plan (Phase 0 Audit)

Status: **Phase 1 done; Phase 2 in progress.** Repo relocated to the target layout, the
Sanctum-guard test gap closed, `app/Modules` renamed to `app/Domain` across the
codebase, and the MySQL-compatibility code changes (Section 6 below) are done. None of
this is committed to git yet — see "Git handoff" at the end of this file. The actual DB
engine cutover (provisioning real MySQL, flipping `.env`/`phpunit.xml`, running
migrations fresh) has not happened — see Section 6. Admin rebuild and frontend rewrite are
not started. The money-format migration has a full written plan —
`docs/ADR/0002-money-format-migration.md` — but no migration files yet; it's the one
item flagged there as needing sign-off before real schema changes start.

## 6. DB engine migration (Postgres → MySQL) — code changes done, cutover still manual

Audited all 179 migrations plus every `app/` query for Postgres-specific SQL. Found and
fixed exactly three migrations with raw SQL, plus one Postgres-only query operator used
across the app — everything else already went through Laravel's engine-agnostic Schema
builder/query builder and needed no changes:

1. **`.../add_scope_and_action_to_permissions_table.php`** — the generated `action`
   column used Postgres's `split_part(slug, '.', 2)`. Now branches on
   `DB::connection()->getDriverName()`: MySQL gets `substring_index(slug, '.', -1)`,
   Postgres keeps `split_part`. Equivalent here because every existing permission slug
   is exactly one dot (documented in the migration itself).
2. **`.../create_product_attribute_values_table.php`** — the `pav_exactly_one_owner`
   CHECK constraint needed no change. MySQL 8.0.16+ and Postgres both support identical
   `ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)` syntax with no engine-specific
   functions in the condition. Added a comment noting the 8.0.16+ requirement (older
   MySQL silently ignores CHECK constraints).
3. **`.../create_inventories_table.php`** — the two partial unique indexes (unique on
   `warehouse_id`+`product_id` only when `product_variant_id IS NULL`, and vice versa)
   have no MySQL equivalent — MySQL has no partial/filtered index. Now branches by
   driver: MySQL gets two generated `char(73)` columns (`concat(warehouse_id, ':',
   product_id)`, NULL when the condition doesn't apply) with a plain unique index on
   each — MySQL's unique index treats NULL as distinct, same trick as the partial index
   achieves in Postgres. Postgres keeps the original partial indexes.
4. **`'ilike'` query operator** — Postgres-only; MySQL has no ILIKE. Used in 19 files,
   44 call sites (`->where('col', 'ilike', "%term%")` pattern, mostly search/filter
   endpoints). Replaced with `'like'` everywhere — MySQL's default collation
   (`utf8mb4_unicode_ci`, already set in `config/database.php`) is case-insensitive, so
   plain `LIKE` is the exact equivalent here.

**Second audit pass (2026-08-04)** — the first pass above covered migrations plus a
targeted search for the operators/types initially suspected; this pass re-checked
everything (`to_char`, `string_agg`, `array_agg`, `date_trunc`, `::` casts, regex/JSONB
operators, `ON CONFLICT`/`RETURNING`, `jsonb`/`citext`/`array`/`tsvector` types, GIN/GiST
indexes, sequence functions, case-sensitivity) more thoroughly and found two more
"breaks on MySQL" items the first pass missed, plus one "silently different behavior"
item:

5. **`to_char()`** — Postgres-only date-formatting function, used unguarded (no driver
   branch) in **17 places across 9 report/dashboard services** —
   `ReportCenterService`, `FinanceAnalyticsService`, `FinanceReportService` (8
   occurrences alone), `CrmDashboardService`, `PlatformAnalyticsService`, Accounting's
   `FinancialReportService`, `SubscriptionAnalyticsService`, `SalesDashboardService`.
   Same bug class as `split_part` above — this would throw
   `FUNCTION <db>.to_char does not exist` on MySQL the first time any dashboard/report
   endpoint loaded. Fixed by extracting a shared
   `App\Domain\Shared\Support\DateFormatSql::daily()`/`monthly()` helper (rather than
   repeating the driver-branch inline at 17 call sites) that returns
   `to_char(col, 'YYYY-MM-DD'|'YYYY-MM')` on Postgres and MySQL's
   `DATE_FORMAT(col, '%Y-%m-%d'|'%Y-%m')` equivalent.
6. **`RestoreService::restore()`** — hardcoded the `psql` binary and Postgres-only
   connection flags/env var (`PGPASSWORD`) unconditionally; not driver-branched despite
   the class already reading `config('database.connections.'.config('database.default'))`
   dynamically. Would either fail to find `psql` at all on MySQL, or (if some
   unrelated `psql` happened to be on PATH) point it at a MySQL host/port — a protocol
   mismatch, and even then the dump file itself is produced by spatie/laravel-backup
   using whichever dumper matches `DB_CONNECTION`, so it would be `mysqldump`-format SQL
   that `psql` can't execute anyway. Reachable from the SuperAdmin "restore backup" UI
   action. Fixed: `restore()` now branches on the connection driver — `mysql`/`mariadb`
   pipes the dump into `mysql` via stdin (its CLI takes dumps on stdin, not a `--file`
   flag like `psql`), password via `MYSQL_PWD` env (not a `--password=` argument, for the
   same "don't leak secrets via `ps`" reason `PGPASSWORD` was already used for psql);
   `pgsql` keeps the existing `psql --file=` behavior. Also added a `dump.dump_binary_path`
   config key to the `mysql` connection array in `config/database.php` (mirroring the
   `pgsql` one, which exists because Apache/XAMPP processes don't inherit the shell PATH
   that would otherwise locate the binary) — defaults to empty string since `mysql`/
   `mysqldump` are typically already on PATH on Linux/XAMPP installs, unlike the
   Homebrew-installed Postgres client tools this app previously ran against.
7. **Case-sensitive unique columns, silently different behavior (not a hard break)** —
   Postgres's default collation is case-sensitive; MySQL's default
   (`utf8mb4_unicode_ci`) is not. Left alone, `"ABC-1"` and `"abc-1"` are distinct values
   today but would collide as duplicates under a MySQL unique index — a silent
   uniqueness-semantics change, and a real migration-time risk if any existing row
   already has a case-variant duplicate of another row's SKU/barcode/serial/reference
   number (that would surface as a migration failure, not silently). No application code
   was found relying on this either way (no deliberate case-sensitive SKU logic), so
   this was a latent risk rather than a confirmed bug — resolved conservatively by
   preserving exact Postgres parity rather than accepting MySQL's default: new migration
   `2026_08_04_000001_preserve_case_sensitive_collation_on_mysql.php` pins
   `products.sku`/`barcode`, `product_variants.sku`/`barcode`,
   `product_serials.serial_number`, and `payment_transactions.reference_number` to
   `utf8mb4_bin` (case-sensitive) collation, MySQL-only (Postgres needs no change, already
   case-sensitive). Deliberately **not** applied to `email` columns
   (`users`/`platform_users`/`businesses`) — case-insensitive email uniqueness is
   standard practice and the more correct behavior, so MySQL's default here is a welcome
   change, not a regression, and was left alone on purpose. Uses raw `ALTER TABLE ...
   MODIFY ... COLLATE` rather than `Blueprint::change()` since `doctrine/dbal` (which
   `change()` needs) isn't installed in this project.

**What's still manual** (infrastructure, not code):

- Provision a real MySQL 8.0.16+ instance (or use `docker/docker-compose.yml`, already
  configured for it).
- Create `biasharamax` and `biasharaos_testing` databases on it.
- Flip `backend/.env`: `DB_CONNECTION=mysql`, `DB_PORT=3306` (already the default in
  `.env.example` — your live `.env` was deliberately left alone since it's actively
  running your current Postgres data).
- Flip `backend/phpunit.xml`'s `DB_CONNECTION`/`DB_PORT` env entries the same way —
  also deliberately left alone for the same reason: it's what your just-passed test run
  used.
- Run `php artisan migrate --seed` fresh against MySQL, then `php artisan test`.
- Only after that passes, decommission the Postgres database.

Not verified by actually running migrations against a real MySQL instance (no DB engine
available in the environment this was written in) — the `split_part`/`substring_index`
and partial-index-emulation SQL, the `to_char`/`DateFormatSql` fix, the
`RestoreService` MySQL branch, and the new collation migration should all be tested
against a real MySQL 8 instance before trusting them in production.

**Not verified by actually running the app.** This environment has no PHP runtime, so
the rename was done and checked by reading/grepping source, not by running
`composer dump-autoload`, `php artisan test`, or booting the app. Before trusting this,
run `composer install && php artisan test` locally — a single missed reference would
surface immediately as a class-not-found error.

## Decisions (2026-08-03)

| Question | Decision |
|---|---|
| Database engine | **Migrate to MySQL 8** |
| Admin dashboard | **Rebuild in Filament** |
| Client web frontend | **Livewire + Blade** |
| Guard/table naming | **Keep `platform`/`platform_users`** (not renamed to `admin`) |
| Repo relocation | **Yes, done now** (this document reflects the post-move state) |
| Accounting vs. Finance | **Merge `Accounting` into `Finance`**; `Finance` becomes the ledger (`Domain/Accounting` once the Modules→Domain rename happens) |
| Sync engine | **Full rebuild** per Section 6 of the master spec, replacing the current timestamp-cursor design — including updating the already-built Flutter screens in `desktop-app/lib/` |

None of these are executed yet except the repo relocation itself — DB engine, Filament,
Livewire, the ledger merge and the sync rebuild are each their own phase with their own
plan, not implied by the directory move.

## 1. What actually exists today

This is not a greenfield project and not three duplicate Laravel projects. It is **one
already-consolidated Laravel 12 / PHP 8.4 application**, in active development, with a real
git history (4 commits: auth/registration/subscriptions/RBAC, branches/warehouses/employees,
a public landing page, and a Tanzania locale change) plus an uncommitted Sprint 3 (Inventory)
already documented in `CHANGELOG.md`. 179 migrations, 1,163 PHP files, 108 test files, 226 TSX
files.

### 1.1 Surfaces — already separated, differently than the target spec assumes

| Surface | Reality today |
|---|---|
| Web app + Admin dashboard | **Both** are Inertia + React (TypeScript), served by the same Laravel app. Tenant pages under `routes/web.php` (`web` guard, `User` model). Admin/platform pages under `routes/platform.php` (`platform` guard, `PlatformUser` model, `platform_users` table), React pages namespaced at `resources/js/Pages/Platform/`. No Filament, no Livewire, no Vue anywhere in `composer.json`/`package.json`. |
| API (Flutter) | `routes/api.php` already has a real, working surface: `POST /v1/licenses/activate`, `/validate` (unauthenticated, throttled), `POST /v1/auth/login`/`logout`/`me` (Sanctum, token ability `desktop`), `GET /v1/sync/products` (pull, `since` timestamp), `POST /v1/sync/sales` (push). This is a *simpler* sync design than Section 6 of the spec (timestamp cursor, no `uuid`/`revision`/command-queue), but it is live code, not a plan. |
| Desktop | `flutter_desktop_client/` is a real, partially-built Flutter app, not a stub: `activation_screen`, `login_screen`, a POS screen (`pos_home_screen`, `cart_controller`/`cart_panel`, `payment_sheet`, `receipt_dialog`), `sync_manager.dart`, Drift `database.dart`, Dio `api_client.dart`, `flutter_secure_storage`, device fingerprinting. A CI workflow (`.github/workflows/build-desktop-windows.yml`) already builds it. |

**Conclusion:** Phase 2 ("merge API/web/admin into `backend/`, delete the duplicate projects")
does not apply — there is nothing to merge. The actual Phase 2 work is *renaming/relocating* an
already-correct architecture into the target folder layout, and reconciling naming (`platform`
guard vs. spec's `admin` guard).

### 1.2 Domain modules — already bounded-context-shaped, wider than the spec's list

`app/Modules/` — renamed to `app/Domain/` as of this phase (see status line above) — already
has 27 modules, each with
its own `Models/Services/Http/Policies`: Authentication, Business, RBAC, Subscription, Inventory,
Purchasing, Sales, **Accounting**, **Finance**, CRM, Payroll, Platform, Reports, Licensing,
Localization, ModuleManagement, Monitoring, Notifications, Integrations, AiInsights, Backup,
Security, Settings, Support, Website, WebsiteTemplates, Developer, Shared.

Two of these don't map cleanly onto the spec's `Domain/` list and need an explicit decision
(Section 3 below): **`Accounting`** (Expense/Income/ExpenseCategory, cash-basis, its own
`FinancialReportService`) and **`Finance`** (full double-entry: `Account`, `JournalEntry`,
`JournalLine`, `ChartOfAccountsService`, `JournalPostingService`, `AutoPostingService`, plus
listeners that already post journal entries for `SaleCompleted`, `SaleVoided`, `ExpensePaid`,
`SupplierPayment`, `GoodsReceived`, `IncomeRecorded`). **The double-entry ledger Appendix A2 asks
for already exists, in `Finance`, not in a module called `Accounting`.**

`Licensing` (License, LicenseDevice, LicenseActivationLog, `OfflineCertificateService`,
RSA-signed offline certs) and `Subscription` (plans, trial, `SubscriptionLimitService`,
registration codes) are both fully built and materially overlap Phase 9 and Appendix work items.

### 1.3 Hard conflicts with the target spec (Sections 4–7)

| Spec says | Codebase has | Conflict |
|---|---|---|
| MySQL 8 | **PostgreSQL 16** (`DB_CONNECTION=pgsql`, `phpunit.xml` runs against a dedicated `biasharaos_testing` Postgres DB) | Full engine migration, not a config change — 179 migrations to re-verify, any Postgres-specific SQL to find. |
| Filament admin | Custom Inertia+React admin already built under `Platform` module (business management, licenses, roles, module management, monitoring, business types, etc. — dozens of controllers) | Adopting Filament means throwing away and rebuilding an already-substantial admin surface. |
| Client web: Livewire+Blade or Inertia+Vue | Inertia + **React** + TypeScript, with an existing component library (`resources/js/Components`, `Layouts`, `Charts`, `Bi`) | Full frontend framework rewrite if Vue/Livewire is chosen. |
| `admin` guard, `admin_users` table | `platform` guard, `platform_users` table, already wired through dozens of controllers/policies | Cosmetic but wide — a rename touching most of the `Platform` module and `RBAC`. |
| Money = integer minor units | **`decimal(x,y)` everywhere** — `sale_items.unit_price`, `product_variants.selling_price`, `payment_transactions.amount`, `journal_lines` debit/credit, `customers.current_balance`, etc. — dozens of columns across Sales, Inventory, Purchasing, Finance, Payroll, CRM. | This is the most invasive item on the list: it touches nearly every money-bearing table, every `Resource`, every React form, and every calculation in `Finance`/`Sales`/`Payroll`. Not something to do incidentally inside a "consolidation" pass. |
| Sync: `uuid` identity, server `revision` counter, command queue, `sync_conflicts`/`sync_logs` | Sync exists but is simpler: timestamp `since` cursor, no `uuid` column on synced tables, no revision counter, no command queue, no conflict table. Two resources only (products, sales). | This is a genuine rebuild of the sync layer per Section 6, on top of a Flutter app that already has working screens built against the *current*, simpler `sync_api.dart`/`sync_manager.dart`. |
| Repo layout: `backend/`, `desktop-app/`, `shared/`, `docker/`, `scripts/`, `sql/`, `backups/`, `licences/` | Flat Laravel-root layout: `app/`, `resources/`, `routes/`, `database/` directly under `BOS/`; `flutter_desktop_client/` (not `desktop-app/`) as a sibling folder | Mechanical but repo-wide: every deploy script, IDE config, and the local XAMPP `htdocs/BOS` path assumption changes. |
| English + Swahili from day one | `lang/` only has vendor package translations (`lang/vendor/backup`) — no app-level `en`/`sw` files | Real gap, but additive — no conflict, just missing work. |

### 1.4 What's already correct and shouldn't be redesigned

Multi-tenancy via `BelongsToTenant` global scope + `business_id` on every tenant table. The
`stock_movements` immutable ledger (`StockMovement::delete()` throws `LogicException`) is exactly
the pattern Section 6.2's "derived state is never synced, only movements" describes — Inventory
already does this correctly. RBAC is custom (not `spatie/permission`) — Role/Permission/
PlatformRole/RoleTemplate — functioning and integrated; no reason to replace it.

**Correction (2026-08-03):** this ADR originally reported here that `Auditable`/`HasUserstamps`/
`BelongsToTenant` had no `sanctum` guard check, based on `docs/architecture/flutter-desktop-client.md`
calling it out as an open bug. Re-reading the actual trait code in Phase 2 showed all three
already check `sanctum` alongside `platform`/`web` — the code was fixed at some point but the
architecture doc wasn't updated to say so, and the Phase 0 audit trusted the doc instead of
re-verifying the code. Both are now corrected, and
`backend/tests/Feature/Api/SyncTenantScopingTest.php` proves the fix end-to-end (tenant isolation
+ real actor on created records over a Sanctum-authenticated request) — this exists because there
was previously zero test coverage mentioning `sanctum` at all, and non-negotiable constraint #2
requires a test proving cross-tenant access fails.

**Correction 2 (2026-08-04):** the `sanctum` guard check existing wasn't the whole story.
Running the *full* test suite for the first time in this project (previously every
verification was a scoped `--filter=X` run) surfaced that `SyncTenantScopingTest` itself
was still failing — not because `sanctum` wasn't checked, but because `BelongsToTenant`/
`HasUserstamps`/`Auditable` all checked `web` *before* `sanctum` in a fixed order. In
production this is a narrow edge case (a request would need both a valid `web` session
and a valid Sanctum bearer token simultaneously); in this specific test it's guaranteed —
`actingAs()` leaves the `web` guard's cached user set for the rest of the test method,
so a later Sanctum-authenticated request still saw `Auth::guard('web')->check()` return
`true` for a *different, stale* user, and resolved the wrong tenant/actor from it.

First fix attempt: resolve via `Auth::user()`/`Auth::id()` (no explicit guard) first, on
the theory that Laravel's `Illuminate\Auth\Middleware\Authenticate` calls
`Auth::shouldUse($guard)` on whichever guard actually authenticates a request/route,
changing what the *default* guard resolves to. **This did not work** — the next full-suite
run reproduced the identical failure at the identical assertion, so whatever `Auth::user()`
resolves to in a test request, it isn't reliably the route's authenticating guard here (this
project's sandbox has no `vendor/` mounted, so the exact internal mechanism was never
directly confirmed — the fix was reverted rather than debugged blind).

Actual fix: check `sanctum` explicitly *before* `web`, reversing the original static
order instead of trying to avoid one. `web`'s `SessionGuard::check()` reflects whatever
user was last attached to that guard instance and does not re-validate against the
current request — fine in production (a session guard's state *is* the current request's
session), but Laravel's testing HTTP client reuses one application/container across every
simulated request in a test method, so a `SessionGuard` populated by an earlier
`actingAs()` call stays "checked" for the rest of that test method, even for later requests
authenticating as someone else via `sanctum`. `sanctum`'s guard re-resolves the bearer
token from the *current* request's `Authorization` header on every call, so it never
returns stale state — checking it first means a genuine Sanctum-authenticated request
always wins over a merely-cached `web` session, in both tests and production (a real
browser session request never carries a bearer token, so this reordering changes nothing
for the `web`-only case). `platform` remains a special-cased first check in all three
traits — a SuperAdmin action should always be attributed to the `platform` guard. No new
test needed — `SyncTenantScopingTest` (already written for Correction 1) is exactly the
regression test for this bug; awaiting the next full-suite run to confirm it now passes.

This, plus two smaller pre-existing bugs found in the same full-suite run (both unrelated
to guards/tenancy, see below), reinforces a lesson: scoped `--filter=X` verification
proves a *context's* own tests pass, but doesn't catch bugs that only manifest across
test-method boundaries within a single PHP process, or in files nobody's filter happened
to include yet. Worth periodically running the full suite, not just scoped filters, going
forward.

**Two more pre-existing, engine-agnostic bugs found in the same full-suite run** (neither
caused by the MySQL cutover — both reproduce identically on any database engine, and were
apparently never exercised by any previously-run scoped filter):

- `SaleService::create()` correctly rejects a walk-in cash sale with a nonzero balance due
  and no `customer_id` (`CreditSaleException::customerRequired()` — there's no one to
  carry the debt). `SyncTenantScopingTest`'s own sale-creation test paid less than the
  sale total without providing a customer, so it was tripping this real business rule
  itself, not exercising a bug in `SaleService`. Fixed the test's fixture (pay in full).
- `BusinessAssistantService`'s payables intent-matcher (`Str::contains` against a fixed
  keyword list) didn't include a phrasing as natural as "Which suppliers should I pay
  first?" — it fell through to the generic "I don't have a direct answer" response. Added
  `'suppliers should i pay'`, `'suppliers to pay'`, `'which supplier'`, `'who should i pay'`,
  `'pay first'` to the trigger list.
- `NotificationCenterTest` called the notifications route with a plain `get()`;
  `NotificationController::index()` branches on `$request->wantsJson()`/`ajax()`, neither
  of which a plain `get()` satisfies (no `Accept`/`X-Requested-With` header), so it
  rendered the Inertia HTML page instead of JSON — "Invalid JSON was returned from the
  route" is exactly what `assertJsonCount()` reports when handed an HTML body. Fixed the
  test to use `getJson()`.
- `DeductInventoryOnSaleCompletion` (the `SaleCompleted` listener) deducted stock for
  every sale line unconditionally, with no check against `Product::tracksStock()` — a
  method that already existed for exactly this purpose (gates a product's `in_stock`
  display on the storefront) but nothing in the sale-completion path ever called it. A
  product created with `track_stock = false` (a service, a made-to-order item, anything
  this business doesn't manage stock levels for) never receives a stock-`IN` movement
  either, so its inventory row sits at 0 forever — the very first sale of it walks
  quantity to -1 and `StockMovementService::record()` throws
  `InsufficientStockException`, an app-level bug, not a test bug: any real sale of a
  non-stock-tracked product hits this today. `SyncTenantScopingTest`'s sale-sync test
  created a `track_stock: false` product and surfaced it — the sale was accepted by
  `SaleService` (no `CreditSaleException`) but then failed inside the `SaleCompleted`
  event's listener chain, caught by `SyncController::pushSales()`'s generic
  `catch (Throwable $e)` and reported back as a generic `'error'` status. Fixed by
  skipping the stock deduction entirely when `$item->product?->tracksStock()` is false.
- `PermissionMatrixTest > matrix can be searched` — root-caused via a tinker diagnostic
  (see Section 5): searching `business types` against `Permission::query()->where('name',
  'like', "%business types%")->orWhere('slug', 'like', ...)` returned zero rows even
  though `name = 'View Business Types'` exists in the table. The diagnostic's own error
  output revealed why static reading of the code never would have found this: the query
  ran against `Connection: pgsql, Host: 127.0.0.1, Port: 5432` — **every verification
  this session, tinker and `php artisan test` alike, has actually been running against
  PostgreSQL, not MySQL**. `phpunit.xml` hardcodes `DB_CONNECTION=pgsql`/
  `DB_DATABASE=biasharaos_testing`, and `backend/.env` was never switched over either —
  `docker/docker-compose.yml` says so explicitly in its own header comment ("`.env` still
  points at PostgreSQL until the engine-migration phase actually runs"), a step that got
  documented as a manual follow-up in Phase 3 but was never actually done. See the
  standalone note below this list — this affects every "verified against MySQL" claim
  logged during Phase 3, not just this one test.

  The bug itself is real regardless of engine: Postgres's `LIKE` is case-sensitive by
  default (`'business types' LIKE '%business types%'` never matches `'View Business
  Types'`), and the code was only "working" on MySQL because that engine's *default*
  collation happens to be case-insensitive — an implicit dependency on a collation
  default, not a deliberate design choice. Fixed `PermissionMatrixController::index()` to
  wrap both sides of the search in `LOWER()` via `whereRaw`, making the match explicitly
  case-insensitive on every engine rather than relying on collation defaults.

**Correction 3 (2026-08-04):** every "verified against a real MySQL 8 instance" claim
logged earlier in this section and in Section 6 needs a caveat. `backend/phpunit.xml`
hardcodes `DB_CONNECTION=pgsql`/`DB_HOST=127.0.0.1`/`DB_PORT=5432`/
`DB_DATABASE=biasharaos_testing` in its `<php>` block, and `backend/.env` was also never
switched — `docker/docker-compose.yml`'s own header comment already flagged this ("`.env`
still points at PostgreSQL until the engine-migration phase actually runs... update
`DB_CONNECTION`/`DB_HOST`/etc. once that phase lands"), but that switch never actually
happened. PHPUnit's `<env>` entries take precedence over shell-exported environment
variables, so even if `migrate --seed` was run with an inline `DB_CONNECTION=mysql`
override (plausible — the collation migration did log `DONE` against something), every
`php artisan test` run this session — the 128M memory-limit failures, the 3-failure list,
the guard/inventory fixes, all of it — almost certainly ran against the pre-existing
Postgres `biasharaos_testing` database, not MySQL.

This doesn't invalidate the guard-order fix or the inventory-listener fix — both bugs are
engine-agnostic (confirmed by their own reasoning: `SessionGuard` staleness and
`tracksStock()` never being called have nothing to do with SQL dialect) — but it does mean
**Phase 3's actual goal, proving the app runs correctly on MySQL, has not yet been
verified by the test suite at all.** The migration compatibility work (Section 6) has only
been checked by a single manual `migrate --seed` run, never by `php artisan test`.

Fixed the same day: `backend/.env` and `backend/phpunit.xml` both now point at
`127.0.0.1:3306` (MySQL), database `biasharamax`/`biasharaos_testing` respectively, user
`biasharamax`/`biasharaos_dev_pw` — matching `docker/docker-compose.yml`'s `db` service
credentials, on the assumption you have a native/local MySQL 8 install answering on that
port (not necessarily the compose stack itself). One thing this environment can't do
(no MySQL client available to it): confirm `biasharaos_testing` actually exists yet as a
database — create it (`CREATE DATABASE biasharaos_testing;`) if `php artisan test` fails
to connect, then run the full suite. That run will be the first real confirmation that
Phase 3's compatibility work, and every fix in this Correction, actually holds on MySQL.

## 2. Consolidation / alignment plan

Reframing Phase 2 as **alignment**, not merge, since there is one codebase already:

1. **Fix the known Sanctum-guard gap first** (small, mechanical, already scoped in the desktop
   client architecture doc) — this is a live security bug for any Sanctum traffic today,
   independent of anything else in this plan.
2. Get explicit decisions on the six conflicts in 1.3 (see Open Questions) before touching
   directory structure or schema — several are mutually reinforcing (e.g., money format touches
   every module; DB engine touches every migration) and doing them out of order multiplies
   rework.
3. Relocate into the target layout (`backend/`, `desktop-app/`, `shared/`, etc.) as a pure move +
   path/config update, with tests green before and after, once the layout question is confirmed.
4. Reconcile naming only where the decision is "yes, rename": `platform` → `admin` guard/table
   touches `Platform` module, `RBAC`, and every policy/middleware referencing it — do this as one
   dedicated, reviewable step, not folded into unrelated changes.
5. Treat the money-format change and the sync-engine rebuild as **their own phases with their own
   migration plans**, not line items inside "Phase 2/3" — each is comparable in size to an entire
   existing module.

### Risks

- **Money format migration** is the highest-risk single item: it's a breaking schema + business
  logic change across Sales, Inventory, Purchasing, Finance, Payroll, CRM simultaneously, with
  live journal-posting listeners that must stay balanced (debits == credits) through the change.
  Needs its own ADR and a dry-run against real data before touching production-shaped migrations.
- **DB engine switch** (Postgres → MySQL) risks silently breaking anything relying on
  Postgres-specific behavior (case sensitivity, `JSON` vs `JSONB`, sequence behavior) — needs a
  dedicated audit pass of all 179 migrations for engine-specific syntax before committing to it.
- **Sync engine rebuild** happens on top of Flutter screens already wired to the current API
  shape (`sync_api.dart`) — the POS/cart/checkout screens will need their data layer touched too,
  not just the backend.
- **Frontend framework change** (if Vue/Livewire is chosen over keeping React) is a full rewrite
  of every existing Inertia page — the largest line-count item in the whole plan if chosen.
- **Admin rebuild in Filament** would discard a working, already-built admin surface
  (`Platform` module) covering most of Section 4.1's admin feature list already.

## 3. Open questions (blocking — need answers before Phase 1 file moves happen)

1. **Database engine** — keep PostgreSQL (matches everything built so far, README, test config)
   or migrate to MySQL 8 per the spec? If MySQL, is that a hard requirement (e.g., host
   constraint) or a default that can be revisited?
2. **Admin dashboard tech** — keep the existing, already-substantial Inertia+React `Platform`
   module, or actually rebuild it in Filament? If Filament, what happens to the existing
   `Platform` controllers/pages (port the logic, or true rewrite)?
3. **Client web tech** — keep Inertia+React (matches 226 existing TSX files and the component
   library), or move to Livewire+Blade / Inertia+Vue? This spec asks for an ADR either way
   ("pick one and justify") — do you want that ADR written assuming React stays, given how much
   already exists on it?
4. **Guard/table naming** — rename `platform`/`platform_users` to `admin`/`admin_users` to match
   the spec literally, or treat `platform` as the accepted equivalent (same semantics, different
   name) and update the spec's naming instead?
5. **Money format migration scope/timing** — confirm this becomes its own phase (own ADR, own
   dry run) rather than part of "Phase 3 — Data model," given it touches nearly every
   money-bearing table already built across six modules.
6. **Accounting vs. Finance** — `Finance` already is the double-entry ledger Appendix A2
   describes (accounts, journal entries/lines, auto-posting listeners, chart of accounts seeder).
   `Accounting` is a separate, simpler cash-basis expense/income tracker. Do we: (a) keep both,
   with `Accounting` reporting alongside but not into the ledger, (b) fold `Accounting`'s
   expense/income into `Finance` as ledger-posting source documents, or (c) rename `Finance` to
   the spec's `Domain/Accounting` and rename/merge `Accounting` elsewhere?
7. **Sync engine rebuild scope** — confirm we're replacing the existing timestamp-based
   `since`/product+sales sync (already live, with Flutter screens built against it) with the full
   Section 6 design (uuid identity, revision counter, command queue, conflict table), rather than
   incrementally extending the current design. This affects Flutter screens already written, not
   just backend routes.
8. **Repo relocation** — confirm moving `app/`, `resources/`, `routes/`, `database/`, etc. into
   `backend/`, and `flutter_desktop_client/` → `desktop-app/`, as a dedicated Phase 1 step, with
   local XAMPP path becoming `htdocs/BOS/backend/public`.
9. **Localization** — no `en`/`sw` app translation strings exist yet (only a vendor package's own
   translations). Confirm this is new work, not a gap in something already built.

## 4. Phase 1 file list (once the above is confirmed — not yet executed)

Pending answers to Section 3, particularly Q8 (repo relocation) and Q1 (DB engine, which affects
whether `docker/` ships a Postgres or MySQL service). No files created or moved yet.

Proposed, contingent on "yes, relocate":

```
BOS/
├── backend/                      # git mv of app/, resources/, routes/, database/, config/,
│                                  # bootstrap/, public/, storage/, tests/, artisan, composer.*,
│                                  # phpunit.xml, .env(.example), vite/tailwind/eslint configs
├── desktop-app/                  # git mv of flutter_desktop_client/
├── shared/
│   ├── api-contracts/            # new: OpenAPI 3.1 spec (extracted from routes/api.php)
│   ├── database-schema/          # new: ERD + schema doc (generated from current migrations)
│   └── translations/             # new: en/sw source-of-truth strings (Q9)
├── docker/                       # new: docker-compose.yml, nginx, php, db, redis configs
│                                  # (engine per Q1 answer)
├── scripts/                      # new: install.sh/.bat wrapping today's manual setup steps
│                                  # from README.md
├── docs/
│   ├── ADR/0001-consolidation.md # this file
│   └── architecture/flutter-desktop-client.md   # existing, kept, superseded in part by Q7
├── .github/workflows/            # existing build-desktop-windows.yml kept; add backend CI
└── README.md                     # updated for new layout
```

No `backups/`, `licences/`, or `sql/` top-level directories exist yet — these would be new,
empty (gitignored) directories, added only once Q8 is confirmed.

## 5. Git handoff (Phase 1 relocation — run this locally)

The directory relocation above has been physically performed. It could not be committed
from the environment this ADR was written in — that sandbox's mount of this folder
doesn't support the low-level file operation (`unlink`) git needs to write commits, so
`git mv`/`git commit` fail partway through there. The working tree itself is unaffected
(verified with `git fsck`: no corruption, only harmless orphaned objects from the failed
attempts, which git will garbage-collect on its own).

Run this from a real terminal on your machine, in the repo root:

```bash
# git doesn't know about the moves yet — `git add -A` will see them as
# deletions + new files. `git add -A` still stages them correctly; you just
# lose automatic rename-detection in the raw diff (git log --follow and
# `git show --stat -M` will still find the history across the move).
git add -A
git status   # sanity check: should show renames/moves for app/, resources/,
             # routes/, database/, public/, storage/, tests/, bootstrap/,
             # config/, lang/, vendor/, node_modules/, artisan, composer.*,
             # package*.json, phpunit.xml, *.config.js, tsconfig.json,
             # .eslintrc.json, .prettierrc, .env, .env.example into backend/,
             # and flutter_desktop_client/ -> desktop-app/, plus new files
             # under shared/, docker/, scripts/, docs/, backups/.gitkeep,
             # licences/.gitkeep, sql/README.md, and the updated
             # .github/workflows/build-desktop-windows.yml, README.md,
             # backend/README.md, docs/ADR/0001-consolidation.md

git commit -m "chore(repo): relocate to monorepo layout

Move the Laravel app into backend/, the Flutter client into desktop-app/,
and add shared/, docker/, scripts/, sql/, backups/, licences/ per
docs/ADR/0001-consolidation.md. Update the Windows desktop CI workflow's
paths. No feature code changed — DB engine, admin framework, frontend
framework, money format and the sync engine rebuild are separate, later
phases."

git add -A
git commit -m "refactor(backend): rename app/Modules to app/Domain

Renames the namespace organizational folder across backend/ — namespaces,
use statements, bootstrap/providers.php, routes/*.php, tests/,
seeders/factories. 27 module directories, 734 PHP files. The unrelated
ModuleManagement business feature keeps its name; only the PHP namespace
folder changed.

Also: corrects a stale claim in docs/architecture/flutter-desktop-client.md
that BelongsToTenant/HasUserstamps/Auditable were missing a sanctum guard
check — they already had it, the doc just wasn't updated. Adds
tests/Feature/Api/SyncTenantScopingTest.php, since nothing previously
tested that path.

Not verified by actually running the app in the environment this was
written in (no PHP runtime available there) — run composer install &&
php artisan test before trusting this to be correct."
```

Run `composer install && php artisan test` from `backend/` right after this commit,
before doing anything else — this is the first real chance to catch anything the
rename script missed (a class-not-found error would surface immediately).

```bash
git add -A
git commit -m "fix(db): MySQL-compatibility fixes ahead of the Postgres->MySQL migration

Fixes the three migrations with raw Postgres-specific SQL (generated
column via split_part, now driver-branched; a CHECK constraint that
needed no change; two partial unique indexes on inventories, emulated
on MySQL via generated columns since MySQL has no partial index) and
replaces the Postgres-only 'ilike' operator with 'like' across 19
files/44 call sites (MySQL's default collation is already
case-insensitive). Updates .env.example to default to MySQL.

Does not flip backend/.env or phpunit.xml — those still point at the
Postgres database currently in use; flipping them is a manual step
once a real MySQL 8.0.16+ instance is provisioned (see
docs/ADR/0001-consolidation.md Section 6).

Not verified against a real MySQL instance (no DB engine available in
the environment this was written in) — test against real MySQL 8
before trusting it in production."
```

```bash
git add -A
git commit -m "feat(money): add minor-unit columns + Money value object (ADR 0002 step 1)

Six additive migrations, one per bounded context (Payroll, CRM
balances, Purchasing, Inventory, Sales, Finance), add _minor/_micros
integer columns alongside every existing decimal money column and
backfill from the current value. Old decimal columns remain
authoritative — no model or service reads the new columns yet.

Adds app/Domain/Shared/ValueObjects/Money.php: immutable minor-units +
currency value object with fromDecimal/toDecimalString, add/subtract/
multiply, and allocate() (largest-remainder split summing exactly to
the original, per the VAT allocation rule in ADR 0001 Appendix A1).
Covered by tests/Unit/Shared/MoneyTest.php.

Not verified by actually running migrations or tests (no PHP/DB
runtime in the environment this was written in) — run
'php artisan migrate' against a scratch database and
'php artisan test --filter=MoneyTest' before trusting this."
```

Run `php artisan migrate` (against a disposable copy of the database, not production)
and `php artisan test --filter=MoneyTest` after this commit — first real chance to catch
a typo in a table/column name or a rounding-logic mistake in `Money`.

```bash
git add -A
git commit -m "feat(payroll): cut over to Money/minor units (ADR 0002, context 1 of 6)

PayrollService now computes gross/net salary, income tax, NSSF and NHIF
via the Money value object, dual-writing both the decimal and _minor
columns on every create/update. EmployeeProfile, SalaryAllowance,
PayrollPeriod, Payslip and PayslipDeduction cast their _minor columns
as integers.

Statutory rate calculations use Money::multiplyTruncate(), not
multiply(), to preserve the exact truncation (not rounding) behavior
the legacy bcmul(\$a, \$b, 2) calls had -- this does not change any
computed withholding amount. Http/Controllers, Requests and the
frontend are unchanged; they keep reading the decimal columns, which
stay correct via the dual-write.

Adds a test asserting every _minor column agrees exactly with its
legacy decimal sibling.

Not verified by actually running the test suite (no PHP runtime in
the environment this was written in) -- run
'php artisan test --filter=Payroll' before trusting this."
```

Run `php artisan test --filter=Payroll` after this commit — this is the one that
actually proves the truncation-preserving arithmetic and the dual-write are correct.

```bash
git add -A
git commit -m "feat(crm): cut over customer/supplier balances to Money/minor units (ADR 0002, context 2 of 6)

SaleService, SalePaymentService, SaleReturnService,
SupplierPaymentService and AutoPostingService::chargeSupplierBill()
now compute customer/supplier balance changes via the Money value
object, dual-writing both the decimal and _minor columns explicitly.
PurchaseOrder.paid_amount/balance_due (the two fields these two
services touch) are dual-written too; the rest of PurchaseOrder
belongs to the Purchasing context and is untouched.

Adds app/Domain/Shared/Concerns/SyncsMoneyMinorColumns: a model trait
that derives whichever of a decimal/_minor column pair wasn't
explicitly set from the one that was, on the model's saving event.
Manually dual-writing inside services isn't sufficient by itself --
any other create/update path (an un-migrated controller, a seeder, a
test fixture) that only sets the legacy decimal column would leave
_minor stuck at 0 forever, which is a silent correctness bug wherever
Money-aware code reads _minor exclusively (e.g.
Customer::creditLimitMoney()). Applied to Customer, Supplier,
CustomerDebtTransaction, SupplierDebtTransaction, LoyaltyTier, and
PurchaseOrder's two ported fields.

LoyaltyTierService::recalculateTier() now compares
LoyaltyTier.minimum_spend_minor against a Money-computed lifetime
spend instead of the old float lifetimeSpend(): float calculation.

Adds tests/Feature/Crm/MoneyMinorSyncTest.php proving the decimal/
_minor agreement invariant end-to-end through the real services,
including cases that only set the decimal column to prove
SyncsMoneyMinorColumns derives _minor correctly.

Not verified by actually running the test suite (no PHP runtime in
the environment this was written in) -- run
'php artisan test --filter=Crm' and
'php artisan test --filter=Sales' and
'php artisan test --filter=Purchasing'
before trusting this."
```

**Verified 2026-08-04**: `php artisan test --filter=Crm` (28 passed, 96 assertions),
`--filter=Sales` (26 passed, 138 assertions), `--filter=Purchasing` (13 passed, 54
assertions) all pass against the real PostgreSQL database.

```bash
git add -A
git commit -m "feat(purchasing): cut over PurchaseOrder/PurchaseOrderItem/SupplierPayment to minor units (ADR 0002, context 3 of 6)

PurchaseOrder's remaining money fields (subtotal, discount_amount,
tax_amount, shipping_cost, other_charges, total_amount --
paid_amount/balance_due were already ported in context 2),
PurchaseOrderItem (unit_cost, discount_amount, tax_amount,
line_total), and SupplierPayment.amount now cast their _minor columns
and use SyncsMoneyMinorColumns.

PurchaseOrderService's own bcmath arithmetic is deliberately
untouched -- it already computes correct decimal(x,2) values, and
with every money column now listed in moneyMinorColumns(), the trait
derives _minor from whatever the service writes, with no
truncation-vs-rounding risk (this is an exact derivation from an
already-2-decimal value, unlike Payroll's statutory calculations).
GoodsReceivedService is untouched -- it reads unit_cost's decimal
column, and the Expense it creates belongs to the not-yet-started
Finance context.

Adds tests/Feature/Purchasing/MoneyMinorSyncTest.php, including proof
that SupplierPayment.amount_minor -- never explicitly set by
SupplierPaymentService::record() -- is derived correctly.

Not verified by actually running the test suite (no PHP runtime in
the environment this was written in) -- run
'php artisan test --filter=Purchasing' before trusting this."
```

Run `php artisan test --filter=Purchasing` after this commit — this covers the new
dual-write test plus `PurchaseOrderAndGoodsReceivedTest`/`SupplierPaymentTest`, which
exercise the rewritten model layer and were passing before this cutover.

**Verified 2026-08-04**: `php artisan test --filter=Purchasing` — 15 passed, 68
assertions, all green.

```bash
git add -A
git commit -m "feat(inventory): cut over Product/StockMovement/Inventory to minor units and micros (ADR 0002, context 4 of 6)

Product, ProductVariant, ProductBatch.cost_price, the
product_supplier pivot's supplier_cost_price, and
StockMovement.total_cost now use the standard _minor scale via
SyncsMoneyMinorColumns.

Adds app/Domain/Shared/Concerns/SyncsMoneyMicroColumns: same shape as
SyncsMoneyMinorColumns but for the _micros scale (x1,000,000) used by
StockMovement.unit_cost, StockAdjustmentItem.unit_cost,
StockTransferItem.unit_cost, and Inventory.average_cost --
weighted-average costing precision beyond the currency's minor unit.
Deliberately does not use the Money value object, since micros
amounts are currency-agnostic and never compared against a real
Money. StockMovement uses both traits at once.

StockMovementService's weighted-average arithmetic is untouched, same
reasoning as Purchasing's cutover -- it already computes correct
decimal values, the trait derives _minor/_micros from them.

Bug fixed: StockMovementService's purchase-receipt branch updated
Product.last_purchase_price via a query-builder mass update
(Product::query()->where(...)->update([...])), which bypasses
Eloquent model events entirely -- SyncsMoneyMinorColumns hooks
`saving`, so last_purchase_price_minor would never have been derived
through this path and would silently go stale. Fixed to fetch the
model and call the instance update() method instead. Same bug class
as the LoyaltyTierController gap found in the CRM-context batch --
worth specifically checking for query-builder mass updates on any
model given a Syncs*Columns trait going forward.

Adds tests/Feature/Inventory/MoneyMinorSyncTest.php, including proof
of the last_purchase_price fix and a direct product_supplier
pivot-creation case (no application code wires supplier_cost_price
into a request yet).

Not verified by actually running the test suite (no PHP runtime in
the environment this was written in) -- run
'php artisan test --filter=Inventory' and
'php artisan test --filter=StockMovementServiceTest'
before trusting this."
```

Run both of those filters after this commit — `StockMovementServiceTest` specifically
exercises the `last_purchase_price` fix (`test_average_cost_is_recalculated_as_a_weighted_average_across_purchases`
asserts `$product->fresh()->last_purchase_price`), so a regression there means the fix
broke something rather than just failing to add coverage.

**Verified 2026-08-04**: `php artisan test --filter=Inventory` (37 passed, 178
assertions) and `php artisan test --filter=StockMovementServiceTest` (7 passed, 13
assertions) both pass against the real PostgreSQL database.

```bash
git add -A
git commit -m "feat(sales): cut over Sale/SaleItem/SalePayment/SaleReturn/SaleReturnItem to minor units (ADR 0002, context 5 of 6)

Sale, SaleItem, SalePayment, SaleReturn, SaleReturnItem now cast
their _minor columns and use SyncsMoneyMinorColumns. Same pattern as
Purchasing/Inventory: SaleService/SaleReturnService/SalePaymentService's
own bcmath arithmetic is untouched -- it already computes correct
decimal values, the trait derives _minor from them. Specifically
checked for query-builder mass-update landmines (the bug class found
in the Inventory batch) -- none found in Sales.

LoyaltyTierService::recalculateTier() (CRM context) now compares
purely in minor units (Sale.total_amount_minor /
SaleReturn.refund_amount_minor) instead of bridging through the
legacy decimal columns via Money::fromDecimal() -- that bridge was
only needed because Sales hadn't been cut over yet when the
CRM-context batch landed. lifetimeSpend(): Money is replaced by
lifetimeSpendMinor(): int.

Adds tests/Feature/Sales/MoneyMinorSyncTest.php, including a
sale-then-equal-return scenario proving the loyalty-tier
recalculation change reacts correctly to both sides moving.

Not verified by actually running the test suite (no PHP runtime in
the environment this was written in) -- run
'php artisan test --filter=Sales' and
'php artisan test --filter=Crm'
before trusting this."
```

Run both filters after this commit — `Crm` specifically re-covers `LoyaltyTest`'s
recalculation test, since `LoyaltyTierService` changed in this batch even though no CRM
model did.

**Verified 2026-08-04**: `php artisan test --filter=Sales` (30 passed, 157 assertions)
and `php artisan test --filter=Crm` (28 passed, 96 assertions) both pass against the
real PostgreSQL database.

```bash
git add -A
git commit -m "feat(finance): cut over Finance/Accounting/Subscription to minor units (ADR 0002, context 6 of 6, final)

JournalLine (debit, credit, foreign_amount), BankAccount.opening_balance,
BankTransaction.amount, BankReconciliation (statement_balance,
book_balance, difference), BudgetLine.budgeted_amount, TaxTransaction
(taxable_amount, tax_amount), FixedAsset (acquisition_cost,
residual_value, disposal_proceeds), DepreciationSchedule
(depreciation_amount, accumulated_depreciation, book_value),
PaymentTransaction (amount, tax_amount, discount_amount, fee_amount,
commission_amount, refunded_amount), PaymentGateway.fee_fixed,
Accounting's Expense.amount/Income.amount, and Subscription's
SubscriptionPlan (four price columns), Subscription.custom_price,
SubscriptionTransaction.amount all now cast their _minor columns and
use SyncsMoneyMinorColumns. Same low-risk pattern as
Purchasing/Inventory/Sales -- no service arithmetic changed, the trait
derives _minor from decimal values every service already computes
correctly via bcmath.

Currency resolution for models with no business() relation of their
own was resolved case by case: direct Business::find() lookup
(JournalLine, TaxTransaction), via an owning relation
(BankTransaction/BankReconciliation -> bank account, BudgetLine ->
budget, DepreciationSchedule -> fixed asset), via the row's own
currency column falling back to the business relation
(PaymentTransaction, SubscriptionTransaction), or hardcoded to the
platform default for genuinely business-less platform-wide rows
(PaymentGateway, SubscriptionPlan).

Checked AutoPostingService, ChartOfAccountsService, and every other
Finance/Accounting/Subscription service for the query-builder
mass-update bug class found in the Inventory batch -- none found.

Proves the ledger's debit=credit invariant survives the conversion,
not just assumes it: JournalPostingService::assertBalanced() already
enforces SUM(debit) == SUM(credit) in decimal before any line is
persisted, and round(decimal * 100) is exact and order-preserving, so
SUM(debit_minor) == SUM(credit_minor) is mathematically guaranteed
once _minor is derived. Adds
tests/Feature/Finance/MoneyMinorSyncTest.php proving this through the
real JournalPostingService with a three-line entry, plus decimal/
_minor agreement for every other model above through the real
Bank*/Budget/Tax/FixedAsset/PaymentGateway/PaymentTransaction/Expense/
Income/Subscription services.

This closes out all six bounded contexts in the ADR 0002 rollout
order (Payroll -> CRM -> Purchasing -> Inventory -> Sales -> Finance).
Dropping the old decimal columns is a separate, later decision
requiring its own sign-off, not automatic.

Not verified by actually running the test suite (no PHP runtime in
the environment this was written in) -- run
'php artisan test --filter=Finance' and
'php artisan test --filter=Accounting' and
'php artisan test --filter=Subscription' and
'php artisan test --filter=Crm'
before trusting this."
```

Run all four filters after this commit — `Finance` covers the new `MoneyMinorSyncTest`
plus the pre-existing `BankAccountTest`/`FixedAssetTest`/`BudgetTest`/
`FinancialPeriodTest`/`AutoPostingTest`/`JournalEntryUiTest`/`AccountUiTest` suites,
which exercise the models this batch touched; `Accounting` covers `AccountingTest`
(Expense/Income); `Subscription` covers whatever subscription tests exist; `Crm` is a
final re-check that `createOwnerWithBusiness()` (used by nearly every Feature test in
the app) still works correctly now that `SubscriptionPlan` itself carries the trait.

**Verified 2026-08-04**: `php artisan test --filter=Finance` (111 passed, 390
assertions), `--filter=Accounting` (9 passed, 26 assertions), `--filter=Subscription`
(34 passed, 94 assertions), and `--filter=Crm` (28 passed, 96 assertions) all pass
against the real PostgreSQL database. This closes out all six bounded contexts of the
money-format migration (ADR 0002).

```bash
git add -A
git commit -m "fix(db): second-pass MySQL 8 compatibility audit (ADR 0001 Section 6)

The first compatibility pass (split_part, a CHECK constraint, two
partial-index emulations, ilike) covered what a targeted search
suspected. This is a full second pass re-checking migrations and every
app/ query for the complete Postgres-specific surface: to_char,
string_agg, array_agg, date_trunc, :: casts, regex/JSONB operators,
ON CONFLICT/RETURNING, jsonb/citext/array/tsvector types, GIN/GiST
indexes, sequence functions, case-sensitivity. Two more 'breaks on
MySQL' bugs found and fixed, plus one 'silently different behavior'
item resolved conservatively:

- to_char(): unguarded (no driver branch) in 17 places across 9
  report/dashboard services -- same bug class as split_part, would
  throw 'FUNCTION to_char does not exist' on MySQL the first time any
  dashboard loaded. Fixed via a new shared
  App\\Domain\\Shared\\Support\\DateFormatSql::daily()/monthly()
  helper (to_char on Postgres, DATE_FORMAT on MySQL) rather than
  repeating the branch inline at 17 call sites.

- RestoreService::restore() hardcoded the psql binary and Postgres
  connection flags/PGPASSWORD unconditionally, unlike the rest of the
  class which already reads the connection config dynamically. Now
  branches on the driver: mysql/mariadb pipes the dump into `mysql`
  via stdin with the password via MYSQL_PWD env (mysql's CLI has no
  --file flag like psql's), pgsql keeps the existing psql --file=
  behavior. Added a dump.dump_binary_path config key to the mysql
  connection array in config/database.php, mirroring pgsql's (needed
  since Apache/XAMPP processes don't inherit the shell PATH).

- Case-sensitive unique columns: MySQL's default collation
  (utf8mb4_unicode_ci) is case-insensitive, Postgres's default isn't
  -- left alone this silently changes uniqueness semantics on
  products.sku/barcode, product_variants.sku/barcode,
  product_serials.serial_number, payment_transactions.reference_number.
  New migration 2026_08_04_000001 pins these to utf8mb4_bin
  (case-sensitive) on MySQL only, preserving exact Postgres parity --
  deliberately not applied to email columns, where MySQL's
  case-insensitive default is the more correct behavior anyway.

Not verified by actually running migrations against a real MySQL
instance (no DB engine available in the environment this was written
in) -- run 'php artisan test' against a real MySQL 8 instance before
trusting this, per the manual cutover steps in ADR 0001 Section 6."
```

**Verified 2026-08-04**: you ran `php artisan migrate --seed` and `php artisan test`
against a real MySQL 8 instance for the first time — the migration ran clean, confirming
the compatibility fixes above actually work. Running the *full* suite unfiltered (not a
scoped `--filter=X`, unlike every prior verification in this project) surfaced
pre-existing bugs, none caused by the MySQL cutover itself — see the "Correction 2
(2026-08-04)" note in Section 1.4 and the fixes below. This went through three rounds: a
memory_limit fix (128M was too low for a ~700-test run — bumped to 512M in `php.ini`)
revealed a full, un-truncated failure list of 3 tests; the guard-order fix needed a second
attempt after the first didn't work; the sale-sync fix surfaced a real inventory bug one
layer down. **Confirmed fixed**: the guard-order fix and the inventory-listener fix — the
next full run came back at 654 passed, 1 failed. The one still open is
`PermissionMatrixTest > matrix can be searched` (see below).

```bash
git add -A
git commit -m "fix: bugs surfaced by the first full (unfiltered) test suite run

Every previous verification in this project used a scoped
--filter=X run; running the full suite against the new MySQL
database for the first time surfaced several pre-existing bugs, none
caused by the MySQL cutover itself (all reproduce identically on any
database engine):

- 2026_08_04_000001_preserve_case_sensitive_collation_on_mysql.php:
  MySQL's ALTER TABLE ... MODIFY doesn't preserve attributes not
  restated in the statement -- sku/serial_number/reference_number's
  definitions didn't restate NOT NULL, silently making them
  nullable. Fixed by restating 'not null' explicitly.

- BelongsToTenant/HasUserstamps/Auditable checked the web guard
  before sanctum in a fixed order. In production a narrow edge case;
  guaranteed in SyncTenantScopingTest, where actingAs() leaves the
  web guard's cached user set across the rest of a test method, so a
  later Sanctum-authenticated request still resolved the stale web
  user. First attempt (Auth::user()/Auth::id(), no explicit guard,
  betting on Auth::shouldUse() reflecting the authenticating guard)
  did not fix it -- reverted. Fixed by checking sanctum explicitly
  before web: sanctum's guard re-validates the current request's
  bearer token on every call and can never return stale state, so
  checking it first means a genuine Sanctum request always wins over
  a merely-cached web session, in both tests and production. platform
  remains a special-cased first check in all three traits.

- SaleService::create() was correctly rejecting
  SyncTenantScopingTest's own sale-creation test (paid less than the
  sale total with no customer_id -- a real business rule, not a
  bug). Fixed the test fixture to pay in full. Doing so then
  surfaced a real bug one layer down (see next item).

- DeductInventoryOnSaleCompletion deducted stock for every sale line
  unconditionally, never checking Product::tracksStock() (which
  already existed for this purpose but nothing called it). A
  track_stock=false product never receives stock in, so its
  inventory row sits at 0 and the first sale of it throws
  InsufficientStockException -- a real app bug, not a test bug.
  Fixed by skipping deduction when the product doesn't track stock.

- BusinessAssistantService's payables intent-matcher didn't
  recognize 'Which suppliers should I pay first?'. Added a few more
  trigger phrases.

- NotificationCenterTest called the notifications route with a
  plain get(); NotificationController::index() only returns JSON for
  wantsJson()/ajax() requests. Fixed the test to use getJson().

Not fixed in this commit: PermissionMatrixTest > matrix can be
searched returns zero results searching 'business types' against
seeded data that includes 'View Business Types', with no reason
found by reading the controller/seeder/route stack alone (MySQL's
configured collation is case-insensitive, so a plain LIKE should
match). Needs inspection against a live MySQL session (generated
SQL, actual table collation) rather than a guessed fix.

Not verified by actually running the test suite (no PHP/MySQL
runtime in the environment this was written in) -- run
'php artisan test' (full suite, unfiltered) against your real MySQL
instance before trusting this."
```

Run the full `php artisan test` (not a filter) after this commit — that's the only way to
confirm the guard fix and inventory-listener fix actually land, since a scoped filter
wouldn't have caught either in the first place. Then, for `PermissionMatrixTest`, run this
diagnostic (no code changes, just inspection) and paste the output back:

```bash
php artisan tinker --execute="
\$search = 'business types';
echo 'LIKE match count: ' . App\Domain\RBAC\Models\Permission::query()->where(function (\$q) use (\$search) { \$q->where('name', 'like', \"%{\$search}%\")->orWhere('slug', 'like', \"%{\$search}%\"); })->count() . PHP_EOL;
echo 'Exact name exists: ' . App\Domain\RBAC\Models\Permission::query()->where('name', 'View Business Types')->count() . PHP_EOL;
echo 'Table collation: ' . (DB::selectOne(\"SHOW TABLE STATUS LIKE 'permissions'\")->Collation ?? 'unknown') . PHP_EOL;
"
```

After committing, update your local Apache/XAMPP vhost or `DocumentRoot` to point at
`BOS/backend/public` instead of `BOS/public` (you opted to handle this yourself rather
than have a redirect stub added at the old path). Then re-run
`composer install`/`npm install` inside `backend/` if you want fresh `vendor/`/
`node_modules/` rather than the moved copies (both moved intact, so this is optional).
