# Installation

- **Backend (web/API/admin):** `backend/README.md`, or run `scripts/install.sh`
  (`scripts\install.bat` on Windows) from the repo root.
- **Desktop client:** `desktop-app/README.md`, or run `scripts/build-desktop.sh` to
  build a release binary once dependencies are set up.
- **Docker (optional, local infra):** `docker/docker-compose.yml` — see the comment at
  the top of that file; it targets MySQL per the target architecture, while
  `backend/.env` still points at the native PostgreSQL setup in `backend/README.md`
  until the engine migration phase lands. Don't mix the two until then.
