# API

Source of truth for the wire format is `shared/api-contracts/` (OpenAPI, not written
yet) once it exists; until then, `backend/routes/api.php` is authoritative and this page
is a human-readable index of what's actually live today.

## Live today (`backend/routes/api.php`)

| Endpoint | Auth | Purpose |
|---|---|---|
| `POST /api/v1/licenses/activate` | none (throttled) | One-time device activation |
| `POST /api/v1/licenses/validate` | none (throttled) | Periodic license/device re-check |
| `POST /api/v1/auth/login` | none (throttled) | Employee login → Sanctum token, `desktop` ability |
| `POST /api/v1/auth/logout` | `sanctum` | Revokes the calling token only |
| `GET /api/v1/auth/me` | `sanctum` | Current user |
| `GET /api/v1/sync/products` | `sanctum` + `ability:desktop` | Pull products/inventory changed since a `since` timestamp |
| `POST /api/v1/sync/sales` | `sanctum` + `ability:desktop` | Push queued offline sales (idempotent per `idempotency_key`) |

## Planned (per `docs/ADR/0001-consolidation.md`, sync-engine rebuild)

Replaces the timestamp-cursor sync above with uuid-identity + revision-counter sync,
per Section 6 of the master implementation spec:

```
POST   /api/v1/auth/login          → token + device registration
POST   /api/v1/auth/logout
POST   /api/v1/devices/register
POST   /api/v1/sync/upload         → push local changes (batched, idempotent)
GET    /api/v1/sync/download       → pull server changes since revision (cursor-paginated)
POST   /api/v1/sync/resolve        → resolve a flagged conflict
GET    /api/v1/sync/status         → server revision, clock, entity checksums
```

Plus the control-plane command channel (`GET /api/v1/sync/download` returns queued
`commands[]`; `POST /api/v1/devices/commands/{id}/ack`) — see
`docs/ADR/0001-consolidation.md` and the master spec Section 6.5 for the full command
list (`deactivate_device`, `force_logout`, `update_settings`, `force_full_resync`,
`licence_updated`, `require_app_update`, `clear_local_cache`).

Non-negotiable constraint: this doc and `shared/api-contracts/` must be updated in the
same commit as any `routes/api.php` change.
