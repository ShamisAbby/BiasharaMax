# Changelog

All notable changes to BiasharaMax are documented here, sprint by sprint.

## Unreleased — First full-suite test run surfaces and fixes pre-existing bugs

Migrated to a real MySQL 8 instance and ran `php artisan test` unfiltered for the first
time in this project (every prior verification was a scoped `--filter=X` run). Found one
bug in today's own collation migration, one real (if narrow) guard-resolution bug, one
real inventory-deduction bug, and two smaller pre-existing test bugs — none of the latter
four caused by the MySQL cutover itself, all reproduce identically on any database engine.
One failure (`PermissionMatrixTest > matrix can be searched`) is still open — see Known
issues below.

### Fixed

- `2026_08_04_000001_preserve_case_sensitive_collation_on_mysql.php`: MySQL's
  `ALTER TABLE ... MODIFY` doesn't preserve attributes that aren't restated in the
  statement — the migration's column definitions for `sku`/`serial_number`/
  `reference_number` didn't restate `NOT NULL`, which silently made those columns
  nullable. Fixed by restating `not null` explicitly for every column that needs it
  (`barcode` correctly stays nullable).
- `BelongsToTenant`/`HasUserstamps`/`Auditable`: all three checked the `web` guard before
  `sanctum` in a fixed order. In production a narrow edge case; in
  `SyncTenantScopingTest` it's guaranteed — `actingAs()` leaves the `web` guard's cached
  user set across the rest of a test method, so a later Sanctum-authenticated request
  still resolved tenant/actor from the stale `web` user. First attempt (resolving via
  `Auth::user()`/`Auth::id()`, betting on `Auth::shouldUse()` reflecting the
  authenticating guard) did not fix it and was reverted. Fixed by checking `sanctum`
  explicitly before `web`: `sanctum`'s guard re-validates the current request's bearer
  token on every call and can never return stale state, so checking it first means a
  genuine Sanctum request always wins over a merely-cached `web` session, in both tests
  and production. `platform` remains a special-cased first check in all three traits.
- `SaleService::create()` was correctly rejecting `SyncTenantScopingTest`'s own
  sale-creation test — the test paid less than the sale total with no `customer_id`, a
  real "who carries this debt?" business rule, not a bug. Fixed the test fixture to pay
  in full — which then surfaced a real bug one layer down (next item).
- `DeductInventoryOnSaleCompletion` (the `SaleCompleted` listener) deducted stock for
  every sale line unconditionally, never checking `Product::tracksStock()` — a method
  that already existed for this exact purpose but nothing in the sale-completion path
  called it. A `track_stock = false` product (a service, a made-to-order item) never
  receives a stock-`IN` movement either, so its inventory row sits at 0 forever, and the
  first sale of it throws `InsufficientStockException`. Fixed by skipping the deduction
  when the product doesn't track stock.
- `BusinessAssistantService`'s payables intent-matcher didn't recognize "Which suppliers
  should I pay first?" as a payables question. Added a few more trigger phrases.
- `NotificationCenterTest` called the notifications route with a plain `get()`;
  `NotificationController::index()` only returns JSON for `wantsJson()`/`ajax()`
  requests, so it rendered the Inertia HTML page instead — fixed the test to use
  `getJson()`.

- `PermissionMatrixController::index()`'s search used a plain `like`, which is
  case-sensitive on Postgres — `business types` never matched the seeded `View Business
  Types` permission. It only ever "worked" on MySQL because that engine's default
  collation happens to be case-insensitive, an implicit dependency rather than a
  deliberate choice. Fixed to wrap both sides in `LOWER()` via `whereRaw`, so the match is
  explicitly case-insensitive on every engine.

- **`backend/.env` and `phpunit.xml` switched from PostgreSQL to MySQL.** Discovered via
  a tinker diagnostic (chasing the `PermissionMatrixTest` bug above) that both still said
  `DB_CONNECTION=pgsql` — `docker/docker-compose.yml` had flagged this as a pending manual
  step that never actually happened. Every `php artisan test` run logged in this
  changelog entry, including the ones that "confirmed" the guard-order and inventory
  fixes, almost certainly ran against Postgres, not the MySQL 8 instance this whole phase
  is about. The fixes themselves are still correct (engine-agnostic bugs), but Phase 3's
  actual goal — proving the app runs on MySQL — had only been checked by a single manual
  `migrate --seed` run, never by the test suite. Both files now point at
  `127.0.0.1:3306`/`biasharamax`/`biasharaos_testing`, matching
  `docker/docker-compose.yml`'s `db` service credentials.

### Known issues

- `biasharaos_testing` (the MySQL database `phpunit.xml` now targets) needs to actually
  exist before `php artisan test` will run — create it (`CREATE DATABASE
  biasharaos_testing;`) if it doesn't, then run the full suite once more. This is the
  first time the suite will have run against real MySQL; treat every previous "passed"
  count in this entry as unverified against MySQL until this run confirms it.

## Unreleased — DB engine cutover: second-pass MySQL compatibility audit

### Fixed

Second, more thorough pass over `database/migrations/` and every `app/` query for
Postgres-specific SQL, ahead of the actual MySQL cutover. Found two more "breaks on
MySQL" bugs the first pass missed, plus one "silently different behavior" item:

- `to_char()` (Postgres-only date formatting) was unguarded in 17 places across 9
  report/dashboard services — would throw `FUNCTION to_char does not exist` on MySQL.
  Fixed via a new shared `App\Domain\Shared\Support\DateFormatSql` helper
  (`daily()`/`monthly()`) instead of repeating the driver branch at every call site.
- `RestoreService::restore()` hardcoded the `psql` binary and Postgres-only connection
  flags — not driver-branched despite the rest of the class already reading connection
  config dynamically. Now branches: MySQL/MariaDB pipes the dump into `mysql` via stdin
  (password via `MYSQL_PWD` env), Postgres keeps the existing `psql --file=` behavior.
  Added a `dump.dump_binary_path` config key to the `mysql` connection array.
- Case-sensitive unique columns (`products.sku`/`barcode`, `product_variants.sku`/
  `barcode`, `product_serials.serial_number`, `payment_transactions.reference_number`):
  MySQL's default collation is case-insensitive, Postgres's isn't. New migration pins
  these columns to `utf8mb4_bin` on MySQL only, preserving exact Postgres parity.
  Deliberately not applied to `email` columns, where case-insensitivity is the more
  correct behavior anyway.

### Added

`database/migrations/2026_08_04_000001_preserve_case_sensitive_collation_on_mysql.php`,
`app/Domain/Shared/Support/DateFormatSql.php`.

## Unreleased — Money format migration complete: Finance cutover (sixth, final context) verified

### Verified

The Sales batch (previous entry below) passed a real test run: `php artisan test
--filter=Sales` — 30 passed, 157 assertions. `php artisan test --filter=Crm` — 28
passed, 96 assertions, confirming the `LoyaltyTierService` change didn't break
`LoyaltyTest`. Both green.

### Changed

Cut Finance over — the sixth and final bounded context. Every remaining
money-carrying model gets `SyncsMoneyMinorColumns` and nothing else, same low-risk
pattern as Purchasing/Inventory/Sales: `JournalLine` (debit, credit, foreign_amount),
`BankAccount.opening_balance`, `BankTransaction.amount`, `BankReconciliation`
(statement_balance, book_balance, difference — the first column in this whole
migration allowed to be negative), `BudgetLine.budgeted_amount`, `TaxTransaction`
(taxable_amount, tax_amount), `FixedAsset` (acquisition_cost, residual_value, nullable
disposal_proceeds), `DepreciationSchedule` (depreciation_amount,
accumulated_depreciation, book_value), `PaymentTransaction` (amount, tax_amount,
discount_amount, fee_amount, commission_amount, refunded_amount),
`PaymentGateway.fee_fixed`, Accounting's `Expense.amount`/`Income.amount` (merged into
Finance), and Subscription's platform-billing tables — `SubscriptionPlan` (four price
columns), `Subscription.custom_price`, `SubscriptionTransaction.amount`.

Currency resolution for models with no `business()` relation of their own was resolved
case by case: direct `Business::find()` lookup (`JournalLine`, `TaxTransaction`),
via an owning relation (`BankTransaction`/`BankReconciliation` → bank account,
`BudgetLine` → budget, `DepreciationSchedule` → fixed asset), via the row's own
`currency` column falling back to the business relation (`PaymentTransaction`,
`SubscriptionTransaction`), or hardcoded to the platform default for genuinely
business-less platform-wide rows (`PaymentGateway`, `SubscriptionPlan`).

Checked `AutoPostingService`, `ChartOfAccountsService`, and every other
Finance/Accounting/Subscription service for the query-builder-mass-update bug class
found in the Inventory batch — none found.

Proved the debit=credit invariant survives the conversion, not just assumed it:
`JournalPostingService::assertBalanced()` already enforces `SUM(debit) == SUM(credit)`
in decimal before any line is persisted, and `round(decimal * 100)` is exact and
order-preserving, so `SUM(debit_minor) == SUM(credit_minor)` is mathematically
guaranteed once `_minor` is derived.

This closes out all six bounded contexts in the rollout order (Payroll → CRM →
Purchasing → Inventory → Sales → Finance). Dropping the old decimal columns is a
separate, later decision requiring its own sign-off.

### Added

`tests/Feature/Finance/MoneyMinorSyncTest.php` — proves the decimal/`_minor`
agreement invariant through the real `JournalPostingService`/`BankAccountService`/
`BankReconciliationService`/`BudgetService`/`TaxService`/`FixedAssetService`/
`PaymentGatewayService`/`PaymentTransactionService`/`ExpenseService`/`IncomeService`/
`SubscriptionService` flows, plus a dedicated test proving
`SUM(debit_minor) == SUM(credit_minor)` on a three-line (not just 1:1) journal entry.

### Verified

`php artisan test --filter=Finance` — 111 passed, 390 assertions. `php artisan test
--filter=Accounting` — 9 passed, 26 assertions. `php artisan test --filter=Subscription`
— 34 passed, 94 assertions. `php artisan test --filter=Crm` — 28 passed, 96 assertions,
confirming `createOwnerWithBusiness()` still works now that `SubscriptionPlan` carries
the sync trait. All green. **This closes out all six bounded contexts of the money
format migration: Payroll → CRM → Purchasing → Inventory → Sales → Finance.**

## Unreleased — Money format migration: Sales cutover (fifth context); Inventory batch verified

### Verified

The Inventory batch (previous entry below) passed a real test run: `php artisan test
--filter=Inventory` — 37 passed, 178 assertions. `php artisan test
--filter=StockMovementServiceTest` — 7 passed, 13 assertions, confirming the
`last_purchase_price` fix. Both green.

### Changed

Cut Sales over — the fifth of six bounded contexts. `Sale`, `SaleItem`, `SalePayment`,
`SaleReturn`, `SaleReturnItem` all now cast their `_minor` columns and use
`SyncsMoneyMinorColumns`. Same pattern as Purchasing/Inventory: the services' own
bcmath arithmetic was untouched, the trait derives `_minor` from what they already
compute correctly. Specifically checked for query-builder mass-update landmines this
time (the class of bug found in Inventory) — none found in Sales.

`LoyaltyTierService::recalculateTier()` (CRM context) now compares purely in minor units
(`Sale.total_amount_minor`/`SaleReturn.refund_amount_minor`) instead of bridging through
the legacy decimal columns via `Money::fromDecimal()` — that bridge was only needed
because Sales hadn't been cut over yet when the CRM batch landed.

### Added

`tests/Feature/Sales/MoneyMinorSyncTest.php` — proves the decimal/`_minor` agreement
invariant through the real sale/payment/return flow, and specifically proves the
loyalty-tier recalculation change with a sale-then-equal-return scenario.

### Verified

`php artisan test --filter=Sales` — 30 passed, 157 assertions. `php artisan test
--filter=Crm` — 28 passed, 96 assertions, confirming the `LoyaltyTierService` change
didn't break `LoyaltyTest`. Both green.

## Unreleased — Money format migration: Inventory cutover (fourth context); Purchasing batch verified

### Verified

The Purchasing batch (previous entry below) passed a real test run: `php artisan test
--filter=Purchasing` — 15 passed, 68 assertions, all green.

### Changed

Cut Inventory over — the fourth of six bounded contexts, and the trickiest so far since
it's the only one with two dual-write scales. Standard `_minor`: `Product`,
`ProductVariant`, `ProductBatch.cost_price`, the `product_supplier` pivot's
`supplier_cost_price`, `StockMovement.total_cost`. The special `_micros` scale (x1,000,000,
for weighted-average costing precision) via a new
`App\Domain\Shared\Concerns\SyncsMoneyMicroColumns` trait (same shape as
`SyncsMoneyMinorColumns` but deliberately doesn't use the `Money` value object, since
micros amounts are currency-agnostic and never compared against a real `Money`):
`StockMovement.unit_cost`, `StockAdjustmentItem.unit_cost`, `StockTransferItem.unit_cost`,
`Inventory.average_cost`. `StockMovementService`'s weighted-average arithmetic was not
rewritten, same reasoning as Purchasing — it already computes correct decimal values, the
trait derives the rest.

**Bug found and fixed**: `StockMovementService`'s purchase-receipt branch updated
`Product.last_purchase_price` via a query-builder mass update
(`Product::query()->where(...)->update([...])`), which bypasses Eloquent model events —
`SyncsMoneyMinorColumns` hooks `saving`, so `last_purchase_price_minor` would never have
been derived through this path. Fixed to fetch the model and call the instance `update()`
instead. Same bug class as the `LoyaltyTierController` gap from the CRM-context batch.

### Added

`tests/Feature/Inventory/MoneyMinorSyncTest.php` — proves the decimal/`_minor` and
decimal/`_micros` agreement invariants through the real product/stock-movement/
adjustment/transfer services, including the `last_purchase_price` fix.

### Verified

`php artisan test --filter=Inventory` — 37 passed, 178 assertions. `php artisan test
--filter=StockMovementServiceTest` — 7 passed, 13 assertions, confirming the
`last_purchase_price` fix didn't break the existing weighted-average-cost test. Both
green.

## Unreleased — Money format migration: Purchasing cutover (third context); CRM batch verified

### Verified

The CRM-balances batch (previous entry below) passed a real test run: `php artisan test
--filter=Crm` (28 passed, 96 assertions), `--filter=Sales` (26 passed, 138 assertions),
`--filter=Purchasing` (13 passed, 54 assertions) — all green, including the new
`MoneyMinorSyncTest`.

### Changed

Cut Purchasing over to `_minor` columns — the third of six bounded contexts.
`PurchaseOrder`'s remaining money fields (subtotal, discount_amount, tax_amount,
shipping_cost, other_charges, total_amount), `PurchaseOrderItem` (unit_cost,
discount_amount, tax_amount, line_total), and `SupplierPayment.amount` now cast their
`_minor` columns and use `SyncsMoneyMinorColumns`. Unlike Payroll/CRM,
`PurchaseOrderService`'s own bcmath-based arithmetic was left untouched — since every
money column is now listed in `moneyMinorColumns()`, the trait derives `_minor` from
whatever correct decimal value the service already computes, with no
truncation-vs-rounding risk (unlike Payroll's statutory calculations, this is a lossless
derivation from an already-2-decimal-place value, not a recomputation). `GoodsReceivedService`
was not touched — it reads `unit_cost`'s decimal column, and the `Expense` it creates
belongs to the not-yet-started Finance context.

### Added

`tests/Feature/Purchasing/MoneyMinorSyncTest.php` — proves the decimal/`_minor`
agreement invariant through the real `PurchaseOrderService`/`SupplierPaymentService`
flow, including that `SupplierPayment.amount_minor` (never explicitly set by the
service) is derived correctly.

### Verified

`php artisan test --filter=Purchasing` — 15 passed, 68 assertions, all green.

## Unreleased — Money format migration: CRM balances cutover (second context)

### Added

`App\Domain\Shared\Concerns\SyncsMoneyMinorColumns` — a model trait that hooks `saving`
and derives whichever of a decimal/`_minor` column pair wasn't explicitly set from the
one that was, so any code path (controller, seeder, console command, test fixture) that
only knows about the legacy decimal column no longer leaves `_minor` silently stuck at 0.
Found this was a real gap, not a theoretical one: manually dual-writing inside the
services rewritten for this cutover isn't enough on its own, since e.g.
`LoyaltyTierController`'s store/update requests only ever collect the decimal
`minimum_spend` field, and the existing `LoyaltyTest.php`/`SalesAndPosTest.php` fixtures
create `Customer`/`LoyaltyTier` rows with only the decimal field set. Applied to
`Customer`, `Supplier`, `CustomerDebtTransaction`, `SupplierDebtTransaction`,
`LoyaltyTier`, and `PurchaseOrder` (its two already-ported fields only). This is now the
intended pattern going into the remaining contexts, alongside (not instead of) rewriting
services to compute via `Money` where rounding/precision actually matters.

`tests/Feature/Crm/MoneyMinorSyncTest.php` — proves the decimal/`_minor` agreement
invariant end-to-end through the real services (credit sale → payment → void,
store-credit return, supplier payment, loyalty tier creation + recalculation), including
cases that deliberately exercise the "only the decimal column was set" path.

### Changed

Cut CRM balances over to `Money`/minor units — the second of six bounded contexts.
`SaleService` (credit-sale charging, void reversal), `SalePaymentService`,
`SaleReturnService` (store-credit refunds), `SupplierPaymentService`, and
`AutoPostingService::chargeSupplierBill()` now compute customer/supplier balance changes
via `Money`, dual-writing both columns explicitly. `PurchaseOrder.paid_amount`/
`balance_due` are also dual-written by these two services — the rest of `PurchaseOrder`
(subtotal, tax, shipping, totals) belongs to the Purchasing context and wasn't touched.
`LoyaltyTierService::recalculateTier()` now compares `LoyaltyTier.minimum_spend_minor`
against a `Money`-computed lifetime spend, instead of the old float `lifetimeSpend():
float` calculation — the spend side still sums `Sale`/`SaleReturn`'s decimal columns
(not their `_minor` siblings), since Sales is a later context and those columns aren't
guaranteed populated yet.

Not yet run by the user — `MoneyMinorSyncTest` plus the existing `SupplierPaymentTest`,
`SalesAndPosTest`, `LoyaltyTest`, and `SaleReturnTest` suites (which exercise the same
rewritten services) are the next verification step.

## Unreleased — Money format migration: Payroll cutover (first context)

### Changed

Cut Payroll over to `Money`/minor units — the first of six bounded contexts in
`docs/ADR/0002-money-format-migration.md`'s rollout order. `PayrollService` now computes
gross/net salary, income tax, NSSF, and NHIF via `Money`, dual-writing both the decimal
and `_minor` columns on every create/update; `EmployeeProfile`, `SalaryAllowance`,
`PayrollPeriod`, `Payslip`, and `PayslipDeduction` all cast their new `_minor` columns as
integers. Statutory rate calculations use `Money::multiplyTruncate()` rather than
`multiply()` to preserve the exact truncation behavior the old `bcmul($a, $b, 2)` calls
had — this cutover changes no computed withholding amount. Added a test asserting every
`_minor` column agrees exactly with its decimal counterpart. Http/Controllers, Requests,
and the frontend were not touched — they keep working against the decimal columns,
which remain fully correct via the dual-write.

## Unreleased — Money format migration: schema + Money value object

### Added

Six additive migrations (`2026_08_03_000001`–`000006`), one per bounded context per the
rollout order in `docs/ADR/0002-money-format-migration.md`: add every `_minor`/`_micros`
integer column alongside its existing decimal column and backfill from the current
value. Nothing reads the new columns yet — old decimal columns stay authoritative until
each context's model/service layer is switched over (not started). `unit_cost` and
`average_cost` (Inventory) backfill to a `_micros` scale (x1,000,000) instead of the
standard minor-unit scale (x100), preserving the 4-decimal-place precision weighted-
average costing needs.

Added `app/Domain/Shared/ValueObjects/Money.php` — immutable integer-minor-units +
currency value object with `fromDecimal`/`toDecimalString` (bridging the old columns
during the transition), `add`/`subtract`/`multiply`, and `allocate()` (largest-remainder
split across N parts that always sums exactly to the original — the VAT/discount
allocation rule from `docs/ADR/0001-consolidation.md` Appendix A1). Covered by
`tests/Unit/Shared/MoneyTest.php`, not run (no PHP runtime in the environment this was
written in) — run `php artisan test --filter=MoneyTest` before trusting it.

## Unreleased — Money format migration plan

### Added

`docs/ADR/0002-money-format-migration.md` — full column inventory (60 of 150 `decimal`
columns are actual money and convert; 90 are quantities/percentages/exchange
rates/hours/days/metrics and stay decimal), the `_minor`/`_micros` naming convention
(the two 4-decimal-place cost-tracking columns, `unit_cost` and `average_cost`, need
higher-than-cent precision for weighted-average costing and get their own scale rather
than losing precision to a blanket ×100), a six-bounded-context dual-write rollout order
(Payroll → CRM → Purchasing → Inventory → Sales → Finance, ledger last since it has the
highest blast radius), and the rounding/allocation rules tying into the already-decided
VAT per-line-rounding approach. No migration files written yet — flagged as needing
sign-off given how expensive a wrong design would be to unwind once the ledger is
touched.

## Unreleased — MySQL compatibility fixes (DB engine migration, code portion)

### Changed

Audited all 179 migrations and every `app/` query for PostgreSQL-specific SQL ahead of
the Postgres → MySQL 8 migration. Fixed the three migrations with raw SQL (a generated
column using `split_part`, now driver-branched to also support MySQL's
`substring_index`; a CHECK constraint needing no change, both engines support identical
syntax on MySQL 8.0.16+; two partial unique indexes on `inventories`, which have no
MySQL equivalent — now emulated via generated columns + plain unique indexes on MySQL,
same NULL-is-distinct trick a partial index relies on) and replaced the Postgres-only
`'ilike'` query operator with `'like'` across 19 files/44 call sites (MySQL's default
collation is already case-insensitive, so this is an exact equivalent, not an
approximation). See `docs/ADR/0001-consolidation.md` Section 6 for the full list and
what's still a manual infrastructure step (provisioning MySQL, flipping `.env`/
`phpunit.xml`, running migrations fresh) rather than a code change.

## Unreleased — Modules → Domain rename, sanctum tenant-scoping test

### Changed

**`app/Modules` renamed to `app/Domain`** across the whole `backend/` tree — namespaces,
`use` statements, `bootstrap/providers.php`, `routes/*.php`, `tests/`, seeders/factories.
27 module directories, 734 PHP files under the renamed tree. The unrelated
`ModuleManagement` business feature (which lets platform admins control which
features/modules a subscription plan includes) keeps its name — only the PHP namespace
organizational folder was renamed, not that domain concept. Verified by grep: zero
remaining `App\Modules`/`app/Modules` references. Not verified by actually running the
app (no PHP runtime in the environment this was done in) — run `php artisan test`
locally before trusting it.

**Corrected a stale claim in `docs/architecture/flutter-desktop-client.md`**: it
described `BelongsToTenant`/`HasUserstamps`/`Auditable` as missing a `sanctum` guard
check. Re-reading the actual trait code showed this was already fixed in a prior,
uncommitted change — only the doc wasn't updated. Added
`tests/Feature/Api/SyncTenantScopingTest.php`, since there was previously zero test
coverage proving Sanctum-authenticated requests are correctly tenant-scoped and stamp a
real actor (constraint: every tenant-scoped model must have a test proving cross-tenant
access fails).

## Unreleased — Repo relocation (monorepo layout)

### Changed

**Directory structure only — no feature code.** The Laravel app moved from the repo
root into `backend/`; `flutter_desktop_client/` moved to `desktop-app/`. Added
`shared/` (API contracts, database schema docs, translations — each currently a
placeholder README describing what will live there), `docker/` (nginx/php/mysql/redis,
targeting MySQL per the decision below — `backend/.env` still runs PostgreSQL until
that migration actually happens), `scripts/` (`install.sh`/`.bat`, `build-desktop.sh`,
`backup-db.sh`, `restore-db.sh`, `deploy.sh`, `seed-demo.sh`), `sql/`, `backups/`
(gitignored), `licences/` (gitignored). Updated `.github/workflows/build-desktop-windows.yml`
paths and both root and `backend/README.md` for the new layout. See
`docs/ADR/0001-consolidation.md` for the full audit and the decisions this sets up:
migrate to MySQL 8, rebuild the admin dashboard in Filament, move the client web app to
Livewire + Blade, merge the `Accounting` module into `Finance` (the existing
double-entry ledger), and a full sync-engine rebuild (uuid identity, revision counter,
command queue) — none of which are executed yet.

## Sprint 3 — Inventory Management

The complete Inventory Management module: product catalog, taxonomy, multi-warehouse
stock tracking, batch/serial/expiry management, stock workflows (adjustments, transfers,
physical counts), bulk import/export, and an inventory dashboard. This is the foundation
the upcoming Sales, Purchasing, Accounting and Reporting modules will build on.

### Added

**Database (30 tables)** — `suppliers` (minimal Purchasing module entity), taxonomy
(`categories` with self-referencing subcategories, `brands`, `units` with base-unit
conversions, `collections`, `tags`, `attributes`/`attribute_values`), the core
`products`/`product_variants` with attribute linking, media/notes (`product_images`,
`product_documents`, `product_notes`), `product_supplier` (supplier-specific SKU/cost),
`warehouse_locations` (optional aisle/shelf granularity), the live `inventories` stock
table, lot tracking (`product_batches`, `product_serials`), the **immutable `stock_movements`
ledger**, document-style workflows (`stock_adjustments`/items, `stock_transfers`/items),
`stock_reservations`, `inventory_counts`/items, and `inventory_import_logs`.

**The `stock_movements` ledger is the architectural centerpiece of this sprint.** Every
stock-quantity change anywhere in the platform — regardless of which future module
triggers it — flows through `StockMovementService::record()`, which atomically (DB
transaction + row lock) updates the live `inventories` row and appends an immutable
ledger entry with `quantity_before`/`quantity_after` snapshots. `StockMovement::delete()`
is overridden to throw `LogicException` — immutability is enforced, not just documented.
A polymorphic `reference` (`reference_type`/`reference_id`) lets future Sale/PurchaseOrder
models attach to a movement without any change to Inventory code.

**Negative stock control** uses the existing `businesses.settings` JSON column
(`allow_negative_stock`) from Sprint 1 — no schema change needed. **Costing** is
weighted-average by default (`inventories.average_cost`, recalculated on every inbound
movement); batch-tracked products can support FIFO later since each `product_batches`
row already carries its own `cost_price` and `quantity`.

**Services**: `ProductService` (create/update/duplicate/archive, auto SKU generation,
variant sync), `StockMovementService` (the ledger), `StockAdjustmentService`,
`StockTransferService`, `InventoryCountService` (all three are draft/pending →
completed workflows that generate ledger entries only on completion — never on
creation), `InventoryImportService` (queued CSV import, per-row error isolation so one
bad row never aborts the whole file), `InventoryExportService` (streamed CSV via
`cursor()`, doesn't load the whole catalog into memory), `InventoryDashboardService`.

**Domain events** (`LowStockDetected`, `StockTransferCompleted`, `BulkImportCompleted`)
with email listeners — SMS/WhatsApp/Push are Sprint 9's job; the events already exist so
those channels plug in later without touching Inventory code.

**40+ new permissions** (`products.*`, `categories.*`, `brands.*`, `units.*`, `tags.*`,
`collections.*`, `attributes.*`, `suppliers.*`, `stock_adjustments.*`,
`stock_transfers.*`, `inventory_counts.*`, `inventory.view`) wired into the Manager,
Inventory Officer, Cashier and Accountant default role grants. The Sprint 2 owner-role
auto-sync mechanism means every existing business's Owner role picked these up
automatically the moment `PermissionSeeder` ran again — no manual backfill needed.

**Frontend**: a dedicated `InventoryLayout` with secondary tab navigation; full Product
CRUD (search/filter/paginate, variants, CSV import/export); taxonomy management pages
(Categories, Brands, Units, Tags, Collections, Attributes, Suppliers); Stock Adjustments,
Stock Transfers and Inventory Counts workflow pages; an Inventory Dashboard.

### Architecture decisions worth knowing about

- **Brand and Manufacturer were merged into one `brands` table** rather than duplicated —
  for the target SMB segments they're almost always the same entity.
- **"Product Types" is a fixed enum** (`simple`/`variable`/`service`) on `products`, not a
  manageable lookup table — it's a small fixed set, not user-editable data like categories.
- **Variant attributes are a hybrid**: normalized `attributes`/`attribute_values` tables
  drive management and filtering, plus a denormalized `attributes` JSON snapshot on
  `product_variants` for fast reads without a join.
- **No per-product discount field** — discounts are a Sales/POS concern (applied at sale
  time), not something Inventory should own statically.
- **"Top Selling Products" and "Most Viewed Products" dashboard widgets were deliberately
  left out** — they depend on Sales (doesn't exist until Sprint 4) and page-view analytics
  (doesn't exist anywhere in the platform). Faking them would violate the "no
  placeholders" rule. The dashboard ships only what's genuinely derivable from Inventory
  data: total products, low/out-of-stock counts, expiring/expired batch counts, inventory
  value, today's stock movements, recent products, and a transparent (not black-box)
  health score.
- **Offline support is explicitly out of scope here** — it's Sprint 10's job. What this
  sprint does instead: design `stock_movements` to be sync-friendly (immutable,
  append-only, UUID keys generated client-side-compatible) so the future offline engine
  has something solid to replay against.
- **Two real bugs were caught during verification, not assumed away**: (1) self-referencing
  foreign keys (`categories.parent_id`, `units.base_unit_id`, `warehouse_locations.parent_location_id`)
  failed when declared inline in `Schema::create()` on Postgres — fixed via a follow-up
  `Schema::table()` call. (2) A naive `unique(warehouse_id, product_id, product_variant_id)`
  on `inventories` would not have caught duplicate stock rows for simple (non-variant)
  products, since Postgres treats `NULL` as distinct — fixed with two partial unique
  indexes instead.

### Testing

72 tests total (23 new): `StockMovementServiceTest` (weighted-average costing,
overselling blocked by default, negative-stock opt-in, low-stock event dispatch, the
immutability guard), `ProductManagementTest` (CRUD, duplicate-SKU validation, tenant
isolation, permission checks), `StockWorkflowTest` (full adjustment/transfer/count
lifecycles verified against real HTTP routes, not just the service layer), and
`CategoryManagementTest` (self-referencing parent/child, delete guards). A new
`Tests\Concerns\CreatesBusinesses` trait replaces five separate copy-pasted
`createOwnerWithBusiness()` helpers across Sprint 1/2 test files going forward.

### Known follow-ups

- `WarehouseLocation` model/migration exist but have no dedicated UI yet — most
  businesses won't need aisle/shelf-level precision; it's there for hardware/wholesale
  operations that do, deferred until a business actually asks for it.
- `StockReservation` model exists (for future Sales order holds) but has no controller
  yet — nothing in Inventory itself needs to reserve stock; Sales (Sprint 4) will.
- No standalone `/api/v1` REST API was built for this module, consistent with Sprints 1-2 —
  the React frontend uses Inertia, not a separate JSON API. Sanctum is already installed
  and ready for when a real API consumer (mobile app, third-party integration) exists.

## Unreleased

### Changed

**Locale: Kenya (KES) → Tanzania (TZS)**
- Default `business.country`/`currency`/`timezone` changed from `KE`/`KES`/`Africa/Nairobi` to `TZ`/`TZS`/`Africa/Dar_es_Salaam` (registration form, business defaults). Businesses can still register with any country/currency — these are just the form's pre-filled defaults.
- Subscription plan prices rescaled from KES to realistic TZS amounts (~23x): Starter 35,000/90,000/320,000, Growth 80,000/220,000/760,000, Enterprise 170,000/470,000/1,650,000 (monthly/quarterly/yearly). These were KES-denominated numbers that would have been nonsensically low if just relabeled.
- Added `resources/js/lib/currency.ts` — a shared `formatCurrency()` helper — since the same thousands-separator formatting logic was previously duplicated across the landing page, registration plan picker, and subscription view. All three now import it instead of each defining their own.
- Landing page hero mockup's illustrative sample figures rescaled to TZS magnitude for consistency (not real data either way — purely a stylized preview).

## Sprint 2 — Business Configuration (Branches, Warehouses, Employees)

### Added

**Branches & Warehouses**
- Every business is provisioned with a "Main Branch" and "Main Warehouse" automatically at registration time, in the same atomic transaction as the rest of the registration flow — a business is never left without a location to attach stock to once Inventory ships.
- Full Branch CRUD, gated by the `branches.*` permission set. The main branch can never be deleted; a branch with warehouses or employees still attached cannot be deleted until those are moved or removed first (soft deletes don't cascade, so this guard exists specifically to prevent orphaned warehouse/employee records).
- Full Warehouse CRUD, gated by `warehouses.*`. Each warehouse belongs to exactly one branch (validated server-side to be in the same business — a warehouse cannot be attached to another tenant's branch). The default warehouse for a branch cannot be deleted directly.

**Employees**
- Owners/Managers can invite employees by email, assigning a role and (optionally) a branch. The employee record is created immediately with `status: invited` and an unusable random password — **a real password is never emailed**. A 7-day signed, temporary URL (`employee-invitations.accept`) is sent via `EmployeeInvitedNotification`; visiting it lets the invitee set their own password and activates the account.
- The business owner's role/status can't be changed through the employee-management screen — ownership transfer is a separate, deliberate action, not an accidental side effect of an employee edit form.
- `users.branch_id` added (nullable) so employees can be scoped to the branch they work at; the owner oversees the whole business and is not pinned to one branch by default.

**Permissions**
- Added `branches.*` and `warehouses.*` permissions. Manager and Inventory Officer default roles were granted `*.view` on both by default.
- **Owner-role auto-sync:** the Business Owner role is "full access" by definition, not a per-business customization. `PermissionSeeder` now re-syncs every existing business's Owner role with *all* permissions every time it runs, so a business doesn't lose access to a new module's permissions just because it registered before that module shipped. Other system roles (Manager, Cashier, ...) are deliberately left alone, since their permissions may already have been customized by the business owner.

### Testing

12 new tests covering: main branch/warehouse auto-provisioning, branch CRUD and deletion guards (main branch, non-empty branch), warehouse CRUD and deletion guards (default warehouse), cross-tenant branch validation on warehouse creation, employee invitation + notification dispatch, invitation acceptance (password set, status transition, auto-login), and the owner-role-immutable-via-employee-edit guard. 49 tests total, all green.

### Known follow-ups

- No "resend invitation" or "revoke invitation" action yet — if an invite expires or is lost, the owner currently has to delete and re-invite.
- Branch/warehouse reassignment when deleting (e.g. "move these warehouses to branch X before deleting") is manual; no bulk-move action yet.

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
