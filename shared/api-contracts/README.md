# API contracts

Single source of truth for the API surface both `backend/routes/api.php` and
`desktop-app/lib/data/remote/*.dart` must agree on.

**Not extracted yet.** Until the OpenAPI spec below is written, `backend/routes/api.php`
is the source of truth — see `../../docs/API.md` for the current endpoint list and
`../../docs/ADR/0001-consolidation.md` for the sync-engine rebuild this contract needs to
reflect (uuid identity, revision-based cursor, command queue — see Section 6 of the
master implementation spec).

## Plan

- `openapi.yaml` — OpenAPI 3.1 spec covering `auth`, `devices`, `licenses`, `sync`
  (upload/download/resolve/status), and the command-queue endpoints.
- Per Section 6.3/6.5 of the master spec: every write endpoint documented here must be
  updated in the same commit as the corresponding `routes/api.php` change (Non-negotiable
  constraint #6) — this file existing is what makes that rule enforceable.
- Generated client types (TypeScript for any future web consumer, Dart models cross-
  checked against `desktop-app/lib/data/remote/`) land here once the spec exists.
