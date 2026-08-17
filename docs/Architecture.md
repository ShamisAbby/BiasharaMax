# Architecture

High-level system picture. For decisions and their rationale/risks, see `ADR/`.

```
Flutter Desktop (offline-first, SQLite)  ─┐
                                          ├─ REST API ─→  backend/ (one Laravel app)  ─→  MySQL
Web App (browser)                        ─┤                API + Web + Admin
Admin Dashboard (browser)                ─┘
```

- **backend/** — one Laravel codebase, three surfaces separated by route file and
  guard (`routes/api.php`/`sanctum`, `routes/web.php`/`web`, `routes/platform.php`/
  `platform`), never three separate deployables. Module layout: `backend/README.md`.
- **desktop-app/** — Flutter, offline-first, syncs to `backend/` in the background;
  never blocks on network. Current status and known gaps: `desktop-app/README.md`.
- **The two planes** (application plane: the business's own data, reached via desktop
  or web; control plane: the admin dashboard, operated by the platform vendor, never
  tenant-scoped) — see `docs/ADR/0001-consolidation.md` Section 4.1 for the full
  permission-model rationale.

For the sync engine design (the highest-risk component) see `SyncEngine.md`. For the
full endpoint list see `API.md`. For the schema see `shared/database-schema/`.

This file is intentionally a map, not a duplicate of the ADRs — when architecture
changes, update the relevant ADR first, then reflect it here.
