# Deployment

- **Backend:** `scripts/deploy.sh`, run from the repo root on the target server after a
  `git pull` — installs production dependencies, builds assets, migrates, caches
  config/routes/views, restarts queue workers and Horizon.
- **Database backups:** `scripts/backup-db.sh` (add `--schema-only` to also refresh
  `sql/bos.sql`); restore with `scripts/restore-db.sh <dump-file>` (asks for
  confirmation before overwriting).
- **Desktop releases:** built via `scripts/build-desktop.sh` locally, or the
  `.github/workflows/build-desktop-windows.yml` CI workflow for Windows (requires the
  repo to actually be on GitHub with Actions enabled). Distribution to businesses'
  machines — installer packaging, update channel management via the admin dashboard's
  release manager (master spec Phase 8, item 8) — isn't built yet.
- **Production domains:** not yet assigned/configured in this repo (`api.<domain>`,
  `app.<domain>`, `admin.<domain>` per the master spec Section 3) — fill in once
  real domains exist.
