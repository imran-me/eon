# EON — deployment guide (Hostinger, next to the Epal ERP)

EON is a static front end (`index.html`, `app/`, `ai-companion/`) plus a plain-PHP
backend (`server/`) with an optional Python analytics service (`server/py/eon.py`).
Nothing in the ERP (Laravel 12, `erp.epal.com.bd`) is modified: EON reads the ERP
database with its own MySQL user and writes only to its own `eon_*` tables (or to
JSON files under `server/storage/data/` when those tables are absent).

Three deployment options are described, then smoke tests, troubleshooting, a
security checklist and the no-server fallback.

| | Option A — inside the ERP host | Option B — own subdomain | **Option C — subdomain + push-to-deploy** |
| --- | --- | --- | --- |
| URL | `https://erp.epal.com.bd/eon/` | `https://eon.epal.com.bd/` | **`https://eon.gulfrabit.com/`** |
| Where the files go | `public_html/eon/` of the ERP site | `public_html/` of a new site | `public_html/` of the subdomain, a git clone |
| Updates | manual `git pull` / upload | manual `git pull` / upload | **`deploy/deploy.sh` on cron (or a webhook) — `git push` is the deploy** |
| Database | same account, `localhost` | same account → `localhost`; other account → Remote MySQL | same as B (Remote MySQL if the ERP is on another account) |
| Browser ↔ API | same origin, no CORS needed | same origin (B1) or cross-origin with `origins` (B2) | same origin |
| Best for | production next to the ERP | a clean host name for the summit demo | day-to-day work: build here, push, it is live |

Option C is Option B plus `deploy/` — read B1 for the site itself, then C for the automation.

Host facts assumed throughout: Hostinger shared hosting (Apache/LiteSpeed,
`.htaccess` honoured), PHP 8.2 selected in hPanel, MySQL on `localhost`, cron via
hPanel, SSH with `composer` and `python3`. `USER` below is the hosting username
(`u123456789`); Hostinger prefixes database and user names with it
(`u123456789_erp`, `u123456789_eon`).

---

## 0. Before you start

1. **PHP version** — hPanel → Advanced → PHP Configuration → **8.2** for the site
   (the code uses `match`, `never`, named arguments). Extensions needed: `pdo_mysql`,
   `mbstring`, `curl`, `json` (all on by default).
2. **SSH** — hPanel → Advanced → SSH Access. Over SSH check:
   ```bash
   php -v            # 8.2.x expected; if not, call the 8.2 binary explicitly (e.g. /opt/alt/php82/usr/bin/php)
   composer --version
   python3 --version # 3.8+ is fine
   date              # server clock — matters for the cron hours below
   ```
3. **Anthropic API key** — from the Anthropic console; keep it out of git.
4. **HTTPS** — hPanel → Security → SSL, force HTTPS. Voice (Web Speech API)
   only works in a secure context.

---

## Option A — EON inside the ERP host (`erp.epal.com.bd/eon/`)

### A1. Put the repo in `public_html/eon/`

The Laravel site's document root is `/home/USER/domains/erp.epal.com.bd/public_html/`
(the ERP's `public/`; if `public_html` is a symlink to it, use whichever directory
`https://erp.epal.com.bd/` actually serves). Over SSH:

```bash
cd /home/USER/domains/erp.epal.com.bd/public_html
git clone https://github.com/imran-me/eon.git eon        # later updates: cd eon && git pull
```

Or upload the repo as a zip with hPanel → Files → File Manager and extract it as
`public_html/eon/`. Either way the layout must be:

```
public_html/eon/
├── index.html  app/  ai-companion/          ← served statically
└── server/                                  ← PHP runs here
    ├── api/  lib/  cron/  install/  py/  storage/
    ├── config.example.php → config.local.php
    └── .htaccess  composer.json
```

Why this works without touching the ERP: Laravel's `public/.htaccess` only rewrites
requests that are **not** an existing file or directory, so everything under
`/eon/` is served directly; `server/.htaccess` turns `RewriteEngine On` again in
that subtree so Laravel's rules never apply to the API.

If you cloned with git, hide the `.git` folder from the web — create
`public_html/eon/.htaccess` with:

```
RedirectMatch 404 /\.git
Options -Indexes
```

### A2. `server/.htaccess` — what it does (already in the repo)

- `Options -Indexes` — no directory listings.
- Denies `config.local.php`, `config.example.php`, `bootstrap.php`, `composer.*`.
- `RewriteRule ^(lib|cron|install|storage|vendor|py)(/|$) - [F,L]` — only `api/` is
  web-reachable; reports in `storage/data/` are downloaded through `api/file.php`.
- Passes the `Authorization` header through to PHP (`HTTP_AUTHORIZATION`), which
  some hosts strip — needed for `Bearer` tokens.

Nothing to change unless the host ignores `.htaccess` (Hostinger honours it).

### A3. Configuration — `server/config.local.php`

```bash
cd /home/USER/domains/erp.epal.com.bd/public_html/eon/server
cp config.example.php config.local.php
chmod 600 config.local.php
```

`config.local.php` is git-ignored, denied by `.htaccess`, and merged over
`config.example.php` (`array_replace_recursive`), so it only needs the keys you
change. A production file for Option A:

```php
<?php
return [
    'token'   => 'PASTE-A-LONG-RANDOM-SECRET',        // php -r 'echo bin2hex(random_bytes(32));'
    'origins' => ['https://erp.epal.com.bd'],         // exact scheme+host of every page that calls the API

    'db' => [                                          // the ERP database, READ-ONLY user
        'enabled' => true,
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'u123456789_erp',
        'user'    => 'u123456789_eonro',
        'pass'    => '********',
    ],
    // EON's own tables. null = the eon_* tables live in the ERP database (needs
    // INSERT/UPDATE/DELETE on those six tables only). On shared hosting a separate
    // small database with its own full-rights user is the simplest way to keep the
    // ERP user SELECT-only:
    'eon_db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'u123456789_eon', 'user' => 'u123456789_eon', 'pass' => '********', 'charset' => 'utf8mb4'],

    'anthropic' => [
        'api_key'        => 'sk-ant-...',              // or export ANTHROPIC_API_KEY (see below)
        'model'          => 'claude-opus-5',
        'effort'         => 'high',
        'max_tokens'     => 4096,
        'allow_sql_tool' => true,                      // only with a read-only DB user
    ],

    'boss'    => ['name' => 'Md Imran Hossain', 'title' => 'Managing Director', 'email' => 'imran@epal.com.bd', 'company_id' => null],
    'company' => ['name' => 'Epal Group', 'timezone' => 'Asia/Dhaka', 'currency' => 'BDT'],
    'notify'  => ['email_to' => 'imran@epal.com.bd', 'email_from' => 'eon@epal.com.bd', 'webhook' => ''],
    'python'  => ['bin' => '/usr/bin/python3'],       // `which python3` over SSH; null = auto
    'cache_ttl' => 300,
];
```

Environment overrides also work and take precedence: `ANTHROPIC_API_KEY`,
`EON_TOKEN`, `EON_DB_HOST` / `EON_DB_NAME` / `EON_DB_USER` / `EON_DB_PASS`
(setting any `EON_DB_*` also enables the DB), `EON_PYTHON`. On shared hosting
there is no per-site environment panel for PHP, so the practical choices are the
config file (recommended) or `SetEnv ANTHROPIC_API_KEY sk-ant-...` in
`server/.htaccess`; cron jobs would then need the variable on the cron line
(`ANTHROPIC_API_KEY=... php ...`). The config file covers web and cron alike.

`email_from` should be a real mailbox on the domain (SPF) or Hostinger's `mail()`
may be dropped. `webhook` receives `POST {title, text}` (WhatsApp/SMS gateway, Slack).

### A4. Read-only MySQL user + EON tables

hPanel → Databases → Management:

1. Note the ERP database name (`u123456789_erp`).
2. **Create user** `u123456789_eonro`, add it to the ERP database, then
   **Change permissions** → tick only `SELECT`. This is the user in `db`.
3. Recommended: **create a second database** `u123456789_eon` with its own
   full-rights user; put it in `eon_db`. Import the schema into *that* database:
   ```bash
   mysql -h localhost -u u123456789_eon -p u123456789_eon < /home/USER/domains/erp.epal.com.bd/public_html/eon/server/install/schema.sql
   ```
   (or hPanel → phpMyAdmin → Import → `server/install/schema.sql`).
   If you prefer one database, import the schema into the ERP database and grant
   the EON user `SELECT, INSERT, UPDATE, DELETE` on the six `eon_*` tables — the
   exact `GRANT` lines are commented at the bottom of `install/schema.sql` (needs a
   user with GRANT rights, i.e. usually a VPS, not shared hosting).

The schema is idempotent (`CREATE TABLE IF NOT EXISTS`). If the tables are not
reachable, `Memory` transparently falls back to JSON files in
`server/storage/data/` — `health.php` shows `"memory":"files"` instead of `"mysql"`.

### A5. Composer — the Anthropic PHP SDK

```bash
cd /home/USER/domains/erp.epal.com.bd/public_html/eon/server
composer install --no-dev --optimize-autoloader     # installs anthropic-ai/sdk into server/vendor/
```

`vendor/` is git-ignored and web-blocked. If `composer` is missing, install it
locally: `php -r "copy('https://getcomposer.org/installer','composer-setup.php');" && php composer-setup.php && php composer.phar install --no-dev -o`.
If memory is tight: `php -d memory_limit=-1 $(which composer) install --no-dev -o`.
Without the SDK the server still runs — `health.php` reports `"sdk":false` and Ask
EON answers from the rule-based brain.

### A6. Python (optional)

```bash
python3 -m pip install --user -r /home/USER/domains/erp.epal.com.bd/public_html/eon/server/py/requirements.txt   # numpy, openpyxl
python3 /home/USER/domains/erp.epal.com.bd/public_html/eon/server/py/eon.py health
```

`eon.py` works with the standard library alone (forecast, anomalies, evaluation,
CSV reports); numpy/openpyxl only add speed and `.xlsx` output (without openpyxl
reports are written as `.csv`). PHP calls it through `proc_open`
(`lib/Py.php`) — if that function is disabled in PHP (hPanel → PHP Configuration →
`disable_functions`) Python features return `ok:false` and everything else works.

### A7. Writable folders

```bash
cd /home/USER/domains/erp.epal.com.bd/public_html/eon/server
chmod 755 storage storage/cache storage/logs storage/data
```

PHP runs as your hosting user on Hostinger, so 755 is enough. `storage/cache/`
holds the dataset cache (`cache_ttl`), `storage/logs/eon.log` the log,
`storage/data/` memory JSON files, reports and `demo-dataset.json`.

### A8. Cron — morning brief and hourly watcher

hPanel → Advanced → Cron Jobs → type **Custom**. Absolute paths; PHP binary as
found by `which php` (usually `/usr/bin/php`). Both scripts accept an optional
company id argument (`... morning-brief.php 2`).

```
0 8 * * *  /usr/bin/php /home/USER/domains/erp.epal.com.bd/public_html/eon/server/cron/morning-brief.php >> /home/USER/domains/erp.epal.com.bd/public_html/eon/server/storage/logs/cron.log 2>&1
0 * * * *  /usr/bin/php /home/USER/domains/erp.epal.com.bd/public_html/eon/server/cron/watch.php         >> /home/USER/domains/erp.epal.com.bd/public_html/eon/server/storage/logs/cron.log 2>&1
```

- `morning-brief.php` rebuilds the dataset (`fresh`), computes and stores the brief
  and today's decisions, then e-mails/webhooks it (`notify`). Prints the spoken
  summary + `{"email":…, "webhook":…}`.
- `watch.php` runs hourly; any **new** critical/high decision since the last run
  is notified once (state in setting `watch_seen`).
- Cron hours are **server time**. If `date` over SSH shows UTC, 08:00 Dhaka is
  `0 2 * * *`. (The scripts themselves format dates in `Asia/Dhaka`.)
- Test by hand first: `php .../server/cron/morning-brief.php`.

### A9. Token — how the app authenticates

- Server side: `token` in `config.local.php` (or `EON_TOKEN`). Empty = open mode
  (any request accepted — demo/intranet only).
- The API accepts the token as `Authorization: Bearer <token>`, as header
  `X-EON-Token: <token>`, or as query `?token=<token>`. `health.php` is public;
  every other endpoint (`dataset`, `brief`, `ask`, `memory`, `actions`, `py`, `file`)
  requires it.
- Browser side: the Command Center reads `localStorage.eon_token` and sends it as
  `Authorization: Bearer …` (see `app/eon-app.js`). Set it once per browser, on the
  EON page, in DevTools:
  ```js
  localStorage.setItem('eon_token', 'PASTE-A-LONG-RANDOM-SECRET'); location.reload();
  ```
- Known gap in this revision: `ai-companion/adapters/erp-adapter.js` — which loads
  `dataset.php` and syncs `memory.php` — does **not** attach the token; only
  `app/eon-app.js` does. With `token` set, the adapter's dataset call gets a 401
  and the screens fall back to the demo dataset while Ask EON, brief and actions
  (which go through `eon-app.js`) work with live data. Until the adapter forwards
  `localStorage.eon_token`, either leave `token` empty **and** protect the whole
  `/eon/` folder with hPanel → Files → Password Protect Directories (HTTP Basic
  auth covers `index.html` and `server/api/` alike; the browser sends it
  automatically, `curl` needs `-u user:pass`), or set the token and accept demo
  screens for now.

### A10. CORS origins

Option A is same-origin, so `origins` is only consulted for other pages that call
the API (a GitHub Pages copy, localhost during development). Keep it explicit:
`['https://erp.epal.com.bd']`, add `'https://imran-me.github.io'` or
`'http://localhost:8080'` only when needed. `'*'` echoes any origin (with
credentials) — fine for the demo, not for production.

### A11. Open it

`https://erp.epal.com.bd/eon/` (with the trailing slash — relative paths depend on
it). The pill at the top says **Live · ERP + language model** when `health.php`
returns `db:true` and `llm:true`; the footer shows `erp · server ok · voice on`.

---

## Option B — EON on its own subdomain (`eon.epal.com.bd`)

### B1. Everything on the subdomain (recommended)

1. hPanel → Websites → Add website / Subdomains → `eon.epal.com.bd`; PHP 8.2; SSL.
   Document root: `/home/USER/domains/eon.epal.com.bd/public_html/`.
2. Deploy the repo **into that document root** (not a subfolder):
   ```bash
   cd /home/USER/domains/eon.epal.com.bd
   rmdir public_html 2>/dev/null; git clone https://github.com/imran-me/eon.git public_html
   ```
   Result: `public_html/index.html`, `public_html/server/…`. The adapter's default
   server URL is `./server/api` next to the page, so `index.html` needs no change.
3. Repeat A2–A10 with the new base path
   `/home/USER/domains/eon.epal.com.bd/public_html/server/`. Cron lines:
   ```
   0 8 * * *  /usr/bin/php /home/USER/domains/eon.epal.com.bd/public_html/server/cron/morning-brief.php >> /home/USER/domains/eon.epal.com.bd/public_html/server/storage/logs/cron.log 2>&1
   0 * * * *  /usr/bin/php /home/USER/domains/eon.epal.com.bd/public_html/server/cron/watch.php         >> /home/USER/domains/eon.epal.com.bd/public_html/server/storage/logs/cron.log 2>&1
   ```
4. Database:
   - Same hosting account as the ERP → `host => 'localhost'`, the read-only user
     from A4 works unchanged (MySQL is account-wide, not per site).
   - Different account/server → on the **ERP** account: hPanel → Databases →
     Remote MySQL → allow the EON site's IP (or `%`); then in `db` use the ERP
     account's MySQL host name shown there instead of `localhost`. Prefer the
     same account; remote MySQL is slower and exposes the port.
5. `origins => ['https://eon.epal.com.bd']`. Add `https://erp.epal.com.bd` only
   if an ERP page will embed or call EON.
6. Because Laravel is not on this site, add a small `public_html/.htaccess`:
   ```
   DirectoryIndex index.html
   Options -Indexes
   RedirectMatch 404 /\.git
   ```

### B2. Page on the subdomain, API on the ERP host

Use this when the PHP backend must stay next to the ERP (Option A already
deployed) and the subdomain only serves the front end.

1. Deploy the repo to `eon.epal.com.bd` as in B1 (the `server/` folder there is
   simply unused; you may delete it or leave it unconfigured).
2. In `index.html`, uncomment and set the config block **before** the adapter
   script (it already exists as a comment):
   ```html
   <script>
     window.EON_CONFIG = { server: 'https://erp.epal.com.bd/eon/server/api', company: null, demo: false };
   </script>
   <script src="./ai-companion/adapters/erp-adapter.js"></script>
   ```
   `server` is the base URL of `api/` (no trailing slash); `company` = default
   company id or `null` for the whole group; `demo: true` forces the demo dataset.
3. On the ERP host add the new origin: `origins => ['https://erp.epal.com.bd', 'https://eon.epal.com.bd']`.
   The API answers the preflight (`OPTIONS` → 204) and echoes the allowed origin.
4. Token: as A9, set `localStorage.eon_token` on `https://eon.epal.com.bd` (storage
   is per origin).

---

## Option C — `eon.gulfrabit.com`, push to GitHub and it goes live

The live site is a **git checkout**. Cron runs `deploy/deploy.sh` every few minutes:
one `git ls-remote` asks GitHub whether the branch moved, and only when it did does
it update the checkout, publish it (layout B), and run `deploy/post-deploy.php` —
runtime folders, `composer install` when `vendor/` is missing, EON's schema when its
database is reachable, cache clear, health report. When GitHub has not moved it
prints `up to date` and stops.

So the working loop becomes: **commit → `git push` → the site is on that commit.**

```
laptop ──git push──▶ github.com/imran-me/eon
                              │ cron */5 (or the webhook, seconds)
                              ▼
                     deploy/deploy.sh ── git reset --hard origin/main
                              └────────── post-deploy.php ── folders · composer · schema · cache · state.json
                                                   └──▶ https://eon.gulfrabit.com/
```

Files (all in `deploy/`, described in `deploy/README.md`): `deploy.sh`,
`post-deploy.php`, `webhook.php` (optional instant deploy), `notify.php` (failure
e-mail), `deploy.env.example`. Everything the host creates — `deploy.env`,
`deploy.log`, `state.json`, the lock — is git-ignored, and `deploy/.htaccess` denies
the whole folder to the web except `webhook.php`.

### C1. Which layout does this subdomain need?

hPanel → Websites → `eon.gulfrabit.com` → its **document root** answers it:

| Document root shown by hPanel | Layout |
| --- | --- |
| `/home/USER/domains/eon.gulfrabit.com/public_html` — its own folder | **A** — the checkout *is* the document root |
| `/home/USER/domains/gulfrabit.com/public_html/eon` — inside the main site | **B** — keep the checkout outside and publish into that folder |

Layout B exists because a folder inside another site's `public_html` can be
overwritten by that site's own deployment; the checkout stays at `~/eon-src`, and
each deploy copies the tree into the live folder (`rsync --delete`, or `git archive`
when the host has no rsync — both were exercised).

### C2. First deploy

PHP 8.2 and SSL on for the subdomain (hPanel → PHP Configuration / SSL), then SSH:

```bash
# layout A — the checkout is the document root
cd ~/domains/eon.gulfrabit.com
rm -rf public_html && git clone https://github.com/imran-me/eon.git public_html
bash public_html/deploy/deploy.sh --force

# layout B — checkout outside, published into the live folder
git clone https://github.com/imran-me/eon.git ~/eon-src
EON_PUBLISH_DIR=~/domains/gulfrabit.com/public_html/eon bash ~/eon-src/deploy/deploy.sh --force
```

The last lines of the run are the health report — it says what is still missing
(`token MISSING`, `ERP db off`, `offline brain`). That is expected before C3.

### C3. Configure the site (A3–A4 with this host name)

```bash
cd <the live folder>/server            # layout B: the published folder, not ~/eon-src
cp config.example.php config.local.php && chmod 600 config.local.php
composer install --no-dev --optimize-autoloader     # post-deploy does this too when composer exists
```

In `config.local.php`: `token` (32 random bytes), `origins => ['https://eon.gulfrabit.com']`,
the ERP `db` block (read-only user, A4) and `anthropic.api_key`. Two notes for this
host name:

- If `gulfrabit.com` is **not the same hosting account as the ERP**, `localhost` will
  not reach the ERP database: enable hPanel → Databases → **Remote MySQL** on the ERP
  account for this site's IP and use the host name it shows in `db.host`.
- `server/config.local.php`, `server/storage/`, `server/vendor/` and `deploy/deploy.env`
  are never touched by a deploy — in layout B they are on the keep-list of the
  publish step, in layout A they are git-ignored. Secrets, memory and logs survive.

In layout B, run `composer install` in the **published** `server/` (that is the one
serving requests); `~/eon-src` needs no vendor.

### C4. `deploy/deploy.env` — the host's own settings

```bash
cd <checkout>/deploy && cp deploy.env.example deploy.env && chmod 600 deploy.env
which php composer git rsync          # fill the paths in
```

```bash
branch = main                                            # only this branch goes live
publish =                                                # layout B: /home/USER/domains/gulfrabit.com/public_html/eon
src =                                                    # layout B: /home/USER/eon-src   (used by webhook.php)
php =                                                    # empty = found automatically
notify = 1                                               # e-mail/webhook the boss when a deploy FAILS
secret =                                                 # only for webhook.php (C8)
```

Environment variables still win over the file (`EON_BRANCH`, `EON_PUBLISH_DIR`,
`EON_PHP`, `EON_NOTIFY`), and `--branch=` / `--publish=` / `--force` / `--quiet` win
over both.

### C5. Cron — the deploy plus the two EON jobs

hPanel → Advanced → Cron Jobs → **Custom**:

```
*/5 * * * *  /bin/bash /home/USER/domains/eon.gulfrabit.com/public_html/deploy/deploy.sh --quiet
0 8 * * *    /usr/bin/php /home/USER/domains/eon.gulfrabit.com/public_html/server/cron/morning-brief.php >> /home/USER/domains/eon.gulfrabit.com/public_html/server/storage/logs/cron.log 2>&1
0 * * * *    /usr/bin/php /home/USER/domains/eon.gulfrabit.com/public_html/server/cron/watch.php         >> /home/USER/domains/eon.gulfrabit.com/public_html/server/storage/logs/cron.log 2>&1
```

Layout B — the deploy line points at the checkout, the EON jobs at the live folder:

```
*/5 * * * *  /bin/bash /home/USER/eon-src/deploy/deploy.sh --quiet
0 8 * * *    /usr/bin/php /home/USER/domains/gulfrabit.com/public_html/eon/server/cron/morning-brief.php >> /home/USER/domains/gulfrabit.com/public_html/eon/server/storage/logs/cron.log 2>&1
0 * * * *    /usr/bin/php /home/USER/domains/gulfrabit.com/public_html/eon/server/cron/watch.php         >> /home/USER/domains/gulfrabit.com/public_html/eon/server/storage/logs/cron.log 2>&1
```

- `--quiet` keeps cron mail empty; everything is written to `deploy/deploy.log`
  (trimmed to the last 500 lines). Anything cron still mails is a real failure.
- Every minute (`* * * * *`) is fine — an idle check is one `git ls-remote`.
- Concurrent runs lock each other out; a lock older than 10 minutes is cleared.
- Cron hours are server time; the Dhaka/UTC note in A8 applies to the brief and watcher.

### C6. Private repository — deploy key

Public repo: nothing to do. Private repo, read-only key for the host:

```bash
ssh-keygen -t ed25519 -C "eon.gulfrabit.com deploy" -f ~/.ssh/eon_deploy -N ""
cat ~/.ssh/eon_deploy.pub    # → GitHub → repo → Settings → Deploy keys → Add (no write access)
printf 'Host github.com\n  IdentityFile ~/.ssh/eon_deploy\n  IdentitiesOnly yes\n' >> ~/.ssh/config
cd <checkout> && git remote set-url origin git@github.com:imran-me/eon.git
ssh -T git@github.com        # accept the host key once, so cron never has to
```

### C7. Verify

```bash
# laptop
git commit -am "test: deploy marker" && git push origin main
# host, or just wait for cron
tail -n 20 <checkout>/deploy/deploy.log
```

```
2026-08-18 16:46:05  deploying 550c1ab → 4075671 (main)
2026-08-18 16:46:06  checkout 4075671 — test: deploy marker
2026-08-18 16:46:08    EON 4075671 · php 8.2.33 · token set · ERP db ok · EON db ok · model on · data erp
2026-08-18 16:46:08  live: 4075671
```

From anywhere:

```bash
curl -s https://eon.gulfrabit.com/server/api/health.php
# {"ok":true,…,"model":"claude-opus-5","commit":"4075671","deployed":"2026-08-18T16:46:07+06:00"}
```

`commit` is the hash the site is actually running — compare with
`git rev-parse --short HEAD` on the laptop. `deploy/state.json` holds the same record
plus `token`, `erp_db`, `eon_db`, `llm`, `source`. `deploy/deploy.sh --force`
redeploys the same commit; `deploy/deploy.sh` alone prints `up to date (sha)`.

### C8. Instant deploy (optional, seconds instead of minutes)

Two ways, both keeping the cron line as the fallback that heals a missed event:

1. **hPanel → Advanced → GIT** — connect the repository to the site and enable
   auto-deployment; paste the webhook URL it gives you into GitHub → Settings →
   Webhooks. Then run `post-deploy.php` from the deploy hook (or leave cron to do it).
2. **`deploy/webhook.php`** — for layout B, where hPanel Git cannot own the folder.
   In `deploy.env` set `secret` (`php -r 'echo bin2hex(random_bytes(24));'`), `src`
   and `publish`; in GitHub add the webhook with payload URL
   `https://eon.gulfrabit.com/deploy/webhook.php`, content type `application/json`,
   the same secret, push events only. It verifies `X-Hub-Signature-256`, ignores
   other branches and events, rate-limits itself to one deploy per 10 s, and runs the
   same `deploy.sh`. Without a secret it answers `503` and does nothing.

### C9. What a deploy does — and does not do

| Does | Does not |
| --- | --- |
| move the checkout to `origin/<branch>` (`git reset --hard`) | touch `config.local.php`, `server/storage/`, `server/vendor/`, `deploy/deploy.env` |
| publish into the document root, keeping those paths (layout B) | copy `.git` into the live folder |
| create the runtime folders and the per-directory `.htaccess` denies | change PHP versions, extensions or hPanel settings |
| `composer install` when `server/vendor/` is missing | reinstall packages on every deploy |
| apply `server/install/schema.sql` when the EON database is reachable and empty | migrate or drop anything in the ERP database (EON's user is SELECT-only there) |
| clear `server/storage/cache/*.json` | restart a daemon — plain PHP has none; opcache picks new files up by timestamp |
| log every run, notify the boss when a deploy fails | overwrite the site when GitHub is unreachable — it fails and the last good commit stays live |

**Rollback** is a push: `git revert <bad-sha> && git push`. On the host in an
emergency, `cd <checkout> && git reset --hard <good-sha>` (then `--force` a publish in
layout B) — but the next cron run follows the branch head again, so revert-and-push
is the real fix.

---

## Smoke test

Set `BASE` to the API base and `TOKEN` to your token (omit the header in open mode).

```bash
BASE=https://erp.epal.com.bd/eon/server/api      # Option B: https://eon.epal.com.bd/server/api
TOKEN=PASTE-A-LONG-RANDOM-SECRET

# 1. health — public, tells you what is connected
curl -s $BASE/health.php
# {"ok":true,"name":"EON server","version":"0.1.0","time":"…","php":"8.2.x",
#  "db":true,"source":"erp","llm":true,"llm_key":true,"sdk":true,
#  "memory":"mysql","auth":"token","model":"claude-opus-5"}

# 2. KPIs from the decision layers (add &company=2 to scope)
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/brief.php?what=kpis"
# {"ok":true,"what":"kpis","company":null,"source":"erp","data":{…}}
#   same call with the query form:  "$BASE/brief.php?what=kpis&token=$TOKEN"

# 3. the full ranked brief (also logs today's decisions to memory)
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/brief.php?what=brief"

# 4. Ask EON — the language-model agent with tools over the ERP data
curl -s -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
     -d '{"question":"What is our cash position today and who owes us the most?"}' $BASE/ask.php
# {"ok":true,"mode":"llm","model":"claude-opus-5","text":"…","speak":"…","tools_used":["…"],
#  "usage":{…},"conversation_id":"…","ms":…,"source":"erp"}
#   mode "offline" + a "note" means no key / no SDK; "llm_error" means the API call failed.

# 5. Python bridge
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/py.php?cmd=health"      # {"ok":true,"python":true,"bin":"/usr/bin/python3",…}
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/py.php?cmd=forecast&months=3"

# 6. dataset (large) — just check it is JSON with meta.source "erp"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/dataset.php" | head -c 300

# 7. cron by hand
php /home/USER/domains/erp.epal.com.bd/public_html/eon/server/cron/morning-brief.php
```

Then open the page in a browser: pill **Live · ERP + language model**, Brief shows
real companies in the scope selector, Ask EON's trace line reads
`language model · claude-opus-5 · tools: …`.

---

## Troubleshooting

`server/storage/logs/eon.log` is the first place to look (`tail -n 50`).

| Symptom | Cause → fix |
| --- | --- |
| `health.php` returns the ERP's HTML or a Laravel 404 | `eon/` is not inside the directory the site really serves — check `public_html` vs the Laravel `public/` symlink (A1). |
| `health.php` shows PHP source or a 500 before any JSON | PHP < 8.2 selected for the site, or `.htaccess` not applied. hPanel → PHP Configuration → 8.2. |
| `"db":false` | `db.enabled` not `true`, wrong `name`/`user`/`pass`, host should be `localhost` on Hostinger, or the user is not assigned to the database. Test: `mysql -h localhost -u DBUSER -p DBNAME -e 'select 1'`. `eon.log` has the PDO message. |
| `"source":"demo"` in the other endpoints | The DB is off (`db:false`) — `source` is `erp` only when the ERP connection works; see the row above. |
| `"llm":false`, `"llm_key":false` | No `anthropic.api_key` in `config.local.php` and no `ANTHROPIC_API_KEY` in the environment. |
| `"llm":false`, `"llm_key":true`, `"sdk":false` | `composer install` not run in `server/`, or `vendor/` not uploaded (it is git-ignored). Run A5. |
| Ask EON `mode:"offline"` with `llm_error` | Key invalid/expired, model name not enabled for the key, outbound HTTPS blocked, or PHP `curl` missing. Message is in the response and in `eon.log`. |
| `"memory":"files"` | `eon_*` tables not found with the configured user — import `install/schema.sql` into the `eon_db` (or grant on the ERP DB). Files still work. |
| `py.php?cmd=health` → `"python":false` | `proc_open` disabled in PHP, wrong `python.bin` (set the full path from `which python3`), or `py/eon.py` not readable. Test over SSH: `python3 server/py/eon.py health`. EON works without Python. |
| `401 unauthorised` | Token mismatch (copy again, no spaces), header stripped by the host (the `.htaccess` `HTTP_AUTHORIZATION` rule must be present — or use `?token=` / `X-EON-Token`), or `localStorage.eon_token` not set in this browser/origin. From the adapter's calls this is expected while the token gap in A9 stands. |
| Browser: pill **Server · demo data** although `db:true` | The adapter's `dataset.php` call failed: 401 (A9 token gap), or the dataset took > 30 s (first build on a big database — the next load hits `storage/cache/`; raise `cache_ttl`). |
| CORS error in the browser console | The page origin (scheme + host, no path) is missing from `origins`; the browser must see `Access-Control-Allow-Origin` on the preflight. Only relevant for B2, GitHub Pages and localhost. |
| `500` on any endpoint | Read `storage/logs/eon.log` (`Http::run` logs the exception with file/line). Common: `storage/` not writable, missing `pdo_mysql`, syntax-level failure from an older PHP. |
| `403` on `api/…` | Not from EON (only `lib/cron/install/storage/vendor/py` are blocked). Hostinger's mod_security can block a POST body it dislikes — check hPanel → Security, or rephrase the test question. |
| Site does not update after a push | Wrong branch (`branch=` in `deploy/deploy.env` vs the branch you pushed), cron line missing/wrong path, or the host cannot reach GitHub — run `bash <checkout>/deploy/deploy.sh` by hand and read `deploy/deploy.log`. Layout B: check `publish=` points at the real document root. |
| `health.php` shows an old `commit` | The deploy ran but `post-deploy.php` did not (no PHP binary found — set `php=` in `deploy.env`), or in layout B the publish step was skipped. `deploy/state.json` is written by post-deploy only. |
| `webhook.php` returns 401 / 503 | 401 = the GitHub secret and `secret=` in `deploy.env` differ; 503 = no secret configured (the webhook is inert until you set one). |
| Cron did nothing / no e-mail | Wrong absolute path or PHP binary; check `storage/logs/cron.log`. `mail()` needs a domain mailbox as `email_from`; check spam. Hour offset: server time vs Dhaka. |
| Voice button greyed out | Not HTTPS, or the browser lacks Web Speech (use Chrome/Edge). |
| Reports download fails | Only `report-<kind>-YYYYMMDD-HHMMSS.(xlsx|csv)` under `storage/data/` are served, through `file.php?name=…` with the token. |

---

## Security checklist

- [ ] The ERP database user in `db` has **SELECT only** (A4). EON never writes to
      ERP tables; `Erp::safeSelect` also refuses non-SELECT statements and appends a
      `LIMIT`, but the grant is the real guard.
- [ ] `anthropic.allow_sql_tool` is `true` **only** with that read-only user; set it
      to `false` if the user has any write privilege.
- [ ] `token` is set (32+ random bytes) and stored only in `config.local.php` /
      `localStorage` — never in the repo, never in a URL you share (`?token=` ends up
      in access logs; prefer the header).
- [ ] `origins` lists explicit `https://` origins; no `'*'` in production.
- [ ] `config.local.php` is git-ignored, `chmod 600`, and denied by
      `server/.htaccess` (verify: `curl -sI https://erp.epal.com.bd/eon/server/config.local.php` → 403).
- [ ] `lib/`, `cron/`, `install/`, `storage/`, `vendor/`, `py/` answer 403 from the
      web; `.git` is hidden (`RedirectMatch 404 /\.git`).
- [ ] HTTPS forced on the site; the Anthropic key is only in `config.local.php`
      (or the environment), rotated if it ever lands in a log.
- [ ] Until the adapter forwards the token (A9), the `/eon/` folder is
      password-protected in hPanel if `token` is left empty.
- [ ] `notify.email_to` is the boss's address only; the webhook URL is HTTPS.
- [ ] `deploy/deploy.env` is `chmod 600` (it holds the webhook secret) and the deploy
      folder answers 403 from the web except `webhook.php` (verify:
      `curl -sI https://eon.gulfrabit.com/deploy/post-deploy.php` → 403, `/deploy/state.json` → 403).
- [ ] The GitHub deploy key for the host is **read-only** (no write access), and the
      branch in `deploy.env` is the one you actually review before pushing.
- [ ] `git pull` updates never overwrite `config.local.php`, `vendor/`, `storage/`
      (all git-ignored) — but re-run `composer install` when `composer.json` changes.

---

## Static / GitHub Pages fallback

`index.html` needs no server: with nothing at `./server/api/` (a static host
returns the PHP files as text or a 404, which the adapter treats as "no server")
it generates the demo dataset in the browser
(`ai-companion/eon-brain/domains/erp/demo-data.js` — same schema as the ERP,
twelve companies), answers from the rule-based brain and speaks with the browser's
voice. The pill reads **Static · demo data · offline brain**.

- GitHub Pages: repository → Settings → Pages → branch `main`, folder `/ (root)`.
  Everything is relative, no CDN (three.js is vendored), so `https://imran-me.github.io/eon/` works as is.
- Any web server pointed at the repo root works the same (`python3 -m http.server 8080` locally).
- Opening `index.html` from `file://` blocks ES-module imports in most browsers — serve it over HTTP.
- To make a static copy **live**, set `window.EON_CONFIG.server` to the Hostinger
  API (B2), add the static origin to `origins`, and set `localStorage.eon_token`
  there. Without that, the static copy is the safe demo that "never dies" —
  what the summit script falls back to if the venue network drops.
