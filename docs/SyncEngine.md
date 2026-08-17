# Sync engine

The highest-risk component in the system — see the master implementation spec Section 6
(reproduced/adapted in `docs/ADR/0001-consolidation.md`) for the full design: every
syncable table carries `uuid` (the real cross-device identity, never the server
autoincrement `id`), `device_id`, `sync_status`, `last_synced_at`, and a server-side
monotonic `revision` counter that the client downloads against via `since_revision`
(never wall-clock time — device clocks aren't trustworthy).

## Record classes and conflict policy

| Class | Examples | Policy |
|---|---|---|
| Reference data | products, customers, suppliers, prices, settings | Last-write-wins on `updated_at`; flagged to `conflict` if both sides changed the same field in-window |
| Immutable transactions | sales, invoices, payments, purchase receipts | Append-only; deduped by `uuid`; corrections are new reversal documents |
| Derived state | stock levels, account balances | **Never synced directly** — sync the movements, recompute locally on each side |

## Current state vs. target

Today: a simpler, already-working timestamp-cursor sync for two resources (products
pull, sales push) — see `docs/API.md`'s "Live today" table and `desktop-app/lib/sync/`.
No `uuid` column on synced tables yet, no revision counter, no command queue, no
`sync_conflicts`/`sync_logs` tables.

Target (per the ADR, confirmed for a full rebuild rather than incremental extension):
the complete Section 6 design, including the control-plane command channel (Section
6.5) that lets the admin dashboard reach offline desktops — queued commands delivered
on next sync, never live RPC, since a shop PC is usually behind a router and often
offline.

Rebuilding this touches `desktop-app/lib/data/remote/sync_api.dart` and
`desktop-app/lib/sync/sync_manager.dart`, which are already wired to the current,
simpler API — not just backend routes.
