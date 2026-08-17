# BiasharaMax Desktop (Flutter Desktop Client)

Offline-first native client for Windows/macOS/Linux, installed on a business's
own PC/POS terminal. Talks to the same Laravel backend as the web app, via
the `v1/*` API surface in `routes/api.php` — see
`../docs/architecture/flutter-desktop-client.md` for the full design.

## What's here vs. what isn't

This is now a working first-cut POS, not just plumbing: license activation →
employee login → warehouse selection → a two-pane POS screen (searchable
product catalog + live cart) → cash checkout → an on-screen receipt, all the
way down through `SaleRepository`'s offline outbox and `SyncManager`. See
`lib/features/pos/` — `cart/` (cart state + panel), `location/` (warehouse
picker), `checkout/` (payment sheet), `receipt/` (post-sale summary).

This was written without a Flutter SDK available in the environment it was
built in, so it has **not been run** — `flutter pub get` / `flutter analyze`
/ a real build have not been executed against it. Treat it as a structurally
complete first draft to build and fix up locally, not as verified-working code.

## First-time setup

```bash
# From this directory:
flutter create --platforms=windows,macos,linux .   # generates the native runner shells this scaffold doesn't include
flutter pub get
dart run build_runner build --delete-conflicting-outputs   # generates data/local/database.g.dart (Drift codegen)
flutter run -d windows   # or -d macos / -d linux
```

`flutter create .` on a directory that already has a `pubspec.yaml` and `lib/`
fills in the missing platform folders without touching existing files — that's
why it's safe to run after these files already exist, not just before.

## Building the Windows `.exe`

Flutter's Windows target compiles native runner code via MSVC — there is no
Linux-to-Windows cross-compile path, so this can only be built on an actual
Windows machine (or a Windows CI runner). Two ways to get one:

**On a Windows PC**, with [Flutter installed](https://docs.flutter.dev/get-started/install/windows)
and Visual Studio's "Desktop development with C++" workload:

```powershell
cd desktop-app
flutter create --platforms=windows .
flutter pub get
dart run build_runner build --delete-conflicting-outputs
flutter build windows --release
```

The result isn't a single portable file — it's
`build\windows\x64\runner\Release\biasharamax_desktop.exe` plus several DLLs
and a `data\` folder that all have to ship together. Zip the whole `Release`
folder, or wrap it with an installer tool (Inno Setup, MSIX) for
distribution — not set up in this pass.

**Via CI, with no Windows machine needed:** `.github/workflows/build-desktop-windows.yml`
builds this exact sequence on a `windows-latest` GitHub Actions runner and
uploads the `Release` folder as a downloadable artifact, on every push to
`main` that touches this folder (or manually via the Actions tab's "Run
workflow" button). Requires this repo to actually be on GitHub — it isn't
yet (`git remote -v` shows nothing configured).

## Pointing at your backend

The API base URL defaults to `http://localhost:8000/api` (see
`lib/core/config/app_config.dart`) until a real value is saved to secure
storage. For a first local test against `php artisan serve`, that default is
already correct. For a real deployment, add a small settings field to the
activation screen that calls `SecureStorage.setApiBaseUrl()` before
activation — not built into this pass since every business's server address
is different (BiasharaMax Cloud subdomain vs. a self-hosted LAN box) and there
was no single sensible default to hard-code.

## Backend endpoints this app calls

| Endpoint | Purpose |
|---|---|
| `POST /api/v1/licenses/activate` | One-time device activation (license key + hardware fingerprint) |
| `POST /api/v1/licenses/validate` | Periodic re-check that the license/device is still good |
| `POST /api/v1/auth/login` | Employee login, returns a Sanctum token scoped to the `desktop` ability |
| `POST /api/v1/auth/logout` | Revokes the current device's token only |
| `GET /api/v1/sync/products` | Pull product catalog + inventory changed since a watermark |
| `POST /api/v1/sync/sales` | Push queued offline sales (idempotent per `idempotency_key`) |

## Known gaps / next steps

- **Cash payments only.** `payments` already accepts a method + amount per
  entry server-side (see `SaleService::create()`'s docblock); card/mobile
  money is a matter of adding more payment rows, not a new mechanism — just
  not built since there's no payment terminal integration in this codebase
  to test it against.
- **No customer / credit sales in the POS UI.** `customer_id` and credit
  terms exist server-side and in the sale payload shape, but there's no
  customer picker yet — customers aren't synced locally either (no
  `/v1/sync/customers` pull endpoint yet, same pattern as products would
  extend to).
- **No barcode-scanner-specific handling.** A USB scanner acting as a
  keyboard will type into the search box and filter correctly (barcode is
  one of the searched fields), but there's no "exact barcode match ->
  auto-add to cart and clear the field" behavior yet.
- **No receipt printing** — `ReceiptDialog` is on-screen only.
- No settings screen for the API base URL (see "Pointing at your backend"
  above) — `SecureStorage.setApiBaseUrl()` is ready, just not wired to a form.
- **No warehouse names.** `LocationScreen` lists raw warehouse UUIDs pulled
  from synced inventory rows since there's no `/v1/warehouses` (or similar)
  endpoint yet to give them human labels.
- The conflict policy for mutable-state resources (editing a product's price
  offline, for instance) hasn't been designed yet — sales sync cleanly today
  because they're append-only, which is why they were built first.
- `OfflineCertificateService`'s RSA-signed offline certificate (for
  activation with zero network access) isn't wired into the Dart client yet
  — `LicenseApi.activate()` currently assumes at least one network round-trip
  is possible during first-run setup.
