# Deploying to GoDaddy (cPanel) — Step by Step

You have cPanel access, nothing is live yet. This deploys **both** apps:
the existing Admin panel (`codegurukul/`) and the new Student Portal
(`student-portal/`). No SSH needed — everything here uses cPanel's File
Manager, phpMyAdmin, and MySQL Databases tools.

**Ready-to-upload packages are already built** in `deploy-packages/`:
- `codegurukul-admin.zip`
- `student-portal-api.zip`
- `student-portal-web.zip`

## Site map this guide sets up

| URL | What's there | Where the files physically live |
|---|---|---|
| `https://yourdomain.com/` | Student portal frontend (static HTML/CSS/JS) | `public_html/` |
| `https://yourdomain.com/api/` | Student portal backend (PHP) | `public_html/api/` |
| `https://admin.yourdomain.com/` | Admin panel | Outside `public_html` entirely, for security — see Step 4 |

The Admin panel gets its own subdomain with a **custom document root**
pointed at its `public/` folder specifically — this means `app/`,
`config/`, `database/` etc. for the Admin panel are never reachable by any
URL at all (the strongest version of the protection its own `.htaccess`
already provides as a backup). The Student Portal API can't use this same
trick because `/api` has to be a literal path under the primary domain
(shared hosting can't proxy a path to a different docroot) — that's why its
`app/`/`config/`/etc. instead rely on `.htaccess` blocking, same as today.

---

## Step 1 — Create the database

cPanel → **MySQL® Databases**

1. Under "Create New Database", enter `codegurukul` (GoDaddy will prefix it,
   e.g. `yourusername_codegurukul`) → Create Database.
2. Under "MySQL Users → Add New User", create a user (e.g.
   `yourusername_cguser`) with a strong generated password. **Save this
   password somewhere — you'll need it twice below.**
3. Under "Add User To Database", assign that user to the database with
   **All Privileges**.

Note the three real values you now have: the full database name (with
prefix), the full username (with prefix), and the password. You'll use these
in both `.env` files.

## Step 2 — Import both schema files

cPanel → **phpMyAdmin** → select your `codegurukul` database in the left
sidebar → **Import** tab.

1. Import `codegurukul/database/schema.sql` first (the Admin panel's full
   schema — tables, roles, permissions).
2. Then import `student-portal/api/database/schema_student_portal.sql`
   (everything the Student Portal adds — this only adds/extends tables, it
   never touches what the first import created).

If phpMyAdmin's upload size limit rejects either file (`schema.sql` is the
larger one), use the **SQL** tab instead and paste the file's contents
directly, or ask GoDaddy support to raise `upload_max_filesize`/
`post_max_size` for your account — both are a few hundred KB of plain SQL,
not large by any normal measure, this only happens on very restrictive
shared-hosting limits.

## Step 3 — Select PHP version

cPanel → **Select PHP Version** (sometimes labeled **MultiPHP Manager**).

Both apps need **PHP 8.3** (the Admin panel's `composer.json` pins
`"php": "^8.3"` explicitly). If 8.3 isn't in the dropdown for your hosting
plan/region yet, pick the highest 8.x available and contact GoDaddy support
to ask about upgrading — don't silently deploy against an untested lower
version. Also enable the **pdo_mysql** and **mbstring** extensions if there's
a checklist for them (most GoDaddy PHP 8.x profiles have both on by default).

This setting is typically per-domain — you'll set it once for the primary
domain and once for the `admin` subdomain after Step 4 creates it.

## Step 4 — Create the Admin subdomain with a custom document root

cPanel → **Subdomains**

1. Subdomain: `admin`, Domain: `yourdomain.com` → this proposes a document
   root of `public_html/admin` by default. **Change it** to something
   outside `public_html` entirely, e.g. `codegurukul-admin/public` (cPanel
   resolves relative paths from your home directory, so this becomes
   `~/codegurukul-admin/public`).
2. Create.

You now have an empty `~/codegurukul-admin/` folder waiting for the Admin
panel's files, with only its `public/` subfolder actually web-reachable at
`admin.yourdomain.com`.

**If the document-root field is greyed out or forced inside `public_html`**
on your specific plan: fall back to `public_html/admin-app` instead, and
accept that `app/`/`config/`/etc. are then protected only by the Admin
panel's own `.htaccess` block-list (same protection level the Student
Portal API already relies on) rather than being entirely unreachable. Either
way the rest of this guide is identical — just substitute whichever path you
actually ended up with everywhere `codegurukul-admin/` is mentioned below.

## Step 5 — Upload and extract the Admin panel

cPanel → **File Manager**

1. Navigate to `codegurukul-admin/` (the folder Step 4 created, one level
   above its `public/`).
2. **Upload** → select `deploy-packages/codegurukul-admin.zip` from your
   computer.
3. Once uploaded, right-click it → **Extract** → extract into the current
   folder (`codegurukul-admin/`).
4. Delete the uploaded zip afterward (File Manager → right-click → Delete) —
   no need to leave it taking up space once extracted.
5. Still in File Manager, create a new file named `.env` directly inside
   `codegurukul-admin/` (use **+ File**, then **Edit** it) with this content,
   filling in the real database values from Step 1:

```
APP_NAME="CodeGurukul LMS"
APP_URL=https://admin.yourdomain.com
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:REPLACE_WITH_32_RANDOM_CHARS
APP_TIMEZONE=Asia/Kolkata

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=yourusername_codegurukul
DB_USERNAME=yourusername_cguser
DB_PASSWORD=the_password_from_step_1
DB_CHARSET=utf8mb4

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE=true

LOG_LEVEL=error
LOG_CHANNEL=file
```

For `APP_KEY`, generate 32 random characters any way you like (a password
manager's generator is fine) and prefix with `base64:` — the Admin panel
just needs a long, unpredictable, stable string here, not literally
base64-encoded bytes.

Leave `MAIL_*`/`RAZORPAY_*`/`OPENAI_*`/etc. out entirely for now (see
`codegurukul/.env.example` for the full optional list) — the app reads
missing env vars as unset and those features simply won't be active until
you add real credentials later.

## Step 6 — Upload and extract the Student Portal backend

File Manager → navigate to `public_html/` → create a new folder named `api`
→ go into it.

1. **Upload** `deploy-packages/student-portal-api.zip` into `public_html/api/`.
2. **Extract** it there, then delete the zip.
3. Create `.env` inside `public_html/api/` (same technique as Step 5):

```
APP_NAME="CodeGurukul Student Portal API"
APP_URL=https://yourdomain.com/api
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata
APP_KEY=a-different-long-random-string-than-the-admin-panels

FRONTEND_URL=https://yourdomain.com

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=yourusername_codegurukul
DB_USERNAME=yourusername_cguser
DB_PASSWORD=the_password_from_step_1

AI_API_KEY=your-real-anthropic-api-key
```

Same database, same credentials as Step 5 — this is one physical database
shared by both apps, per the whole architecture's design. **`APP_KEY` is
required here** (generate it the same way as the Admin panel's, but use a
*different* value) — the app refuses to start without it, since it's what
signs the temporary download links for materials/recordings (there's no S3
involved; files are served from this app's own disk via
`public_html/api/storage/app/`, which `.htaccess` already keeps off the
public web). `AI_API_KEY` is what makes the AI Doubt Solver/Coding Assistant
and the project-originality cron below actually work — leave it blank and
those features stay inert (a clean `503`, not a crash) rather than failing
to deploy at all.

## Step 7 — Upload the Student Portal frontend

File Manager → `public_html/` (the root itself this time, not a subfolder).

1. **Upload** `deploy-packages/student-portal-web.zip` directly into
   `public_html/`.
2. **Extract** it there, then delete the zip. You should now see
   `index.html`, `login.html`, `dashboard.html`, and `assets/` sitting
   directly in `public_html/`, alongside the `api/` folder from Step 6.

## Step 8 — Set up the fifteen Cron Jobs (cPanel → Cron Jobs)

All fifteen are ordinary GoDaddy shared-hosting cron jobs — not a cloud
service, just cPanel running a PHP CLI script on a schedule. Without these,
the AI project-originality check, the Progress Analytics dashboard, Monthly
Parent Reports, Course Completion/Certificates, stale Collaborative Coding
sessions, Risk Detection, Course Recommendations, the Communication
Engine's cadence/batch/outbox processing, ledger archival/pruning, and the
infrastructure/ops checks (credit-ledger reconciliation, SLA breaches,
nightly backup) simply never get processed (everything else in the app
works fine regardless).

1. **Project originality check** — every 15 minutes:
   ```
   */15 * * * * php /home/yourusername/public_html/api/cron/process-originality-checks.php >> /home/yourusername/cron-originality.log 2>&1
   ```
2. **Nightly progress snapshot** — once a day, e.g. 2:00 AM:
   ```
   0 2 * * * php /home/yourusername/public_html/api/cron/compute-progress-snapshots.php >> /home/yourusername/cron-progress.log 2>&1
   ```
3. **Nightly risk score computation** — once a day, e.g. 1:00 AM (run before the progress snapshot, though there's no hard ordering requirement since this is its own table):
   ```
   0 1 * * * php /home/yourusername/public_html/api/cron/compute-risk-scores.php >> /home/yourusername/cron-risk.log 2>&1
   ```
4. **Monthly parent reports** — once a month, e.g. the 1st at 3:00 AM (reads the risk scores cron above, so it should already be running daily by the time this fires):
   ```
   0 3 1 * * php /home/yourusername/public_html/api/cron/generate-parent-reports.php >> /home/yourusername/cron-reports.log 2>&1
   ```
5. **Course completion check** — every hour:
   ```
   0 * * * * php /home/yourusername/public_html/api/cron/check-course-completion.php >> /home/yourusername/cron-completion.log 2>&1
   ```
6. **Stale collaborative-coding session cleanup** — every 5 minutes:
   ```
   */5 * * * * php /home/yourusername/public_html/api/cron/end-stale-collab-sessions.php >> /home/yourusername/cron-collab.log 2>&1
   ```
7. **Course recommendations** — once a day, e.g. 4:00 AM (also closes the loop on marking earlier recommendations converted):
   ```
   0 4 * * * php /home/yourusername/public_html/api/cron/generate-course-recommendations.php >> /home/yourusername/cron-recommendations.log 2>&1
   ```
8. **Communication Engine — cadence evaluator** (assignment reminders, etc.) — every 15 minutes:
   ```
   */15 * * * * php /home/yourusername/public_html/api/cron/process-cadences.php >> /home/yourusername/cron-cadences.log 2>&1
   ```
9. **Communication Engine — batch dispatcher** (drains queued/escalation/family-batched notifications) — every 30 minutes:
   ```
   */30 * * * * php /home/yourusername/public_html/api/cron/process-notification-batches.php >> /home/yourusername/cron-notif-batches.log 2>&1
   ```
10. **Communication Engine — domain_events outbox consumer** (Admin ↔ Student cross-system sync) — every 5 minutes:
    ```
    */5 * * * * php /home/yourusername/public_html/api/cron/process-domain-events.php >> /home/yourusername/cron-domain-events.log 2>&1
    ```
11. **Ledger content archival** (strips old AI-message/code-execution/exam-response bodies after exporting them to local disk — see `student-portal/README.md`'s Phase 7 section) — once a month, e.g. the 1st at 4:00 AM:
    ```
    0 4 1 * * php /home/yourusername/public_html/api/cron/archive-ledger-content.php >> /home/yourusername/cron-archive.log 2>&1
    ```
12. **domain_events pruning** (deletes only fully-processed rows older than 30 days — the one ledger this build deletes from at all) — once a day, e.g. 5:00 AM:
    ```
    0 5 * * * php /home/yourusername/public_html/api/cron/prune-domain-events.php >> /home/yourusername/cron-prune-events.log 2>&1
    ```
13. **Credit-ledger reconciliation** (compares `credit_wallets.credits_balance` against the `credit_transactions` ledger every night; any drift emails every active SuperAdmin immediately — see `student-portal/README.md`'s Phase 8 section) — nightly, e.g. 2:30 AM:
    ```
    30 2 * * * php /home/yourusername/public_html/api/cron/reconcile-credit-ledger.php >> /home/yourusername/cron-reconcile.log 2>&1
    ```
14. **Support ticket SLA breach check** — every 30 minutes:
    ```
    */30 * * * * php /home/yourusername/public_html/api/cron/check-support-sla-breaches.php >> /home/yourusername/cron-sla.log 2>&1
    ```
15. **Nightly database backup** (local `mysqldump`, gzip-compressed, 14-day rotation — see `student-portal/README.md`'s Phase 8 section for why this is local-only, not piped to any cloud storage, and what that does and doesn't protect against):
    ```
    30 1 * * * php /home/yourusername/public_html/api/cron/backup-database.php >> /home/yourusername/cron-backup.log 2>&1
    ```

Replace `yourusername` with your actual cPanel username (visible in the
File Manager path). cPanel's Cron Jobs UI has dropdowns for the schedule
instead of raw cron syntax if you'd rather not type it by hand. Check each
log file after its first scheduled run to confirm it's actually executing —
a wrong PHP path is the most common setup mistake (cPanel sometimes needs
`/usr/local/bin/php` instead of `php` depending on the account; "Select PHP
Version" from Step 3 only affects web requests, not cron, so cron may run a
different PHP version unless you check this).

**Auto-issued certificates need a default certificate template to exist
first** — `cron/check-course-completion.php` silently skips issuance (logs
a warning, doesn't crash) for any course whose completion criteria are met
but where no `certificate_templates` row exists yet. Create at least one
template (with `is_default=1`) via the Admin panel before relying on
auto-issuance.

**Course recommendations need a `course_next_steps` mapping to exist
first** — `cron/generate-course-recommendations.php` has nothing to
recommend (a correct, graceful no-op, not a bug) until rows exist in
`course_next_steps`. There's no Admin-panel UI for this yet; add rows
directly via phpMyAdmin's **SQL** tab, e.g.:
```sql
INSERT INTO `course_next_steps` (`source_course_id`, `recommended_course_id`, `sort_order`, `min_completion_percent`)
VALUES (1, 2, 0, 70);
```

**The Communication Engine's email channel needs `MAIL_*` set in `api/.env`
to actually deliver** — without it, `App\Core\SimpleMailer` will fail to
connect and every email attempt logs a `failed` row in `communication_logs`
(the in-app channel still works regardless, since it's a local DB write,
not a network call). Use the same cPanel mailbox credentials as the Admin
panel's own `.env` `MAIL_*` settings. WhatsApp and SMS channels are not
implemented at all in this build — see `App\Core\Notifier`'s docblock —
so there's nothing to configure for those; any trigger that falls back to
them will always log a documented failure and move to the next channel.

**`cron/backup-database.php` needs `mysqldump` and `gzip` on the box** —
both are standard on GoDaddy's Linux shared/VPS hosting (this is not an
extra package to install), but if a backup run logs a failure, check
`which mysqldump`/`which gzip` over SSH first. The script refuses to leave
a zero-byte/partial file behind on failure, so a failed run is always
visibly absent the next day, never a silently corrupt "backup."

**Point an external uptime service (UptimeRobot/Pingdom) at both health
endpoints** — `GET /api/v1/health` (fast liveness, poll every minute or
so) and `GET /api/v1/health/metrics` (slower, business-specific signals —
communication-queue backlog, AI spend today, the last credit-ledger
reconciliation and SLA-breach check results; poll a few times an hour).
This is this build's substitute for CloudWatch-style infrastructure
monitoring, which doesn't exist underneath shared hosting — see
`student-portal/README.md`'s Phase 8 section.

## Step 9 — Create your first login (SuperAdmin)

No seed user exists in `schema.sql` — create one via phpMyAdmin's **SQL**
tab, run against the `codegurukul` database:

```sql
INSERT INTO `users` (`role_id`, `first_name`, `last_name`, `email`, `password_hash`, `status`)
VALUES (1, 'Super', 'Admin', 'you@youremail.com',
'$argon2id$v=19$m=65536,t=4,p=2$SWpWQXJZLjJncEFNcGdlYQ$ZulbfJkJPcuAXMGmkPLRrDOVcsf7reZevdf8rJ54qH4',
'active');
```

That hash is for the password **`ChangeMe@123`** — log in once with it at
`https://admin.yourdomain.com/`, then change it immediately from inside the
Admin panel. Don't leave a known-to-this-guide password active.

This same `users` row (once you also create a Student/Parent account the
same way, with `role_id` 4 or 5) is what you'll use to log into the Student
Portal at `https://yourdomain.com/` too — both apps share this one table.

## Step 10 — Verify

1. `https://admin.yourdomain.com/` — should show the Admin login page. Sign
   in with the account from Step 8.
2. `https://yourdomain.com/api/v1/health` — should return
   `{"success":true,"data":{"status":"ok","database":true}}`. If `database`
   is `false`, recheck `public_html/api/.env`.
3. `https://yourdomain.com/` — should redirect to the Student Portal login
   page. Sign in with a Student/Parent account.
4. **Live classroom**: create a `live_classes` row (Admin panel, or directly
   via phpMyAdmin for a quick test) with `start_datetime` close to now and
   `status='live'`, with two Student accounts in its batch. Open
   `https://yourdomain.com/dashboard.html` as each student in two different
   browsers (or two devices) and click **Join** on the same class — you
   should see and hear both directions. This is the actual Google-Meet-style
   feature end to end, not just an API responding; worth checking for real
   before assuming it works on the live server, since browsers enforce
   camera/mic permissions and HTTPS requirements that only show up with a
   real domain (`getUserMedia` refuses to run at all over plain HTTP on
   anything other than `localhost`, which is exactly why Step 4's SSL note
   isn't optional for this specific feature).

If anything 500s, check `codegurukul-admin/storage/logs/` or
`public_html/api/storage/logs/` (via File Manager) for the actual PHP error
— both apps log there instead of displaying errors when `APP_DEBUG=false`.

## What's deliberately not covered here

- **HTTPS/SSL**: GoDaddy's cPanel has an **SSL/TLS Status** page with
  free AutoSSL for both the primary domain and the `admin` subdomain — turn
  it on for both before going live; nothing above assumes HTTP would work in
  production, the `https://` URLs throughout this guide assume it's already
  on. The live classroom specifically **will not work at all without it**
  (see Step 10.4) — browsers block camera/microphone access on any non-HTTPS,
  non-localhost origin.
- **Email, payments, SMS** (Admin panel) run in stub/disabled mode until you
  add real credentials to its `.env` — see `codegurukul/.env.example`.
- The Student Portal has **no external cloud dependency at all** by
  deliberate design — live classes are peer-to-peer WebRTC (no Agora), files
  are served from this app's own disk (no S3) — see
  `student-portal/README.md`'s "No-cloud architecture decisions" table for
  the full reasoning. The one narrow exception, once Phase 4d (AI features)
  is built, will be a direct pay-per-call API to an LLM provider — there's no
  way to run a capable AI model on shared PHP hosting itself, but that's a
  plain HTTPS call with an API key, not a service to set up.
