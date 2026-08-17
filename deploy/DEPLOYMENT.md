# Deploying BiasharaMax

Target: Hostinger shared hosting, `ssh -p 65002 u713307449@77.37.37.66`
Repository: `https://github.com/ShamisAbby/BiasharaMax.git`

The scripts do the repeatable parts. This document covers the parts they
deliberately don't: anything needing hPanel, and anything needing a
password. A deploy script that prompts for a password is a script that
leaves the password in `~/.bash_history`.

---

## Before you touch the server

### 1. Push the code

The repository has **215 uncommitted changes** and, at the time of writing,
no remote configured. The server pulls from GitHub, so nothing there can
happen until this does.

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/BOS
git remote add origin https://github.com/ShamisAbby/BiasharaMax.git
git add -A
git commit -m "Rename to BiasharaMax, add deployment scripts"
git push -u origin main
```

### 2. Build the front-end

Shared hosting has no Node, so the bundle is built here and shipped in the
repository. `public/build` has been un-ignored for exactly this reason.

```bash
cd backend
npm ci
npm run build
git add public/build
git commit -m "Build production assets"
git push
```

**Do not skip this.** Without `public/build/manifest.json` every page loads
with no styling and no JavaScript — it looks like a broken server rather
than a missing build, which sends you debugging the wrong thing. Both
scripts check for it and say so.

### 3. Check what you are about to publish

```bash
php artisan test
```

Deploying a red suite to a machine customers can reach is a decision, not
an accident. Make it deliberately.

---

## On the server

### 4. Create the database

hPanel → **Databases → MySQL Databases**. Create a database and a user,
give the user all privileges on it, and keep the three values it shows you.

I can't do this step and shouldn't: it issues a password.

### 5. Clone and run setup

```bash
ssh -p 65002 u713307449@77.37.37.66
git clone https://github.com/ShamisAbby/BiasharaMax.git ~/biasharamax
cd ~/biasharamax
bash deploy/setup.sh
```

The first run copies the env template and stops, because it has nothing to
connect to yet. Fill it in:

```bash
nano ~/biasharamax/backend/.env
```

Required before it will continue: `DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`. Also set `MAIL_PASSWORD`, `SUPERADMIN_EMAIL`,
`SUPERADMIN_PASSWORD` and `PLATFORM_ALERT_RECIPIENTS` — the script does not
force these, but each one that stays blank is a feature silently switched
off.

Then run it again:

```bash
bash deploy/setup.sh
```

### 6. Point the domain at `backend/public`

hPanel → **Websites → biasharamax.com → Website settings → Document root**,
set it to:

```
/home/u713307449/biasharamax/backend/public
```

**This is the security-critical step.** Laravel puts one directory on the
web and keeps everything else above it. If the document root stays at
`public_html` and you copy the project into it, then `.env` — with your
database password, mail password and `APP_KEY` — is fetchable by anyone who
types the URL.

If your plan won't let you move the document root, use a symlink instead:

```bash
rm -rf ~/public_html
ln -s ~/biasharamax/backend/public ~/public_html
```

Verify before you announce the site:

```bash
curl -I https://biasharamax.com/.env    # must be 403 or 404, never 200
```

### 7. Cron

hPanel → **Advanced → Cron Jobs**. Two entries.

**Scheduler** — every minute. Six commands depend on it, including the
platform alert dispatcher, which is written to run every minute:

```
* * * * * cd /home/u713307449/biasharamax/backend && /usr/bin/php8.2 artisan schedule:run >> /dev/null 2>&1
```

**Queue** — every minute. There is no daemon on shared hosting, so instead
of a worker that stays up, this starts one that drains the queue and exits:

```
* * * * * cd /home/u713307449/biasharamax/backend && /usr/bin/php8.2 artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1
```

`--max-time=55` keeps it under the next minute's start, so two workers
never overlap. Without queued jobs running at all, notifications, stock
deductions on sale and goods-received alerts are accepted and never
happen — the UI says "sent" and nothing arrives.

Check the PHP path first, it varies by plan:

```bash
which php8.2 php8.3 php
```

### 8. HTTPS

hPanel → **Security → SSL**, issue the free certificate for
`biasharamax.com` and turn on **Force HTTPS**.

`SESSION_SECURE_COOKIE=true` in the env template means the session cookie
is only sent over HTTPS. Until the certificate is live, nobody can log in —
correct behaviour, confusing symptom.

---

## If composer says "your lock file does not contain a compatible set of packages"

`composer.json` asks for `^8.2`, but `composer.lock` was resolved on a
machine running PHP 8.4, so it pins `symfony/clock`, `symfony/string`,
`symfony/translation` and nine other packages at versions requiring
**PHP >= 8.4.1**. The lock decides, not composer.json.

The error names the PHP version it found and never mentions the lock file,
which is why it reads as a server problem rather than a resolution one.

**First, check what the server has:**

```bash
ls -d /opt/alt/php8*/usr/bin/php /usr/bin/php8.* 2>/dev/null
```

**If 8.4 is there** — set it in hPanel (**Websites → PHP configuration →
PHP version**) and re-run `setup.sh`. It now prefers the newest build it
can find, so it will pick 8.4 up automatically.

**If the newest is 8.3**, re-resolve the lock against that version. Do this
on your Mac, not the server:

```bash
cd backend
composer config platform.php 8.3.30
composer update --ignore-platform-reqs=ext-* -W
git add composer.json composer.lock && git commit -m "Pin platform PHP to 8.3 for Hostinger" && git push
```

`platform.php` tells composer to resolve as if it were running on the
server. It is the fix worth having regardless of which version you land
on: without it, whoever next runs `composer update` on a newer laptop
silently pins packages the server cannot install, and the failure surfaces
at deploy time rather than at update time.

Then on the server: `cd ~/biasharamax && git pull && bash deploy/setup.sh`

---

## Later deploys

```bash
ssh -p 65002 u713307449@77.37.37.66
cd ~/biasharamax && bash deploy/update.sh
```

It puts the site into maintenance mode, pulls, migrates, rebuilds caches
and brings it back — and brings it back even if a step fails, so a broken
deploy doesn't also leave the site down.

Rebuild and commit `public/build` locally whenever front-end code changes.

---

## Known limits of this setup

**Database cache and queue.** The app was written for Redis, which shared
hosting doesn't offer. Cache reads are now database queries. Fine at this
scale, worth revisiting on a VPS.

**Queue latency up to a minute.** Cron's finest granularity. A customer
receipt email may take a minute to send.

**No zero-downtime deploy.** `update.sh` shows a maintenance page for a few
seconds. Doing better needs atomic release directories and a symlink
switch, which needs a VPS.

**`public/build` in git.** Every build makes a large diff of hashed files,
and two machines building the same source produce different hashes. It's
the price of a host that can't build; reverse it if you move to one that
can.

---

## If something breaks

```bash
tail -50 ~/biasharamax/backend/storage/logs/laravel-$(date +%Y-%m-%d).log
```

A 500 with a blank page almost always means `storage/` isn't writable
(`chmod -R 775 storage bootstrap/cache`) or `APP_KEY` is empty.

A styled-but-dead page means `public/build` is missing or stale.

Do not set `APP_DEBUG=true` on a live server to investigate. The stack
trace it renders includes your database credentials, and it renders them to
whoever triggered the error.
