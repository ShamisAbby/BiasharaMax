# Translations (English + Swahili)

Source-of-truth strings for both `backend/lang/` (Laravel validation/notification
strings, and Blade/Livewire views once the frontend migration lands) and any strings
`desktop-app/lib/` needs for its own UI.

**Not started.** Today `backend/lang/` only has a vendor package's own translations
(`lang/vendor/backup`) — no app-level `en`/`sw` strings exist yet. This is new work
(non-negotiable constraint #10 of the master spec), not a gap in something already
built. Plan:

- `en/` and `sw/` subfolders mirroring Laravel's standard `lang/{locale}/*.php` structure.
- A shared key namespace so the same string keys resolve in both the backend Blade/
  Livewire views and (via a generated JSON/ARB export) the Flutter client, rather than
  maintaining two independent translation sets that can drift.
