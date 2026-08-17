# Database

Canonical, generated schema documentation lives in `shared/database-schema/` (not
generated yet — see that folder's README for the plan). This page is the narrative
overview.

- **Engine:** PostgreSQL 16 today; migrating to MySQL 8 per `docs/ADR/0001-consolidation.md`.
  179 migrations as of this writing (`backend/database/migrations/`) — the migration
  plan requires auditing all of them for Postgres-specific syntax before the engine
  switch, since this is a full re-platform, not a config change.
- **Multi-tenancy:** every business-scoped table carries `business_id`; enforced via the
  `BelongsToTenant` Eloquent global scope, not by controller convention.
- **Money:** stored as `decimal(x,y)` throughout today (see the ADR's full column list).
  Target constraint is integer minor units + explicit currency — this is its own
  migration phase given how many modules it touches (Sales, Inventory, Purchasing,
  Finance, Payroll, CRM), not a line item folded into routine schema work.
- **The ledger:** `Finance` module — `accounts`, `journal_entries`, `journal_lines`,
  auto-posting listeners for sale/expense/payment/goods-received events. Debits must
  equal credits per entry; entries are immutable once posted; corrections are reversing
  entries, never edits. `Accounting` (cash-basis expense/income) is being merged into
  `Finance` per the ADR rather than kept as a separate reporting path.
- **The inventory ledger:** `stock_movements` is append-only (`StockMovement::delete()`
  throws `LogicException`); every stock-quantity change flows through
  `StockMovementService::record()`, never a direct mutation of `inventories`.
