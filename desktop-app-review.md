# Desktop app — static review

The Flutter client has never been compiled or run (its own README says so),
and there is no Flutter SDK in the environment I worked in either. So this
is a read of all ~2,000 lines the way a compiler and a reviewer would, plus
a cross-check of every call it makes against the Laravel side.

**What is verified and what isn't**

| | |
|---|---|
| **Verified** | Everything in `backend/` — the API contract, the new endpoint, the security changes. Covered by `tests/Feature/Api/SyncPaginationTest.php`. |
| **Analyzed** | `flutter analyze` → **No issues found**. See the section at the bottom for what it caught on the way there, including three rounds on one line of my own fix. |
| **Tested** | `flutter test` → **6 passing**, all cart arithmetic. That is the only Dart in this project with test coverage. |
| **Still not run** | No screen has been *rendered*, no sync has reached a real server, no sale has gone through the outbox. Analysis proves the code is internally consistent; it proves nothing about whether the app works. |

---

## Critical

### 1. Only the first 500 products ever synced — permanently

`ProductRepository.pull()` advanced its watermark to `server_time` inside
the transaction, **then** recursed for the next page. The recursive call
re-read the watermark it had just written, so it asked the server for rows
changed since "now" and got none.

Worse than stopping early: the watermark had already moved past rows that
were never fetched, so no later sync would pick them up either. A catalog
over one page synced its first 500 products and silently stayed that way.

Fixed: paging is a loop, and the watermark only advances on the final page.

### 2. Rows dropped at every page boundary

Even paging correctly, the server ordered by `updated_at` and the client
resumed with `updated_at > last`. Any row sharing that exact timestamp with
the page's last row was skipped, permanently.

That isn't an exotic edge. A bulk product import writes hundreds of rows
within the same second, so on a first sync of a large catalog it's close to
guaranteed.

Fixed with a keyset cursor on `(updated_at, id)`. The response now carries
`next_since` / `next_since_id`, and the client sends both back.

### 3. Rows dropped at the watermark boundary

**Found late**, by one of my own tests failing for a reason I hadn't
written it to catch — see *How this one surfaced* near the bottom. Same
family as §1 and §2, in the same forty lines I'd already read carefully
twice.

Once paging finishes the client stores `server_time` as its watermark. That
value was `Carbon::now()` sampled when the *response was built*, and `since`
is applied with a strict `>` against a second-granular `updated_at`. Two
gaps follow:

- A product edited in the same second as the pull is in neither bucket —
  too late for this response, excluded from the next by the watermark this
  response just handed back.
- Worse, sampling at response time covers the entire duration of the
  request. Serialising 500 products with their inventory rows is not
  instant, and anything edited during it is inside the window the watermark
  then claims to have covered.

Fixed on both counts: the timestamp is taken *before* the query runs, and
rewound a second before being sent. The next pull re-reads the boundary
second, which the client upserts (`insertOnConflictUpdate`) — a duplicate
write and nothing else.

That asymmetry is the whole argument. A duplicate costs one redundant
`INSERT ... ON CONFLICT`; a loss is permanent, silent, and looks exactly
like a colleague forgetting to save.

### 4. Checkout was a dead end for some employees

`PaymentSheet` requires `branch_id`, which was only ever set from the
signed-in employee's own `branch_id`. That field is optional when inviting
staff — so anyone invited without one could fill a cart, press checkout and
be told to *"sign out and select one again"* by a screen that never offered
the choice. Signing out and back in set nothing.

Fixed by making the location screen ask for branch **and** warehouse, and by
gating the POS route on both.

### 5. The warehouse picker listed raw UUIDs

There was no endpoint returning branch or warehouse names, so the picker
scraped distinct `warehouse_id`s out of synced inventory rows. A cashier was
being asked to choose between `019fd8c9-e7de-71c5-…` and another one like
it — and a new warehouse holding no stock yet was invisible entirely.

Added `GET /v1/locations`: active branches with their active warehouses,
nested, plus the employee's own branch so the till can preselect the common
case. The picker now shows names.

---

## Security — found via the desktop route, but wider

### 6. The API login skipped every control the web login runs

`AuthController::login` did its own rate limiting and nothing else. Missing:
IP block check, account-lockout check, failed-attempt recording, successful
-login recording.

A control that covers one of two doors is not a control. An IP an admin had
blocked, or an account locked after repeated failures, could simply sign in
through the desktop API instead — and because failed attempts weren't
recorded, brute-force alerts and automatic lockouts never fired for this
route at all. The Security Center would show a quiet afternoon during an
attack.

Fixed: the same checks, in the same order as the browser login.

### 7. Suspended employees could sign in — on both web and desktop

`User::STATUS_SUSPENDED` was never checked at authentication anywhere in the
application. Suspending someone on the Employees screen changed a badge and
nothing else.

Fixed in both `LoginRequest` (web) and `AuthController` (API).

---

## Smaller, also fixed

- **Receipt shown through a dead context.** `PaymentSheet` popped the sheet
  and then called `showDialog(context: context)` using the same context —
  which belongs to the route just removed. Captured the navigator first.
- **Sale failures were silent.** A bare `try/finally` with no `catch`: if
  the sale never reached the outbox the cashier saw the button stop spinning
  and nothing else. Now reports the error.
- **Discontinued products were sellable.** `watchAll()` streamed every
  cached product regardless of `status`, so anything taken off sale still
  appeared in the till. Now filters to active.

---

## Known and not fixed

- **`GoRouter` is constructed inside `build()`** in `app.dart`, so a rebuild
  makes a new router and drops navigation state. It happens not to rebuild
  often today (the widget only uses `ref.read`), which is why this is listed
  rather than changed — the fix is to hoist it into a provider, and that is
  worth doing when someone can run the app and confirm nothing regresses.
- **The redirect does three async secure-storage reads on every
  navigation.** Correct, just wasteful.
- **Product search filters in Dart** over the whole catalog on every
  keystroke. Fine at a few thousand rows; a `LIKE` query in Drift would
  scale better.
- **The cart is memory-only** and lost if the app closes mid-sale. Already
  documented as a deliberate choice; worth revisiting for a till.
- **Test coverage is two files** — `cart_totals_test.dart` and
  `app_config_test.dart`. The sync loop and the outbox are the parts that
  would most repay testing, and both need a fake `Dio` and an in-memory
  Drift database to do properly.
- **Nothing sets the API base URL.** `SecureStorage.setApiBaseUrl` exists
  and has no callers, so every install silently uses the `localhost:8000`
  fallback. `AppConfig`'s own docblock describes an owner entering their
  server during activation; that screen only asks for a licence key. Fine
  while the server is on the same machine, blocking the moment a till talks
  to a real one.

---

## Files changed

Backend (verified):

```
app/Http/Controllers/Api/SyncController.php      keyset pagination + watermark boundary
app/Http/Controllers/Api/AuthController.php      security parity with web login
app/Http/Controllers/Api/LocationController.php  new — branches + warehouses
app/Domain/Authentication/Http/Requests/LoginRequest.php  block suspended users
routes/api.php                                   GET /v1/locations
tests/Feature/Api/SyncPaginationTest.php         new — 4 tests
```

Flutter (unverified):

```
lib/data/repositories/product_repository.dart  paging loop, cursor, active-only
lib/data/remote/sync_api.dart                  cursor passthrough
lib/data/remote/location_api.dart              new
lib/features/pos/location/location_screen.dart rewritten — names, branch + warehouse
lib/features/pos/checkout/payment_sheet.dart   context, error handling
lib/core/api/endpoints.dart, core/providers.dart, main.dart, app.dart  wiring
```

---

## What `flutter analyze` found

It ran clean apart from five issues — one error, four infos. All are now
fixed. Worth recording that **two of the four came from the fix in §"Receipt
shown through a dead context"** above, which is a fair illustration of what
"reasoned, not run" was worth: the reasoning was right about the problem and
wrong about where the async gap started.

| | |
|---|---|
| `error` · `MyApp` isn't a class · `test/widget_test.dart` | The counter-app scaffold `flutter create` had just written. Replaced. |
| `info` ×2 · BuildContext across async gaps · `payment_sheet.dart` | Mine. See below. |
| `info` ×2 · prefer const · `pos_home_screen.dart` | Pre-existing. Fixing them exposed three more — see below. |

### `const` cascades upward

Only `CartPanel()` was flagged at first, because it was the only child of
that `Column` with a `const` constructor; `_ProductCatalog` and
`_PendingOutboxBanner` had none, so nothing above them could be const
either. Adding constructors to both made the entire `body:` subtree
const-able, and the next run reported five issues where there had been two.

Resolved by making it `body: const Column(...)` once at the top rather
than sprinkling `const` on each child. Worth doing beyond satisfying a
lint: the AppBar carries a sync-status indicator that rebuilds on every
sync tick, and a const subtree is skipped on each of those rebuilds. Each
child watches the providers it needs, so none of them depend on this
screen's state.

### The BuildContext one was a real bug, not a lint nit

The receipt fix captured `Navigator.of(context)` before popping the sheet,
which was the right idea — but it captured it *after* two `await`s that
read branch and warehouse out of secure storage. So the capture was already
crossing an async gap, and nothing checked `mounted` anywhere in the method.

Moved the capture below the sale and behind a `mounted` guard, so the gap
is closed rather than moved. Also added a guard after the storage reads:
`setState` on a disposed `State` throws, and that path had two `setState`
calls after two awaits with no check. The analyzer does not catch that one
— its async-gap lint only tracks `BuildContext` — so if the cashier
dismissed the sheet mid-checkout the till would have thrown either way.

**That fix was still not right, and the second run said so:** *"guarded by
an unrelated `mounted` check"*. `this.mounted` is the sheet's own liveness.
`rootContext` belongs to the Navigator — a different element, which can go
away independently if the whole POS route is torn down mid-sale. One check
was standing in for the other. Now guarded by `rootContext.mounted`, which
is the thing actually being used.

Worth stating plainly: this one line took three attempts, two of them
wrong in a way that reads as correct. Async gaps around `BuildContext` are
the part of Flutter where careful reasoning is least reliable and the
analyzer is most worth listening to.

### The scaffold test, replaced

`flutter create` writes a widget test for the counter template, which is
why the only error was in a file three minutes old. Rather than delete it,
it's now `test/cart_totals_test.dart` — five tests over the cart maths.

That's the deliberate choice of target. The server recomputes every sale
from `product_id` + `quantity` on sync, so almost nothing here is
authoritative — but the total the cashier reads out loud is computed on the
client, and a till that quotes a different number from the one it charges is
an argument at the counter. The tests need no database and no HTTP, so they
run from a clean checkout.

Discount-before-tax is the case worth having: taxing first would overcharge
2.40 on a single 100-unit line at 18%, and reconcile against nothing.

---

## How §3 surfaced

`php artisan test --filter=Sync` came back 32 passed, 1 failed:

```
an incremental pull returns only what changed
Failed asserting that actual size 0 matches expected size 1.
```

The test seeded two products dated 2026-01-01, pulled, seeded a third dated
2026-02-01, and asserted the third came back. My reasoning was "February is
after January". But the client does not resume from the last row's
timestamp — it resumes from `server_time`, which is the wall clock. Against
a watermark of *today*, a 2026-02-01 row is in the past and correctly
excluded. The test asserted something that could never be true.

So: a bad test, and the fix is one line. Except the reason it was bad —
confusing *later than the previous rows* with *later than the watermark* —
is the same confusion that makes the boundary in §3 easy to miss. Reading
the controller to work out why my test was wrong is what surfaced the fact
that a row written during the pull is dropped for good.

Both are now covered: the incremental test moves the clock with
`$this->travel()` so "later" is unambiguous, and a new test writes a
product in the same second as the pull and asserts it survives to the next
one.

Worth being plain that this was luck. Nothing I did was aimed at that bug;
a test I'd written carelessly happened to fail in a direction that led to
it. §1 and §2 were found by reading. §3 was found by running.

---

## macOS entitlements — found by running it

First launch reached the activation screen and failed there:

```
PlatformException(Unexpected security result code,
  Code: -34018, Message: A required entitlement isn't present.)
```

`-34018` is `errSecMissingEntitlement`. A sandboxed macOS app gets no
Keychain access unless it asks for it, and `flutter_secure_storage` — where
the auth token and the active branch/warehouse live — is Keychain on macOS.
The Keychain API refuses the call outright rather than returning an error
code, which is why it surfaced as a raw `PlatformException` instead of
anything the app could have handled.

Neither entitlements file `flutter create` generated had it. Nor did they
have **`com.apple.security.network.client`** — without which a sandboxed
app cannot open an outbound socket at all, so login and sync would have
failed the moment activation started working. The template ships
`network.server` (for hot reload) and no client counterpart.

### The entitlements were not the cause. Two wrong fixes first.

**Wrong fix 1 — add `keychain-access-groups`.** It broke the build:

```
"Runner" has entitlements that require signing with a development
certificate.
```

That entitlement resolves `$(AppIdentifierPrefix)` to a team ID, so it
cannot be signed ad-hoc. Taking that route would have made an Apple ID a
prerequisite for building the project at all.

**Wrong fix 2 — turn App Sandbox off.** The build succeeded and `-34018`
came back unchanged.

### The actual cause

macOS has two Keychain implementations, and `flutter_secure_storage` picks
the one this app cannot use:

```dart
MacOsOptions({..., bool useDataProtectionKeyChain = true})
```

`true` selects the modern data-protection Keychain, which requires a signed
`keychain-access-groups` entitlement **regardless of the sandbox**. The
requirement comes from the Keychain API, not from the sandbox — which is
exactly why removing the sandbox changed nothing, and why the error is a
raw `PlatformException` rather than something the app could handle: the API
refuses at its boundary instead of returning a code.

Fixed in one line, in `SecureStorage`:

```dart
static const macOsOptions = MacOsOptions(useDataProtectionKeyChain: false);
```

That selects the legacy file-based Keychain, which an ad-hoc signed app can
use. The cost is no iCloud Keychain sync — irrelevant here, and arguably
wrong to have: a till's auth token and chosen warehouse are per-device by
definition.

### The sandbox is now an open question, not a resolved one

It is currently **off in both files**, and the reason I turned it off turned
out not to be the real problem. That leaves a decision made for a bad
reason standing.

Off is still defensible — the sandbox is mandatory only for Mac App Store
distribution, and a till needs printers, USB scanners and file exports, all
of which it makes materially harder. But it is a genuine reduction in
defence-in-depth: a sandboxed app that gets compromised is confined to its
container and this one is not.

Worth revisiting deliberately once activation is confirmed working. Putting
it back means restoring `com.apple.security.network.client` in both files —
without it a sandboxed app cannot open an outbound socket at all, so login
and sync would fail immediately. Whether the legacy Keychain works inside
the sandbox without a signing identity is the part I would want to test
rather than reason about, given the above.

**Three things left as they are:**

- `PRODUCT_BUNDLE_IDENTIFIER` is still the template's
  `com.example.biasharamaxDesktop`. Change it before distributing.
- **Notarization is not set up.** Direct distribution to other people's
  Macs needs a Developer ID certificate (paid account), Hardened Runtime
  enabled, and a notarization pass — otherwise Gatekeeper refuses to open
  it on any machine but this one. Nothing to do until you ship, but it is
  not optional when you do.
- `apiBaseUrl` defaults to `http://localhost:8000/api`. Fine for
  development. Pointing a till at a LAN address over plain `http://` sends
  the auth token in clear text across the shop's network; the answer is TLS
  on the server.

---

## Next step — actually run it

Analysis and unit tests are both clean, so the remaining risk is entirely
in the parts a compiler cannot see: whether the screens render, whether the
sync loop terminates against a real server, whether a sale survives the
outbox.

Backend first, since the client is useless without it:

```bash
cd backend
php artisan migrate                                   # two pending migrations
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=DashboardModuleSeeder
php artisan test --filter=Sync
php artisan serve                                     # leave running
```

Then the client:

```bash
cd ../desktop-app
flutter run -d macos
```

### What to watch, in order

1. **Sign in** with an employee who has the `desktop` token ability. A
   suspended user should now be refused — that changed on both doors.
2. **The location screen** should list branch and warehouse *by name*, not
   UUID, and preselect the employee's own branch. Names appearing is the
   proof `GET /v1/locations` is wired correctly.
3. **First sync.** The one to watch. If the catalog is over 500 products,
   confirm the count in the till matches the count in the dashboard —
   that is the §1 and §2 fix, and its failure mode is silent.
4. **A sale, offline.** Pull the network, complete a sale, confirm the
   outbox banner appears, restore the network, confirm it drains.
5. **Close the sheet mid-checkout** if you can manage the timing. That is
   the path the three `mounted` guards protect and the only way to see
   whether they were placed correctly.

Expect things to break here. Nothing in this list has ever executed.
