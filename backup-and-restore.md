# Backup & Restore

Two separate features, because the two dashboards need genuinely different
things and one of them cannot safely be given what the other has.

| | Platform (`/platform` → System → Backups) | Vendor (`/dashboard` → Settings → Backup & Restore) |
|---|---|---|
| Scope | The whole database | One business's records |
| Export | `.sql` (schema + data) and the existing `.zip` archives | `.sql` (data only, that business's rows) |
| Import | Uploaded `.sql` is **executed** | Uploaded `.sql` is **parsed, never executed** |
| Confirmation | Type `RESTORE DATABASE` | Type the business name |
| Permission | `backups.manage` (platform) | `backups.view` / `backups.create` / `backups.restore` |

---

## Why the vendor side is not just "mysqldump"

A vendor asking for "a .sql backup" is asking for something that, taken
literally, would be a serious breach:

- A real dump contains **every business on the platform**. Handing one to a
  vendor is handing them every competitor's customer list, pricing and
  revenue.
- Executing an uploaded `.sql` is **arbitrary SQL against the shared
  database**. Any vendor could grant themselves a subscription plan, edit
  another tenant's records, or insert themselves into `platform_users`.

So the vendor feature keeps the useful part of the request — a readable
`.sql` file you can keep, archive and restore from — without either of
those properties:

**Export** (`TenantSqlExportService`) walks a table map and emits only rows
where `business_id` matches, as ordinary `INSERT` statements. It streams in
500-row chunks; a busy shop's `sales` and `stock_movements` are the largest
thing in the system and a backup that dies with a memory error on the
businesses that most need one would be worse than no feature.

**Import** (`TenantSqlImportService`) never runs the file. It reads it line
by line, matches only its own `INSERT INTO \`table\` (…) VALUES (…);`
shape, checks the table against the allow-list, **rewrites `business_id` to
the importing business**, and writes through the query builder as
parameterised inserts.

Anything else in the file — `DROP`, `UPDATE`, `GRANT`, a `mysqldump`
header, a shell one-liner — simply doesn't match the pattern and is never
sent to the database. A malicious backup file isn't dangerous, it's just
ignored. There's a test for exactly this
(`test_dangerous_statements_in_an_uploaded_file_are_ignored`).

---

## What a vendor backup contains

Membership is **discovered from the schema** — every table with a
`business_id` column — so a new module's tables are included automatically
rather than being quietly missing from every backup until someone notices.

Child tables that have no `business_id` of their own (order lines, pivots)
are listed explicitly in `TenantTableMap::CHILD_TABLES` and selected
through their parent. Without them a backup would hold sales with no line
items and products with no images.

### What it deliberately does not contain

Each exclusion is a decision, and each is shown in the UI rather than
buried here — an owner relying on this for disaster recovery needs to know
before the day they need it.

| Table | Why |
|---|---|
| `subscriptions`, `subscription_transactions`, `payment_transactions` | Billing is owned by the platform. A restored subscription row would hand back a plan the business no longer pays for. |
| `licenses`, `business_module` | Entitlements follow the plan, not a file. |
| `users`, `roles` | Accounts, password hashes and permissions. Restoring these would change who can sign in and what they may do, via a file upload, outside the screens that audit it. |
| `audit_logs`, `impersonation_logs` | An audit trail you can restore over isn't one. |
| `support_tickets` | The other party is the platform, which didn't agree to a rollback. |
| `webhooks` | Endpoints and secrets — a restore could redirect outbound data. |
| `product_enquiries` | Submitted by the public, not by the business. |

The practical consequence worth stating plainly: **a vendor restore replaces
business records, not staff accounts.** Employees keep their logins and
roles across a restore.

---

## Restore behaviour

Replace, not merge. The business's rows in every covered table are deleted
and the backup's rows are inserted — so the result matches the backup
exactly, rather than being a third state that is neither the old data nor
the backed-up data.

Guards, in order:

1. **Format header.** A file without the `-- BiasharaMax Tenant Backup`
   marker is rejected with a clear message, so uploading a stray
   `mysqldump` gives an explanation rather than a confusing partial restore.
2. **Inspect first.** Upload and restore are two steps. The owner sees the
   source business, the date, and a per-table row count before anything is
   touched — the difference between "4,000 sales" and "3 sales" is the
   difference between restoring and destroying.
3. **Typed confirmation.** The business name, exactly. A checkbox is too
   easy to click through for something that deletes every record the
   business owns.
4. **One transaction**, with foreign key checks off for the duration, so a
   failure leaves the data as it was.

---

## Platform `.sql`

`DatabaseSqlDumpService` writes schema (`SHOW CREATE TABLE`) and data for
every table except runtime scaffolding (`sessions`, `jobs`, `cache`,
`password_reset_tokens` — restoring those would sign the wrong people in
and re-run work that already happened).

**Written in PHP, not by `mysqldump`** — on purpose. The existing `.zip`
backups shell out to it, and that is precisely what fails on this project:
XAMPP and Homebrew put the client binaries somewhere the web process's PATH
doesn't reach, so `backup:run` exits non-zero and the run lands in the
history as "failed". A backup that only works when a binary happens to be
on PATH is one you discover you don't have on the day you need it. The
trade-off is honest: slower than `mysqldump`, and MySQL/MariaDB only.

The `.zip` flow is untouched and still runs on its schedule — it also
covers uploaded files, which a `.sql` dump does not.

### Restoring a platform `.sql`

This one *does* execute the file: a schema restore has to run DDL, and a
platform admin already holds full authority over the database. The
safeguards are about mistakes, not privilege — inspect first, refuse
unrecognised files, and a typed `RESTORE DATABASE` confirmation.

One thing the UI states rather than hides: **MySQL commits implicitly on
DDL**, so a restore that fails partway through cannot be rolled back and
will leave the database partly restored. That's inherent to restoring a
schema on MySQL, not something the code can paper over.

---

## Two bugs the tests caught

Worth recording, because both were real and one was not a test problem.

### Every row was dumped twice

`Schema::getTableListing()` with no arguments lists tables from **every
schema the MySQL user can see**, not just the connected one. On a machine
with `biasharamax` and `biasharaos_testing` — or any dev and prod database
on one server, which is the normal XAMPP setup — `accounts` came back
twice, once per schema. Stripping the schema prefix collapsed them into a
duplicate entry, so the exporter dumped every table twice and the importer
faithfully tried to insert every row twice, hitting a duplicate primary
key.

This would have happened in production on any shared MySQL server. Both
`TenantTableMap` and `DatabaseSqlDumpService` now pass the connection's
database name explicitly and request unqualified names.
`test_the_table_map_contains_no_duplicates` and
`test_the_export_writes_each_row_exactly_once` guard it.

### Cross-business restore was never going to work

One test asserted that restoring business A's file into business B should
write the rows into B. **The test was wrong.** Primary keys are preserved
so foreign keys inside the backup stay valid, which means A's ids already
exist — the insert collides with A's own rows.

Remapping every key and every reference to it is a different feature
(clone a business), not a restore. So a backup now only restores into the
business it came from, which is also the safer rule: a file handed over by
a former employee or a competitor can't be loaded in as "my data". The
screen says so as soon as the file is inspected, before any confirmation
is typed.

### Also fixed while in there

`TenantTableMap` now memoises its lists per connection. `isAllowed()` is
called once per row during an import, and each call was resolving the
table map from scratch — roughly 170 schema queries. A 50,000-row restore
would have issued millions of them and never finished.

---

## Files

```
app/Domain/Backup/Support/TenantTableMap.php          allow-list + exclusions
app/Domain/Backup/Support/SqlValue.php                quote/parse, exactly symmetrical
app/Domain/Backup/Services/TenantSqlExportService.php vendor export
app/Domain/Backup/Services/TenantSqlImportService.php vendor import (parses, never executes)
app/Domain/Backup/Services/DatabaseSqlDumpService.php platform .sql export
app/Domain/Backup/Services/DatabaseSqlRestoreService.php platform .sql restore
app/Domain/Backup/Http/Controllers/BusinessBackupController.php
resources/js/Pages/Settings/Backups.tsx               vendor screen
resources/js/Pages/Platform/System/Backup/Index.tsx   platform screen (extended)
tests/Feature/Backup/BusinessBackupTest.php           11 tests, mostly the security boundary
```

New permissions: `backups.view`, `backups.create`, `backups.restore`
(tenant scope). Run `php artisan db:seed --class=PermissionSeeder` to add
them. Only the Owner role holds them by default, via its `'*'` grant —
restore replaces every record the business owns, so it isn't given to
Manager without an explicit decision.
