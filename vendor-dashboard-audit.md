# Vendor Dashboard — Deep Audit

Scope: the tenant-facing Inertia app (everything under `auth` + `verified` +
`subscription.active` in `routes/web.php` — 304 named routes, 81 controllers,
47 sidebar destinations).

**Status: all findings below are fixed.** Verification commands and results are
at the end.

---

## Correction to the first draft of this audit

The original write-up claimed the Payroll endpoints were **cross-tenant** —
that a user in Business A could act on Business B's records because the routes
are model-bound with no `business_id` filter.

**That was wrong.** `LeaveRequest`, `LeaveType`, `AttendanceRecord`,
`AttendanceCorrection` and `EmployeeProfile` all use the `BelongsToTenant`
trait, whose global scope makes route-model binding 404 on another business's
row. Tenancy was never open.

What *was* real, and is what the fixes below address:

- **No authorization at all** on those endpoints — an employee could approve
  their own leave and their own attendance corrections.
- **`manualRecord`'s `employee_profile_id`** genuinely was unscoped. It arrives
  in the request body, so the global scope never sees it, and `Rule::exists`
  goes through the presence verifier which bypasses global scopes too.

The `sameBusiness()` check in the new policies is kept as defence in depth
(the `platform` guard bypasses the tenant scope deliberately), not as the
primary boundary. The code comments say so.

---

## P1 — Payroll had no authorization  ✅ fixed

Every other module guards writes with a FormRequest whose `authorize()` calls a
policy. Payroll used plain `Illuminate\Http\Request` and checked nothing:
`LeaveService::approveRequest()` only verified `status === pending`, never who
was calling. **Any employee could approve their own leave request or their own
attendance correction** — the request/approve split was decorative.

**Fix.** Four new policies plus a shared ownership helper:

| File | Rule |
|---|---|
| `Payroll/Policies/LeaveRequestPolicy.php` | approve/reject require `leave.approve`; self-approval explicitly barred |
| `Payroll/Policies/AttendanceRecordPolicy.php` | you may operate your *own* timesheet with no permission; anyone else's needs `attendance.manage` |
| `Payroll/Policies/AttendanceCorrectionPolicy.php` | review requires `attendance.approve`; self-review barred |
| `Payroll/Policies/LeaveTypePolicy.php` | configuration — `leave.manage` throughout |
| `Payroll/Support/PayrollOwnership.php` | the two questions all four ask: same tenant, and is this record about me |

Controllers now extend the base `Controller` and call `$this->authorize()`.
`HrDashboardController` (headcount and payroll cost, previously open to
everyone) is gated on `payroll.view`.

`manualRecord` additionally gained a business-scoped `exists` rule and
`Rule::in(AttendanceRecord::statuses())` — it previously accepted
`['required', 'string']` for status, so any value at all could be written into
the column and read back by the payroll calculation.

---

## P1 — Four permission checks that were always `false`  ✅ fixed

```php
$user->can('leave.view')   $user->can('payroll.manage')   $user->can('payroll.approve')
```

Authorization in this app is **`$user->hasPermission('slug')`**. Laravel's
`can()` resolves a bare string against defined Gates, and there are none
(`Gate::before` count: 0; no Payroll policies were registered). An undefined
ability resolves to `null` → `false`.

So `canApprove`, `canViewAll` and `canManage` were false **for everyone,
including the owner**: approve/reject buttons never rendered, and managers only
ever saw their own leave. The two halves compounded — approval was unreachable
through the UI while the endpoint behind it was wide open.

Swapped to `hasPermission()`. Same pass found `Payroll/Leave/Types.tsx`
expecting a `canManage` prop the controller never sent, so its create/edit/delete
controls had never rendered either.

The Attendance page is deliberately *not* gated as a whole — it is where an
employee clocks in. The roster, stats and staff list are scoped instead: you
always see yourself, the team only with `attendance.view`.

---

## P1 — Sidebar ignored permissions  ✅ fixed

All 47 destinations rendered for every employee. A cashier saw Journal Entries,
Payroll and Roles & Permissions, and got a 403 on each.

`NavLeaf`/`NavSection` now carry `permission?: string[]`, populated from what
each route's controller or policy actually checks (48 entries). A group whose
every child is hidden disappears rather than leaving an empty heading. Pinned
favourites are re-checked, so a demoted user doesn't keep a shortcut to a screen
that now 403s. Quick-create shortcuts are filtered the same way.

Five entries stay open by design and say so in comments: Dashboard, Business
Profile, Attendance, Leave, Reports.

**Report Center** was ungated entirely — a cashier could read the P&L at
`/reports/finance.profit_loss`. The catalog is now filtered per report family
(`sales.*` → `sales.view`, `finance.*` → `finance.view`, …) and `show()` checks
the same map, so a hand-typed URL fails too. Unmapped families fail closed.

---

## P2 — Success messages were silently discarded  ✅ fixed

Vendor controllers return `->with('status' | 'success' | 'error')` **184 times**
(321 including the platform side). `HandleInertiaRequests::share()` had no
`flash` key and no page read one — every confirmation was written to the session
and thrown away.

- Middleware now shares the flash bag, read from `_flash.old` so new keys work
  without editing the file, filtered to scalars (some controllers flash whole
  models as view data).
- `hooks/useFlashToasts.ts` feeds it into the already-mounted
  `BiNotificationProvider`. Mounted once in each layout, so all ~320 call sites
  work untouched.
- The 284 status slugs follow a strict `{subject}-{verb}` shape, so
  `supplier-created` → "Supplier created" is derived rather than table-driven,
  and the trailing verb picks the tone. Controllers wanting specific wording use
  `->with('success', '…')`, passed through verbatim.

---

## P2 — Dashboard cost  ✅ fixed

`/dashboard` fanned out to nine services (~100 aggregate queries) with no
caching, re-run on every visit and every browser Back.

Now `Cache::remember` for 60s, **keyed by business *and* by the caller's
dashboard-relevant permission set** — a business-only key would have served one
employee another's widgets, so the cache can never widen what someone sees.

`businessHealth` and `recentActivity` were the only two widgets not permission-
gated, which made them a way around the six checks around them. Both are gated now.

---

## P2 — Every response carried the full permission catalogue  ✅ fixed

`auth.user` was the whole User model with `roles.permissions` eager-loaded —
all 171 permission rows, as objects, on every request, none of it read by the
UI. Replaced with an explicit nine-field shape; `auth.role` is `->only(...)` for
the same reason. `auth.permissions` (slugs) already carried what the UI needs.

---

## P3 — N+1 in the ledger  ✅ fixed

Both the General Ledger index and the Trial Balance called
`accountBalance()` inside a `map()` over every active account — one aggregate
query per row, getting slower with every account added.

New `GeneralLedgerService::accountBalances()` computes the whole chart in one
grouped query; `signBalance()` applies the normal-side sign so the bulk and
single-account paths can't drift.

**Not** paginated: Suppliers, Categories, Brands, Tags, Collections and the
other lookup tables. They're bounded in practice and the pages have inline
create/edit — pagination would make them worse, not better.

---

## P3 — Consistency  ✅ fixed

- **All 54 native `confirm()` dialogs** across 45 files migrated to the styled
  `useConfirm` dialog, with tone and button label derived per call site.
  `react-hooks/rules-of-hooks` is clean, which is what verifies the hook landed
  in valid component scope everywhere.
- **8 module layouts** no longer cap at `max-w-7xl`, matching `/dashboard`.
  Content width no longer jumps between modules.
- **19 raw `<select>` elements** replaced with `SelectInput`.
- **Error boundary** added at the app root — a render error now shows an
  explanation and a reload button instead of a white page.
- Website unpublish confirmation copy corrected: it claimed visitors would see
  the default template, which stopped being true when unpublish started serving
  the 503 "temporarily unavailable" page.

**i18n was left alone.** `translations.ts` covers 62 keys (en + sw, both
complete) — essentially the sidebar — against ~1,000 hardcoded English strings
in the vendor pages. Extracting and translating those is a content project, not
a refactor, and guessing at Swahili business terminology would be worse than
leaving it visibly untranslated.

---

## Verification

| Check | Result |
|---|---|
| `npx tsc --noEmit` | clean |
| `react-hooks/rules-of-hooks` | 0 violations |
| ESLint non-prettier issues | 22, all pre-existing (unused imports, exhaustive-deps in untouched files) |
| Native `confirm()` remaining | 0 |
| Bare-string `can('x.y')` remaining | 0 |
| `max-w-7xl` in module layouts | 0 |
| Raw `<select>` in vendor pages | 0 |
| Unguarded model-bound controller methods | 10, all correct by design (signed-URL invite, public storefront, license API) — was 20 |
| PHP brace/paren balance, all 17 changed files | balanced |

### Test suite

`php artisan test` reported **8 failures / 658 passing**. Six were stale tests
asserting behaviour that was deliberately changed; two are environmental.

| Failure | Cause | Action |
|---|---|---|
| `EmployeeInvitationTest` ×3 | posted `role_id`; the endpoint takes `role_ids` since employees can hold several roles | tests updated, plus new coverage for multi-role assignment and the empty-list rejection |
| `PermissionMatrixTest` | asserted a literal 2 platform roles; the catalogue is now 12 | counts from the seeder instead of hardcoding |
| `BusinessTypeManagementTest` | created a `support-agent` role that is now seeded → unique-index collision | uses a test-only slug; the role only needs to grant nothing |
| `IntegrationTestDriversTest` (Gemini) | asserted `model_count`; the driver now does a real generation instead of listing models, because a listing passes even with a retired model | rewritten against the Interactions API shape, plus a case for the `model` credential |
| `BackupServiceTest`, `BackupManagementTest` | `backup:run` exits non-zero — `mysqldump` isn't resolvable | **not a code fault.** Set `DB_DUMP_BINARY_PATH=/Applications/XAMPP/xamppfiles/bin/` in `.env.testing` |

**New: `tests/Feature/Payroll/PayrollAuthorizationTest.php`** — 12 HTTP tests
covering the security fix, which had none. The existing Payroll suite tested the
services directly, which is precisely why the gap survived: the services were
fine, the controllers in front of them let anyone through.

---

## Open question — the tenant role catalogue grants no Payroll permissions

`RoleProvisioningService::DEFAULT_GRANTS` gives `leave.*`, `attendance.*` and
`payroll.*` to **no role except Owner** (which holds `'*'`). Not Manager, not
Accountant, not Branch Manager.

The permissions exist in `PermissionSeeder` and are now enforced, so today only
the business owner can approve leave, review attendance corrections or open the
HR dashboard. That looks like an oversight rather than a decision — but which
roles should get them is a product call, so nothing was changed.

Suggested, if you want it: `leave.view`/`leave.approve` on Manager and Branch
Manager, `attendance.*` on Branch Manager, `payroll.view` on Accountant.
