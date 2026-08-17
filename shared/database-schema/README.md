# Database schema

Canonical schema documentation and ERD, generated from `backend/database/migrations/`
(179 migrations as of this writing) rather than hand-maintained separately from them —
migrations remain the actual source of truth; this folder is a readable view onto them.

**Not generated yet.** Planned:

- `erd.md` (or `.dbml`/`.png`) — entity-relationship diagram covering all modules.
- `schema.md` — table-by-table reference: columns, types, indexes, foreign keys.

Two open items from `../../docs/ADR/0001-consolidation.md` that this documentation needs
to reflect once decided/executed:

1. **Money columns** are `decimal(x,y)` throughout today; the target spec's constraint
   #3 (integer minor units, no float/decimal for money) means every money column listed
   here will change type. Document the *before* state now, the *after* state once that
   migration runs.
2. **Sync columns** (Section 6.1 of the master spec: `uuid`, `device_id`, `sync_status`,
   `last_synced_at`, `revision`) don't exist on any table yet — only `Sales`/`Inventory`
   are synced today, via a simpler timestamp-cursor design. Full sync-engine rebuild is
   its own phase; this schema doc should track which tables have the new columns as that
   phase lands table-by-table.
