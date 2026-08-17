# BiasharaMax

BiasharaMax is a modular Business Operating System for retail, hospitality, pharmacy,
wholesale and service businesses in Zanzibar and Tanzania: POS, inventory, purchasing,
customers/suppliers, accounting, reporting, and an AI assistant — usable all day with
no internet connection.

This is a monorepo with three deployable surfaces, one source of truth per concern:

```
BOS/
├── backend/          One Laravel app: REST API (Flutter desktop) + Web app (browser)
│                      + Admin dashboard (platform operators). See backend/README.md.
├── desktop-app/       Flutter Desktop client (Windows/macOS/Linux), offline-first,
│                      syncs to backend/ whenever the machine has connectivity.
│                      See desktop-app/README.md.
├── shared/            Single source of truth consumed by both apps: OpenAPI contract,
│                      database schema/ERD, translation strings (en + sw).
├── docker/            Local/prod infrastructure: nginx, php, mysql, redis, docker-compose.
├── scripts/           install / build-desktop / backup-db / restore-db / deploy / seed-demo.
├── sql/               Raw SQL exports (schema + seed data) for environments without artisan.
├── docs/              Architecture, API, database, sync engine, install & deploy docs, ADRs.
├── backups/           Local DB backups (gitignored).
└── licences/          Issued licence records (gitignored).
```

## Why one Laravel codebase for three surfaces

`backend/` serves the API, the web app and the admin dashboard from one set of models,
services and migrations — see `backend/README.md` for the module layout and the
`docs/ADR/` decisions on how each surface is separated (routing, guards, middleware)
without duplicating business logic.

## Where to start

- Setting up locally: `backend/README.md` (Laravel/API/web) and `desktop-app/README.md`
  (Flutter client).
- Product/architecture decisions: `docs/ADR/` — start with `0001-consolidation.md`.
- Sprint-by-sprint history of what shipped and why: `CHANGELOG.md`.
