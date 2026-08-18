# Putting the ERP at the front door, with EON walking on it

The address `eon.gulfrabit.com` serves **the ERP, exactly as it is**. EON is an
overlay on top of it and its own panel opens in a second tab.

```
https://eon.gulfrabit.com/           → the ERP (Laravel, erp/public) — pixel for pixel
https://eon.gulfrabit.com/eon/       → EON's panel (brief, decisions, approvals, Ask EON)
https://eon.gulfrabit.com/server/api → EON's API
https://eon.gulfrabit.com/embed/…    → the one script the ERP loads
```

Nothing in the ERP is edited. Not one line. The companion is appended by PHP at
response time (`embed/eon-inject.php`), so the ERP keeps updating from its own
repository and EON keeps riding on it.

Until `erp/` exists, the address serves EON's panel as before — the routing rules
test for `erp/public/index.php` and skip themselves when it is not there.

---

## 1. Put the ERP in `erp/`

Over SSH (`ssh -p 65002 u239665931@145.79.58.223`):

```bash
cd ~/domains/gulfrabit.com/public_html/eon
git clone https://github.com/Epal-It-Solutions/epal_erp_soft.git erp
```

Private repo? Generate a key on the host and add the **public** half to the ERP
repository as a read-only deploy key (GitHub → the ERP repo → Settings → Deploy keys):

```bash
ssh-keygen -t ed25519 -N "" -f ~/.ssh/id_ed25519      # only if you have no key yet
cat ~/.ssh/id_ed25519.pub                              # paste this into GitHub
git clone git@github.com:Epal-It-Solutions/epal_erp_soft.git erp
```

`erp/` is ignored by EON's repository, so EON's deploys never touch it and the ERP
is updated on its own schedule with `git -C erp pull`.

## 2. Install it

```bash
cd ~/domains/gulfrabit.com/public_html/eon/erp
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env` — the important lines:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://eon.gulfrabit.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=…        # the same database EON already reads
DB_USERNAME=…
DB_PASSWORD=…
```

Use **the same database** EON is configured against in `server/config.local.php`, so
the ERP screens and EON's answers are the same numbers.

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
php artisan config:clear && php artisan route:clear && php artisan view:clear
```

> **Never run `php artisan migrate` here.** The database already holds live data; EON's
> whole premise is that it reads what the ERP wrote. Migrations belong to the ERP's own
> deployment, not to this copy.

## 3. Turn on the companion

The next EON deploy writes `.user.ini` for you (post-deploy does it as soon as it sees
`erp/public/index.php`). To do it by hand:

```bash
cd ~/domains/gulfrabit.com/public_html/eon
echo 'auto_append_file = "'$PWD'/embed/eon-inject.php"' > .user.ini
```

PHP re-reads `.user.ini` every 5 minutes by default, so give it that long, or touch a
PHP file to force it. The injector appends **one** script tag, and only to real HTML
pages — JSON, downloads, redirects and AJAX fragments are left exactly as the ERP
produced them.

Alternative if `.user.ini` is ignored on your host: add the same line to `.htaccess` as
`php_value auto_append_file "/home/u239665931/domains/gulfrabit.com/public_html/eon/embed/eon-inject.php"`.

## 4. Check it

```bash
curl -sI https://eon.gulfrabit.com/            | head -1     # 200, served by Laravel
curl -s   https://eon.gulfrabit.com/ | grep eon-embed        # the injected script tag
curl -sI  https://eon.gulfrabit.com/eon/       | head -1     # 200, EON's panel
```

In the browser: the ERP looks exactly as it always did, EON walks in the corner, and the
**EON panel** button (bottom-left) opens the panel in a new tab.

## Tuning the embed

Set options before the script, or as `data-` attributes on the tag:

```html
<script>window.EON_EMBED = { position: 'bottom-right', company: 2, avatar: false };</script>
```

| Option | Default | What it does |
| --- | --- | --- |
| `server` | `<site>/server/api` | where EON's API lives |
| `panel` | `<site>/eon/` | what the button opens |
| `company` | `null` | pin EON to one company's data |
| `button` | `true` | show the panel button |
| `avatar` | `true` | show the walking companion |
| `position` | `bottom-left` | `bottom-left`, `bottom-right`, `top-right` |

## If the API asks for a token

Once `server/config.local.php` has a token (it should, now that a real database is
connected), open the panel once as `https://eon.gulfrabit.com/eon/?token=YOUR_TOKEN`.
It is stored in the browser and stripped from the address, and the companion on the ERP
pages uses the same stored token — same origin, so one visit covers both.
