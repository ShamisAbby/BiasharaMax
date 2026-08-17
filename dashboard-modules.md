# Dashboard sections (module toggles)

Super Admin can switch each of the ten vendor dashboard sections on or off:
Business, Inventory, Purchasing, Sales, Finance, Customers & CRM, Website &
Online Store, Employees, Reports, Settings.

Most of this already existed — a `modules` registry, a `business_module`
pivot, plan and business-type assignment, and a `BusinessModuleResolver`.
What was missing is that **nothing called it**; the resolver's own docblock
said wiring it up was a deliberate follow-up. So this is mostly that wiring,
not a new system.

---

## The default is the risky part

`business_type_module`, `module_subscription_plan` and `business_module` are
all empty on an existing installation — and until the seeder runs, so is the
module registry itself. A resolver that reads "no rows" as "nothing allowed"
would 404 every page for every live tenant the moment it shipped, before
anyone had switched anything off.

So the resolver answers the inverted question: not *which modules are
allowed* but **which have been switched off**. It returns a list of hidden
slugs, and that negative list is what travels to the frontend. Anything
nobody has said anything about stays visible.

The practical consequence: an unseeded or half-configured installation
behaves exactly as it did before this feature existed. Two tests hold that
in place, one of them deleting every registry row and asserting the
dashboard still works.

It is safe because this gates *which features a business has*, not *who may
use them*. Authorization is still `hasPermission()`, and that one fails
closed.

---

## Four layers

Applied in order by `BusinessModuleResolver`:

| # | Layer | Set where | Notes |
|---|---|---|---|
| 1 | **Global** | Module registry `status` | The kill switch. Nothing below can re-enable it. |
| 2 | **Business type** | `business_type_module` | A retail business need not carry Payroll. |
| 3 | **Subscription plan** | `module_subscription_plan` | Packaging: Basic gets Sales, Pro adds Finance. |
| 4 | **Per business** | `business_module` | The exception support reaches for. Can grant a section the plan excludes. |

Layer 4 is tri-state, which matters: **on**, **off**, and **no override**
(follow the plan and type). "Off" and "no override" produce the same result
today but mean completely different things tomorrow when the plan changes.

---

## What a vendor sees

Hidden completely, as chosen:

- Gone from the sidebar, the ⌘K palette and quick-create.
- The routes return **404** — not 403. The business doesn't have the
  section; telling them it exists but is forbidden is both untrue and the
  opposite of hidden.
- Search returns no results from it, because those results would link to
  pages that now 404.

**Hiding the menu is not access control.** URLs are guessable, get
bookmarked and live in history, so `EnsureModuleIsEnabled` gates the route
groups server-side. The frontend filtering exists only so nothing advertises
a dead link.

Background work keeps running, as chosen. Turning Finance off hides the
screens; Sales still posts journal entries, so the books stay correct and
nothing has to be backfilled when it is turned back on.

---

## Where a section's routes don't match its menu group

Two links live under a different module than the section they appear in, and
both would 404 if this weren't handled:

- **Customers** is displayed under CRM but served by `sales.*`.
- **Suppliers** is displayed under Purchasing but served by `inventory.*`.

Nav leaves can therefore declare their own `module`, which overrides the
section's.

---

## The admin screen

`/platform` → Businesses → **Sections**.

Each row shows the effective state *and the reason for it* — "Not included
in their subscription plan", "Disabled for this business specifically",
"Switched off platform-wide". With four layers stacked, a bare on/off toggle
is unusable: an admin who switches Finance on and sees nothing change needs
to be told the plan is what's blocking it.

Three buttons per row: **On**, **Off**, **Follow plan** (clears the
override).

---

## Files

```
app/Domain/ModuleManagement/Support/DashboardModule.php          the 10 slugs, named once
app/Domain/ModuleManagement/Services/BusinessModuleResolver.php  the four layers
app/Http/Middleware/EnsureModuleIsEnabled.php                    route gating (404)
app/Domain/Platform/Http/Controllers/BusinessModuleController.php
resources/js/Pages/Platform/Businesses/Modules.tsx               the admin screen
database/seeders/DashboardModuleSeeder.php
tests/Feature/ModuleManagement/DashboardModuleGatingTest.php     9 tests
```

The resolver is a **singleton** — it's consulted by the route middleware,
again when shared props are built, and again per search source. A fresh
instance each time would re-run the same four queries a dozen times a page.

The seeder **never touches `status` on an existing row**, so re-running it
can't undo an operator's decision. There's a test for that.

---

## To activate

```bash
php artisan db:seed --class=DashboardModuleSeeder
```

This registers the ten sections so they appear in the admin screen. Nothing
breaks if you deploy before running it — an unregistered section is simply
not managed, so it stays on. That's the point of the inverted default.
