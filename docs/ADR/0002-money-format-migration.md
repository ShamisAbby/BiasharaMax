# ADR 0002 — Money Format Migration (decimal → integer minor units)

Status: **Approved. Step 1 (add + backfill migrations) and the `Money` value object are
done** — six migrations (`2026_08_03_000001` through `000006`, one per bounded context)
add every `_minor`/`_micros` column from Section 1.1 and backfill from the existing
decimal values; `app/Domain/Shared/ValueObjects/Money.php` (+
`tests/Unit/Shared/MoneyTest.php`) implements the rounding/allocation rules from Section
5. **Payroll (first context) is cut over** — `EmployeeProfile`, `SalaryAllowance`,
`PayrollPeriod`, `Payslip`, `PayslipDeduction` now cast their `_minor` columns as
integers; `PayrollService` computes entirely via `Money`, dual-writing both the decimal
and `_minor` columns on every create/update so nothing else that reads the decimal
columns (Http/Controllers, Requests, the frontend, and Finance's
`JournalPostingService`, which still takes decimal strings) needed to change. Statutory
rate calculations (income tax, NSSF, NHIF) use `Money::multiplyTruncate()`, not
`multiply()`, specifically to preserve the exact truncation (not rounding) behavior
`bcmul($a, $b, 2)` had, so this cutover does not silently change any withholding amount.
`tests/Feature/Payroll/PayrollTest.php` gained a test asserting every `_minor` column
agrees exactly with its legacy decimal sibling.

**CRM balances (second context) is cut over** — `Customer`, `Supplier`,
`CustomerDebtTransaction`, `SupplierDebtTransaction`, `LoyaltyTier`, and the two
already-ported `PurchaseOrder` payment fields (`paid_amount`/`balance_due`; the rest of
`PurchaseOrder` — subtotal, tax, totals, etc. — belongs to the Purchasing context and is
untouched). `SaleService`, `SalePaymentService`, `SaleReturnService`,
`SupplierPaymentService`, and `AutoPostingService::chargeSupplierBill()` now compute
customer/supplier balance changes via `Money` and dual-write both columns explicitly.

While doing this, found that manually dual-writing in each service is not sufficient on
its own: any *other* code path that creates or updates one of these models — an
un-migrated controller, a seeder, a console command, or simply a test fixture written
before this cutover — would only know about the legacy decimal column, leaving the new
`_minor` column at its default of 0 forever. Since `Customer::creditLimitMoney()` and
friends read `_minor` exclusively, that's not a cosmetic gap, it's a silent correctness
bug (e.g. every credit-limit check would see a 0 limit). Fixed at the model layer instead
of chasing every call site: `App\Domain\Shared\Concerns\SyncsMoneyMinorColumns` is a new
trait that hooks a model's `saving` event and derives whichever of the decimal/`_minor`
pair wasn't explicitly set from the one that was (if both were set explicitly, it trusts
the caller and touches neither). Applied to `Customer`, `Supplier`,
`CustomerDebtTransaction`, `SupplierDebtTransaction`, `LoyaltyTier`, and `PurchaseOrder`
(for its two ported fields only). This is now the intended pattern for every future
context's models too, in addition to (not instead of) the services computing via `Money`
directly where precision/rounding actually matters. `LoyaltyTierService::recalculateTier()`
was also switched from a float `lifetimeSpend(): float` calculation to comparing `Money`
minor units — the spend side still reads `Sale`/`SaleReturn`'s decimal columns rather
than their `_minor` siblings, since Sales is a later context and those columns aren't
guaranteed populated for every row yet.

`tests/Feature/Crm/MoneyMinorSyncTest.php` (new) proves the decimal/`_minor` agreement
invariant across all of the above, including two cases that deliberately create a model
with only the legacy decimal field set (mirroring an un-migrated controller) to prove
`SyncsMoneyMinorColumns` derives `_minor` correctly rather than defaulting to 0.

**Purchasing (third context) is cut over** — `PurchaseOrder`'s remaining money fields
(`subtotal`, `discount_amount`, `tax_amount`, `shipping_cost`, `other_charges`,
`total_amount` — `paid_amount`/`balance_due` were already ported in the CRM-context
batch), `PurchaseOrderItem` (`unit_cost`, `discount_amount`, `tax_amount`, `line_total`),
and `SupplierPayment.amount` (which had a `_minor` column since the CRM-context
migration but no model support for it yet) all now cast their `_minor` columns as
integers and use `SyncsMoneyMinorColumns`.

Unlike Payroll/CRM, `PurchaseOrderService`'s own arithmetic (`buildLineItems()`,
`create()`, `update()`) was deliberately **not** rewritten to use `Money` — it already
computes correct `decimal(x,2)` values via `bcmath`, and now that every money column on
`PurchaseOrder`/`PurchaseOrderItem` is listed in `moneyMinorColumns()`,
`SyncsMoneyMinorColumns` derives `_minor` from whatever decimal value the service writes,
automatically, with no risk of a truncation-vs-rounding mismatch (deriving minor units
from an already-2-decimal-place string via `round(decimal * 100)` is exact and lossless,
unlike Payroll's statutory-rate calculations, which compute the amount itself and needed
`multiplyTruncate()` to match legacy behavior). This is a meaningfully smaller, lower-risk
change than Payroll/CRM's service rewrites, and validates that
`SyncsMoneyMinorColumns` is sufficient on its own for contexts where nothing about the
*computation* needs to change — only later contexts where a service does new
Money-specific arithmetic (rounding, allocation) will need the fuller rewrite pattern.

`GoodsReceivedService` (receiving against a PO, writing the resulting stock movement and
Accounting `Expense`) was not touched — it reads `PurchaseOrderItem.unit_cost`'s decimal
column, which remains correct via the dual-write, and the `Expense` it creates belongs to
the Finance context (sixth, not started).

`tests/Feature/Purchasing/MoneyMinorSyncTest.php` (new) proves the decimal/`_minor`
agreement invariant through the real `PurchaseOrderService`/`SupplierPaymentService`
flow, including that `SupplierPayment.amount_minor` — never explicitly set by
`SupplierPaymentService::record()` — is derived correctly.

**Inventory (fourth context) is cut over** — the trickiest one so far, since it's the
only context with two dual-write scales (Section 2). Standard `_minor` (via
`SyncsMoneyMinorColumns`): `Product` (`cost_price`, `purchase_price`, `selling_price`,
`wholesale_price`, `minimum_price`, `last_purchase_price`), `ProductVariant` (`cost_price`,
`selling_price`, `wholesale_price`), `ProductBatch.cost_price`, the `product_supplier`
pivot's `supplier_cost_price` (a new `App\Domain\Shared\Concerns\SyncsMoneyMinorColumns`
usage on an Eloquent `Pivot`, which works identically since `Pivot extends Model`), and
`StockMovement.total_cost` (a real invoice-comparable amount, despite living on the same
table as the micros column below). New `App\Domain\Shared\Concerns\SyncsMoneyMicroColumns`
(same shape as the minor trait, but derives via `round(decimal * 1,000,000)` and doesn't
use the `Money` value object — a micros amount is currency-agnostic costing precision,
never added to or compared against a real `Money`, so reusing `Money` here would
misrepresent what the column is): `StockMovement.unit_cost`, `StockAdjustmentItem.unit_cost`,
`StockTransferItem.unit_cost`, `Inventory.average_cost`. `StockMovement` uses both traits
at once (no method-name collision — each declares its own `boot*`/abstract method).

As with Purchasing, `StockMovementService`'s weighted-average-cost arithmetic was not
rewritten — it already computes correct `decimal(x,4)`/`decimal(x,2)` values via
`bcmath`, so listing every column in `moneyMinorColumns()`/`moneyMicroColumns()` is
sufficient. `ProductService`, `StockAdjustmentService`, and `StockTransferService` needed
no changes either — all their create/update calls already go through instance-level
Eloquent methods.

One real bug found and fixed: `StockMovementService`'s `TYPE_PURCHASE` branch updated
`Product.last_purchase_price` via `Product::query()->where('id', ...)->update([...])` —
a **query-builder mass update**, which bypasses Eloquent model events entirely. Since
`SyncsMoneyMinorColumns` hooks the `saving` event, `last_purchase_price_minor` would never
have been derived through this path and would silently stay stale forever. Fixed by
changing it to fetch the model and call the instance `update()` method instead (`->first()
?->update([...])`), which does fire model events. This is the same class of bug as the
`LoyaltyTierController` gap found in the CRM-context batch — a reminder to specifically
check for query-builder mass updates (`Model::query()->where(...)->update(...)`, or raw
`DB::table(...)->update(...)`) on any model given a `Syncs*Columns` trait, since those
silently skip the sync.

`tests/Feature/Inventory/MoneyMinorSyncTest.php` (new) proves the decimal/`_minor` and
decimal/`_micros` agreement invariants through the real
`ProductService`/`StockMovementService`/`StockAdjustmentService`/`StockTransferService`
flow, including the `last_purchase_price` fix above and a direct pivot-creation case
(there's no application code wiring `supplier_cost_price` into a request yet, so this
proves the trait itself is correct rather than proving a real call site).

**Sales (fifth context) is cut over** — `Sale`, `SaleItem`, `SalePayment`, `SaleReturn`,
`SaleReturnItem` all now cast their `_minor` columns and use `SyncsMoneyMinorColumns`.
Same pattern as Purchasing/Inventory: `SaleService`/`SaleReturnService`/
`SalePaymentService`'s own bcmath arithmetic was left untouched, since it already
computes correct decimal values and every money column is now listed in
`moneyMinorColumns()`. No query-builder mass-update landmines found this time (checked
specifically, given the `last_purchase_price` bug found in the Inventory batch).

`LoyaltyTierService::recalculateTier()` (CRM context) was updated to compare purely in
minor units — `Sale.total_amount_minor`/`SaleReturn.refund_amount_minor` — instead of
bridging through the legacy decimal columns via `Money::fromDecimal()`, which is what it
had to do before this context existed. The `lifetimeSpend(): Money` method was replaced
with `lifetimeSpendMinor(): int`.

`tests/Feature/Sales/MoneyMinorSyncTest.php` (new) proves the decimal/`_minor` agreement
invariant through the real sale/payment/return flow, and specifically proves the
loyalty-tier recalculation change: a sale pushes a customer into a tier, an equal-value
return pulls them back out, both read via the new minor-unit comparison.

**Finance (sixth and final context) is cut over.** Every remaining money-carrying
model gets `SyncsMoneyMinorColumns` and nothing else — no service arithmetic needed to
change, same low-risk pattern as Purchasing/Inventory/Sales: `JournalLine` (`debit`,
`credit`, `foreign_amount`), `BankAccount.opening_balance`, `BankTransaction.amount`,
`BankReconciliation` (`statement_balance`, `book_balance`, `difference` — the first
column in this whole migration allowed to be negative, confirmed `Money`/the trait
handle the sign correctly), `BudgetLine.budgeted_amount`, `TaxTransaction`
(`taxable_amount`, `tax_amount`), `FixedAsset` (`acquisition_cost`, `residual_value`,
nullable `disposal_proceeds`), `DepreciationSchedule` (`depreciation_amount`,
`accumulated_depreciation`, `book_value`), `PaymentTransaction` (`amount`, `tax_amount`,
`discount_amount`, `fee_amount`, `commission_amount`, `refunded_amount`),
`PaymentGateway.fee_fixed`, Accounting's `Expense.amount`/`Income.amount` (merged into
Finance per `docs/ADR/0001-consolidation.md`), and Subscription's platform-billing
tables — `SubscriptionPlan` (`price_monthly`, `price_quarterly`, `price_yearly`,
`price_lifetime`), `Subscription.custom_price` (nullable), `SubscriptionTransaction.amount`
— included here since they have no rollout step of their own and are still money.

Currency resolution needed a case-by-case call for models with no `business()` relation
of their own: `JournalLine` and `TaxTransaction` (plain `business_id` column, no
relation) resolve via a direct `Business::find($this->business_id)`; `BankTransaction`,
`BankReconciliation` resolve via `bankAccount->business`; `BudgetLine` via
`budget->business`; `DepreciationSchedule` via `fixedAsset->business`;
`PaymentTransaction` and `SubscriptionTransaction` carry their own per-row `currency`
column (a historical record of what was actually charged, which must not drift if the
business's currency setting changes later), falling back to the business relation;
`PaymentGateway` and `SubscriptionPlan` are genuinely platform-wide catalog/config rows
with no business concept at all, so both hardcode the platform default (`'TZS'`).

Checked `AutoPostingService` and `ChartOfAccountsService` (this context's ledger-adjacent
services) plus every other Finance/Accounting/Subscription service for the
query-builder-mass-update class of bug found in Inventory's cutover
(`Model::query()->where(...)->update(...)` bypasses Eloquent events, so the trait's
`saving` hook never fires) — none found. Every money-column write in this context goes
through either an instance `update()`/`save()` call or `Model::create()`, both of which
fire model events normally.

The Finance-context cutover is also where the mathematical safety of this whole approach
for the ledger specifically gets proven, not just assumed:
`JournalPostingService::assertBalanced()` already enforces `SUM(debit) == SUM(credit)` in
decimal via `bcmath` *before* any `JournalLine` is persisted, and deriving `_minor` via
`round(decimal * 100)` is an exact, order-preserving transformation applied identically
to both sides of an already-balanced equation — so `SUM(debit_minor) == SUM(credit_minor)`
is guaranteed to hold once `_minor` is derived, not something that could fail
independently. `tests/Feature/Finance/MoneyMinorSyncTest.php` (new) proves this in
practice through the real `JournalPostingService`, using a three-line entry (one debit
split across two credit accounts, not a trivial 1:1 pair) so the invariant proof isn't
trivially true just because there happen to be exactly two rows. The same test file also
proves decimal/`_minor` agreement for every other model above through the real
`BankAccountService`/`BankReconciliationService`/`BudgetService`/`TaxService`/
`FixedAssetService`/`PaymentGatewayService`/`PaymentTransactionService`/`ExpenseService`/
`IncomeService`/`SubscriptionService` flows, including `BankReconciliation.difference`'s
negative-value case and `FixedAsset.disposal_proceeds`'s nullable-until-disposal case.

This closes out all six bounded contexts in Section 4 step 3. Per Section 4 step 5,
dropping the old decimal columns is a separate, later decision requiring its own
sign-off — not automatic just because every context has cut over.

**Verified 2026-08-04**: `php artisan test --filter=Finance` (111 passed, 390
assertions), `--filter=Accounting` (9 passed, 26 assertions), `--filter=Subscription`
(34 passed, 94 assertions), and `--filter=Crm` (28 passed, 96 assertions) all pass
against the real PostgreSQL database — confirming the debit=credit-minor invariant
test, every other Finance-context dual-write, and that `createOwnerWithBusiness()`
(used by nearly every Feature test in the app) still works now that `SubscriptionPlan`
itself carries `SyncsMoneyMinorColumns`. **All six bounded contexts of ADR 0002 are now
cut over: Payroll → CRM → Purchasing → Inventory → Sales → Finance.**

**Verified 2026-08-03**: `php artisan test --filter=MoneyTest` (15 passed, 33
assertions) and `php artisan test --filter=Payroll` (8 passed, 42 assertions, including
the new decimal/`_minor` agreement test) both pass against a real PostgreSQL database,
confirming the six new migrations run cleanly and the truncation-preserving statutory
calculations produce the same figures as before. The CRM-balances batch
(`MoneyMinorSyncTest` plus the existing `SupplierPaymentTest`/`SalesAndPosTest`/
`LoyaltyTest`/`SaleReturnTest`/`CrmTest`/`CustomerFeedbackTest`/`MarketingCampaignTest`
suites) was also verified 2026-08-04: `php artisan test --filter=Crm` (28 passed, 96
assertions), `--filter=Sales` (26 passed, 138 assertions), `--filter=Purchasing` (13
passed, 54 assertions), all against the real PostgreSQL database. The Purchasing-context
batch above was verified the same day: `php artisan test --filter=Purchasing` (15
passed, 68 assertions), also green. The Inventory-context batch above was verified the
same day: `php artisan test --filter=Inventory` (37 passed, 178 assertions) and
`php artisan test --filter=StockMovementServiceTest` (7 passed, 13 assertions,
confirming the `last_purchase_price` fix), both green. The Sales-context batch above was
verified the same day: `php artisan test --filter=Sales` (30 passed, 157 assertions) and
`php artisan test --filter=Crm` (28 passed, 96 assertions, confirming
`LoyaltyTierService`'s change), both green. This is the highest-risk item in
`docs/ADR/0001-consolidation.md` (non-negotiable constraint
#3: money is integer minor units + explicit currency, never float/decimal). It touches
Sales, Inventory, Purchasing, Finance, Payroll, CRM simultaneously and the Finance
ledger's debit=credit invariant must survive the conversion — this gets a plan and a
dry-run design before any real migration is written, per
`docs/ADR/0001-consolidation.md`'s own risk note.

## 1. Full column inventory

Audited all 150 `decimal(...)` column definitions across `database/migrations/`.
Classified by what the column actually represents — **only money amounts convert**;
quantities, percentages, exchange rates, hours/days, and system metrics stay decimal,
because they aren't currency and constraint #3 doesn't apply to them.

### 1.1 Converts to integer minor units (~60 columns)

| Table | Columns |
|---|---|
| `subscription_plans` | `price_monthly`, `price_quarterly`, `price_yearly`, `price_lifetime` |
| `subscriptions` | `custom_price` |
| `subscription_transactions` | `amount` |
| `products` | `cost_price`, `purchase_price`, `selling_price`, `wholesale_price`, `minimum_price`, `last_purchase_price` |
| `product_variants` | `cost_price`, `selling_price`, `wholesale_price` |
| `product_supplier` | `supplier_cost_price` |
| `product_batches` | `cost_price` |
| `stock_movements` | `unit_cost`, `total_cost` |
| `stock_adjustment_items` | `unit_cost` |
| `stock_transfer_items` | `unit_cost` |
| `inventories` | `average_cost` |
| `payment_gateways` | `fee_fixed` |
| `payment_transactions` | `amount`, `tax_amount`, `discount_amount`, `fee_amount`, `commission_amount`, `refunded_amount` |
| `customers` | `credit_limit`, `current_balance` |
| `sales` | `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `paid_amount`, `balance_due` |
| `sale_items` | `unit_price`, `unit_cost`, `discount_amount`, `tax_amount`, `line_total` |
| `sale_payments` | `amount` |
| `customer_debt_transactions` | `amount`, `balance_before`, `balance_after` |
| `expenses`, `incomes` | `amount` |
| `purchase_orders` | `subtotal`, `discount_amount`, `tax_amount`, `shipping_cost`, `other_charges`, `total_amount`, `paid_amount`, `balance_due` |
| `purchase_order_items` | `unit_cost`, `discount_amount`, `tax_amount`, `line_total` |
| `sale_returns` | `refund_amount` |
| `sale_return_items` | `unit_price`, `line_refund_amount` |
| `loyalty_tiers` | `minimum_spend` |
| `journal_lines` | `debit`, `credit`, `foreign_amount` |
| `suppliers` | `credit_limit`, `current_balance` |
| `supplier_payments` | `amount` |
| `supplier_debt_transactions` | `amount`, `balance_before`, `balance_after` |
| `bank_accounts` | `opening_balance` |
| `bank_transactions` | `amount` |
| `bank_reconciliations` | `statement_balance`, `book_balance`, `difference` |
| `budget_lines` | `budgeted_amount` |
| `tax_transactions` | `taxable_amount`, `tax_amount` |
| `fixed_assets` | `acquisition_cost`, `residual_value`, `disposal_proceeds` |
| `depreciation_schedules` | `depreciation_amount`, `accumulated_depreciation`, `book_value` |
| `employee_profiles` | `base_salary` |
| `salary_allowances` | `amount` |
| `payroll_periods` | `total_gross`, `total_deductions`, `total_net` |
| `payslips` | `basic_salary`, `total_allowances`, `gross_salary`, `income_tax`, `social_security`, `other_deductions`, `total_deductions`, `net_salary` |
| `payslip_deductions` | `amount` |

### 1.2 Stays decimal — not money (~90 columns)

Quantities (`quantity`, `reserved_quantity`, `minimum_stock`, `maximum_stock`,
`reorder_level`, `quantity_ordered`, `quantity_received`, `quantity_damaged`,
`quantity_rejected`, `quantity_returned`, `expected_quantity`, `counted_quantity`,
`variance` on `inventories`/`product_batches`/`stock_movements`/`stock_adjustment_items`/
`stock_transfer_items`/`stock_reservations`/`inventory_count_items`/
`purchase_order_items`/`goods_received_items`/`sale_return_items`/`products`), physical
attributes (`weight`), percentages/rates (`tax_rate`, `default_tax_rate`,
`fee_percentage`, `discount_percentage`, `tax_rates.rate`, `conversion_factor`),
exchange rates (`exchange_rate_to_base`, `exchange_rate_override`, `exchange_rate` —
these are ratios, not currency amounts, and need their existing high-decimal-place
precision, not a minor-unit integer), system metrics (`cpu_usage`, `memory_usage`,
`disk_usage`, `db_response_time_ms`, `health_score`), and HR time (`regular_hours`,
`overtime_hours`, `break_hours`, `allocated_days`, `used_days`, `pending_days`,
`carried_forward_days`, `available_days`, `days_requested`).

## 2. The precision problem — why "multiply by 100" doesn't work uniformly

Most money columns are `decimal(x, 2)` — multiplying by 100 to get integer cents is
exact and lossless. But `unit_cost` (stock movements, adjustment/transfer items) and
`average_cost` (inventories) are `decimal(14, 4)` — **4 decimal places**, deliberately
higher precision than 2, because weighted-average costing
(`InventoryDashboardService`/`StockMovementService`'s average-cost recalculation on
every inbound movement) accumulates rounding error across many small movements if
rounded to the cent on every write. Multiplying these by 100 and truncating to whole
cents would silently lose exactly the precision they were given the extra 2 decimal
places for.

**Decision: these columns convert to integer *ten-thousandths* of the currency's minor
unit** (i.e. multiply by 1,000,000 for a 2-decimal-minor-unit currency like TZS, since
4 extra decimal places beyond the minor unit = ×10,000, and the minor unit itself is
×100 — combined ×1,000,000), not integer minor units directly. They are *cost-tracking*
figures, not amounts that appear on an invoice or get added into a sale/journal total
directly — `total_cost` (which does need to be a real, invoice-comparable amount) stays
at the standard minor-unit scale. Rename these two columns to make the scale explicit in
the schema rather than leaving it implicit: `unit_cost_micros`, `average_cost_micros`
(a "micro-unit" naming convention, same idea Google/Stripe APIs use for exactly this
problem — sub-minor-unit precision on unit costs that get multiplied by large
quantities).

All other converting columns become plain integer minor units (e.g. TZS cents — TZS
technically has no subunit in circulation, but the system already stores 2 decimal
places for TZS today, and constraint #3 doesn't ask us to change the number of decimal
places actually meaningful to the business, just the storage representation).

## 3. Column-rename convention

Every converted column is renamed with a `_minor` (or `_micros` for the two exceptions
above) suffix rather than keeping the old name with a changed type — e.g.
`sales.total_amount` → `sales.total_amount_minor`. Deliberately **not** a silent
type-only change on the same column name, for two reasons: (1) it makes every
application-layer read/write site that wasn't updated fail loudly (a `decimal` value
landing in an `_minor`-suffixed integer column read by old code expecting the old scale
is obviously wrong, versus a same-named column silently misinterpreting cents as whole
currency units); (2) it's what enables the dual-write rollout in Section 4 — the old and
new columns can coexist during migration.

## 4. Rollout strategy — dual-write, not a single flag-day cutover

Given this touches the Finance ledger (whose debit=credit invariant must never be
observably broken, even mid-migration) and every sale/purchase calculation
(`SaleService`, `PurchaseOrderService`-equivalent, `JournalPostingService`,
`AutoPostingService`), a single big-bang migration that rewrites all ~60 columns and
all call sites in one shot is exactly the kind of change that's very hard to review and
very risky to roll back. Instead:

1. **Add, don't replace.** New migrations add the `_minor`/`_micros` integer columns
   alongside the existing decimal columns (nullable at first). Backfill from the
   existing decimal values in the same migration (`UPDATE table SET x_minor = ROUND(x *
   100)`), so both columns hold equivalent values immediately.
2. **Introduce a `Money` value object / Eloquent cast** (`app/Domain/Shared/ValueObjects/Money.php`
   or similar) that wraps an integer minor-unit amount + currency code, with
   `fromDecimal()`/`toDecimal()` for the transition period and arithmetic methods
   (`add`, `subtract`, `multiply` by a quantity, `allocate` for splitting a total across
   line items without losing a cent to rounding — the classic "fair rounding" problem)
   so services stop hand-rolling `bcadd`/`bcsub` string arithmetic per call site.
3. **Switch models to the new columns one bounded context at a time**, starting with
   the one with the fewest cross-dependencies and ending with Finance (the ledger,
   highest blast radius): Payroll → CRM (customer/supplier balances) → Purchasing →
   Inventory → Sales → Finance. Each step: point the model's `$casts`/accessors at the
   `_minor` column, update the Resource/API response shape, update the
   React/Livewire-in-progress form that displays/edits it, update the service's
   arithmetic to use the `Money` value object, update tests to assert on the integer
   column. Each bounded context is its own reviewable commit/PR, not one giant diff.
4. **Verification query after each context's cutover**: sum-check that
   `SUM(x_minor) / 100 = SUM(x)` (or `/ 1,000,000` for the micros columns) still holds
   across every row, and that Finance's `SUM(debit) = SUM(credit)` invariant holds when
   computed from the new integer columns.
5. **Drop the old decimal columns** only after every context has cut over and the
   verification queries have passed against production data, in a final migration pass.

This is deliberately slower than a single migration, but it means the ledger is never in
a state where its balance invariant is unverifiable, and a problem discovered in
(say) Inventory's cutover doesn't block or entangle with Finance's.

## 5. Rounding rules (ties into Appendix A1's VAT rules already decided)

Per the already-decided VAT rules: tax is computed **per line**, rounded half-up to the
minor unit, then summed — lines must sum to the document total exactly, never
back-filled from a document-level total. The `Money` value object's `allocate()` method
(splitting a total across N parts, e.g. a discount spread across line items) uses the
standard largest-remainder method so the parts always sum to exactly the original total
— no line silently absorbs a rounding difference inconsistently.

## 6. What this ADR does not yet include

- The actual new migration files (Section 4 step 1) — not written yet, pending sign-off
  on the `_minor`/`_micros` naming convention and the per-context rollout order above.
- The `Money` value object implementation.
- Currency.ts (frontend) and the eventual Livewire/Blade views' handling of integer
  minor units for display (divide by 100 for presentation only, never store the
  divided value).
- A concrete list of every Service/Resource/React-or-Livewire call site per bounded
  context — Section 4's per-context rollout will enumerate these as each context is
  tackled, rather than listing all of them speculatively here before the column-rename
  convention is confirmed.

**Recommend confirming the `_minor`/`_micros` naming and the six-context rollout order
before I start writing the actual migrations** — this is the one place in the whole
consolidation where getting the design wrong is expensive to unwind, since it touches
the ledger's core invariant.
