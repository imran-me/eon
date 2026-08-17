# EON inside the ERP — Laravel module shim

This folder lets EON be mounted **inside the Epal ERP** (`erp.epal.com.bd`,
Laravel 12 / PHP 8.2 / MySQL on Hostinger) as a module at `/eon`, behind the
ERP's own login and roles — **without editing any ERP code**. The ERP touches
EON in exactly one place (a line in `bootstrap/providers.php`) plus one new
config file; removing those detaches everything.

EON's own code is not moved or changed: the plain-PHP server (`eon/server/`),
the Command Center (`eon/index.html`, `eon/app/`) and the companion
(`eon/ai-companion/`) stay in the EON checkout, which the shim finds through
`EON_PATH`.

Maintainer: Md Imran Hossain — Epal IT Solutions.

---

## 1. Files

| File | Copied to (in the ERP) | Role |
| --- | --- | --- |
| `EonServiceProvider.php` | `Modules/Eon/EonServiceProvider.php` | the single wiring point: config defaults, routes, optional asset publish |
| `routes.php` | `Modules/Eon/routes.php` | `/eon`, `/eon/api/{endpoint}`, `/eon/{ai-companion\|app}/{path}` |
| `EonController.php` | `Modules/Eon/EonController.php` | serves `index.html`, bridges the API to `server/api/*.php`, serves assets |
| `README.md` | — | this document (contains the `config/eon.php` file and the Blade snippet) |

Namespace `Modules\Eon` — it rides on the PSR-4 entry the ERP already has
(`"Modules\\": "Modules/"` in `composer.json`), so **no `composer.json` edit is
needed**, only an autoload dump.

## 2. Two ways to deploy EON on the ERP host — pick one per URL

| | A. plain folder (`public/eon/`) | B. this shim (`/eon` route) |
| --- | --- | --- |
| Who serves it | Apache/LiteSpeed directly, EON's own `.htaccess` | Laravel |
| Login | EON's shared token (`server/config.local.php` → `token`) | ERP session + roles (`super admin`, `admin`) |
| ERP code touched | none | `bootstrap/providers.php` + `config/eon.php` |
| Boss identity | fixed in `config.local.php` (`boss`) | the logged-in ERP user, per request |
| Cron | hPanel cron on `server/cron/*.php` | same — cron never goes through Laravel |

They are exclusive on the same URL: a real `public/eon/` directory makes the
front-controller rewrite skip Laravel for `/eon`, so with the shim the EON
checkout must live **outside `public/`** (default: a sibling of the Laravel
root, `../eon`), or use another `EON_PREFIX`.

## 3. How it works

```
browser ── /eon ───────────────► EonController@index
                                   reads  EON_PATH/index.html
                                   ./ai-companion/…, ./app/… → EON_ASSET_URL/…  (absolute)
                                   injects <script>window.EON_CONFIG = {server:'/eon/api', company, token, owner, host:'laravel'}</script>
browser ── /eon/api/health.php ► EonController@api
           /eon/api/ask (POST)     include mode (primary):  require EON_PATH/server/api/<endpoint>.php
                                                             ├─ Authorization: Bearer EON_TOKEN set server-side (Http::auth passes)
                                                             ├─ boss context → $_SERVER['EON_BOSS_*'], $GLOBALS['EON_CONTEXT']
                                                             └─ the endpoint answers and exits, exactly as standalone
                                   http mode / fallback:      proxy to EON_SERVER_URL/<endpoint>.php (+ X-EON-Boss-* headers)
browser ── /eon/ai-companion/js/boot.js ► EonController@asset   (or Apache, after vendor:publish --tag=eon-assets)
cron    ── php EON_PATH/server/cron/morning-brief.php            (unchanged, hPanel cron)
```

**Include mode** runs EON's endpoint script in the same PHP process as the
Laravel request. The script is EON's own: it reads `$_GET` / `php://input`,
sends its headers and JSON through EON's `Http` class and calls `exit`, so the
request ends inside the endpoint (Laravel's after-middleware and session save do
not run for these JSON calls — intended; an EON request never modifies anything
of the ERP's). Two safety checks before the include:

- **name clash** — EON's server declares global classes (`Config`, `Log`,
  `Http`, `Db`, …). Laravel has facade *aliases* with some of the same names;
  they only exist once something in the request has referenced `\Config::` etc.
  The controller checks `class_exists($name, false)` for every EON class first;
  if one is already taken it falls back to http mode (when `EON_SERVER_URL` is
  set) or answers a clear 503 JSON — never a fatal.
- **same origin** — CSRF is lifted for `/eon/api/*` (EON's JavaScript uses
  `fetch()` with JSON bodies and no `_token`), so every non-GET request must
  come from the ERP's own origin (`Sec-Fetch-Site` / `Origin` / `Referer`), on
  top of the `SameSite=Lax` session cookie.

**http mode** (`EON_MODE=http`, or automatic fallback) proxies to a standalone
EON server with Laravel's HTTP client — method, query string, raw body,
`Authorization: Bearer EON_TOKEN`, and the boss as `X-EON-Boss-*` headers.
Returns a normal Laravel response (status, content type, `Content-Disposition`
for `file.php` downloads).

**Assets.** By default `/eon/ai-companion/*` and `/eon/app/*` are served by the
controller (public, correct MIME types — ES modules need `text/javascript` —
`Cache-Control: public, max-age=3600`, ETag/Last-Modified). Only those two
folders are reachable; `server/` never is; `..` and dot-files are refused. For
speed on the summit day publish them and let Apache serve:

```
php artisan vendor:publish --tag=eon-assets --force      # → public/eon-assets/{ai-companion,app}
EON_ASSET_URL=/eon-assets                                # in .env, then php artisan optimize:clear
```

(Never publish to `public/eon` — see §2.)

**Roles.** Routes carry `web`, `auth` and — when `spatie/laravel-permission`
is present — `RoleMiddleware:super admin|admin` (fully-qualified class, so it
does not depend on the `role` alias and survives `route:cache`). The controller
re-checks `hasAnyRole(config('eon.roles'))` itself, so the pages stay closed
even if Spatie were missing. `EON_ROLES=` (empty) opens EON to any logged-in user.

## 4. Install

> **Server steps — these must be run explicitly, on the server, in this order.
> The shim is not live until they are done; until then `Route::has('eon.index')`
> is simply false and the ERP renders exactly as before.**

```bash
# 0. EON checkout next to the Laravel root (outside public/), e.g.
#    /home/USER/domains/erp.epal.com.bd/eon        ← EON_PATH
#    /home/USER/domains/erp.epal.com.bd/public_html ← Laravel base_path()
cd /home/USER/domains/erp.epal.com.bd
git clone https://github.com/imran-me/eon.git eon
cd eon/server && cp config.example.php config.local.php   # db (read-only user), token, anthropic api_key
composer install                                            # anthropic-ai/sdk
mysql -u USER -p DBNAME < install/schema.sql                # EON's own eon_* tables
python3 -m pip install -r py/requirements.txt               # optional (xlsx, numpy)

# 1. copy the shim into the ERP
cd /home/USER/domains/erp.epal.com.bd/public_html          # Laravel root
mkdir -p Modules/Eon
cp ../eon/laravel/EonServiceProvider.php ../eon/laravel/EonController.php ../eon/laravel/routes.php Modules/Eon/

# 2. config/eon.php  (content in §5)  +  .env keys (EON_ENABLED=true, EON_PATH, EON_TOKEN, …)

# 3. register the provider — add ONE line to bootstrap/providers.php:
#      Modules\Eon\EonServiceProvider::class,

# 4. REQUIRED after adding classes under Modules/ (the ERP uses an optimised classmap):
composer dump-autoload -o
php artisan optimize:clear
php artisan optimize

# 5. proof step — must list eon.index, eon.api, eon.asset:
php artisan route:list --name=eon
```

If `route:list` prints nothing for `eon`, the module is **not** loaded: either
`EON_ENABLED` is not true, or the autoloader was not dumped (Laravel drops a
provider it cannot autoload silently). The `Route::has()` guards keep every
other page intact meanwhile.

Then open `https://erp.epal.com.bd/eon` logged in as super admin / admin, and
`https://erp.epal.com.bd/eon/api/health.php` should return EON's health JSON.
Optionally set `EON_TOKEN` in `.env` (any long random string) — the shim adds it
server-side, and also exports it as `EON_TOKEN` for EON's `Config::load()`, so
`server/config.local.php` and the ERP always agree.

## 5. `config/eon.php`

Create this file in the ERP. `env()` belongs here (config cache); everything
missing falls back to `EonServiceProvider::DEFAULTS`.

```php
<?php

return [
    // master switch — false: the shim registers nothing at all
    'enabled'      => (bool) env('EON_ENABLED', false),

    // EON repo root (holds index.html, app/, ai-companion/, server/). null = <laravel base>/../eon
    'path'         => env('EON_PATH'),

    // shared secret matching server/config.local.php 'token' (or leave both empty for open mode).
    // Sent server-side on every bridged call; the browser never needs it.
    'token'        => env('EON_TOKEN', ''),

    // standalone EON server base URL, e.g. https://erp.epal.com.bd/eon-server/api — used in http mode
    // and as the automatic fallback when include mode cannot run
    'server_url'   => env('EON_SERVER_URL', ''),

    // include (run server/api/*.php in-process — primary) | http (proxy to server_url)
    'mode'         => env('EON_MODE', 'include'),

    // URL prefix: /eon, /eon/api/…, /eon/ai-companion/…
    'prefix'       => env('EON_PREFIX', 'eon'),

    // where ai-companion/ and app/ are served from: '/eon' (controller) or '/eon-assets' after vendor:publish
    'asset_url'    => env('EON_ASSET_URL', '/eon'),

    // route middleware; 'web' is required (loadRoutesFrom() does not add it). Add two_factor if the ERP wants it.
    'middleware'   => explode(',', env('EON_MIDDLEWARE', 'web,auth')),

    // Spatie role names allowed in; empty string = any authenticated user
    'roles'        => array_values(array_filter(array_map('trim', explode(',', env('EON_ROLES', 'super admin,admin'))))),

    // default scope: null = whole group, 'user' = the user's home company, or a company id
    'company'      => env('EON_COMPANY'),

    // also put the token into window.EON_CONFIG (only when the page must talk to a standalone server directly)
    'expose_token' => (bool) env('EON_EXPOSE_TOKEN', false),

    // seconds for the http-mode proxy / in-process time limit (ask.php with the language model can be slow)
    'timeout'      => (int) env('EON_TIMEOUT', 90),
];
```

`.env` (Hostinger hPanel → the ERP's `.env`):

```
EON_ENABLED=true
EON_PATH=/home/USER/domains/erp.epal.com.bd/eon
EON_TOKEN=change-me-to-a-long-random-string
EON_SERVER_URL=
EON_MODE=include
EON_ASSET_URL=/eon
EON_ROLES="super admin,admin"
EON_COMPANY=
```

Any change to `.env` or `config/eon.php` needs `php artisan optimize:clear`
(and `php artisan optimize` again) — the ERP runs with cached config and routes.

## 6. Blade snippet — the companion inside the ERP layout

Same tags as `eon/index.html`, absolute paths, fully guarded: with EON disabled,
missing, or half-deployed the layout renders **byte-identical** to before.
Paste at the end of `<body>` in the shared layout (or save as
`resources/views/eon/companion.blade.php` and `@includeIf('eon.companion')`).

```blade
{{-- EON companion — inert unless the module is enabled and its routes exist --}}
@if (config('eon.enabled') && Route::has('eon.index') && Route::has('eon.api') && auth()->check())
  @php
    $eonAssets = rtrim((string) config('eon.asset_url', '/eon'), '/');
    $eonUser   = auth()->user();
    $eonScope  = config('eon.company');
    $eonConfig = [
      'server'  => rtrim(route('eon.index', [], false), '/') . '/api',
      'company' => $eonScope === 'user' ? ($eonUser->company_id ?? null) : (is_numeric($eonScope) ? (int) $eonScope : null),
      'token'   => config('eon.expose_token') ? (string) config('eon.token', '') : null,
      'demo'    => false,
      'owner'   => ['name' => $eonUser->name ?? '', 'email' => $eonUser->email ?? '', 'company_id' => $eonUser->company_id ?? null],
      'host'    => 'laravel',
    ];
  @endphp
  <link href="{{ $eonAssets }}/ai-companion/css/companion.css" rel="stylesheet">
  <link href="{{ $eonAssets }}/ai-companion/css/home.css" rel="stylesheet">
  <link href="{{ $eonAssets }}/ai-companion/css/animations.css" rel="stylesheet">
  {{-- 1. the ERP adapter (classic): server detection, dataset, brain config --}}
  <script>window.EON_CONFIG = Object.assign({}, window.EON_CONFIG || {}, @json($eonConfig));</script>
  <script src="{{ $eonAssets }}/ai-companion/adapters/erp-adapter.js"></script>
  {{-- 2. the companion + brain + ERP domain (modules). One import map per page. --}}
  <script type="importmap">{ "imports": { "three": "{{ $eonAssets }}/ai-companion/vendor/three.module.js" } }</script>
  <script type="module" src="{{ $eonAssets }}/ai-companion/js/boot.js"></script>
  <script type="module" src="{{ $eonAssets }}/ai-companion/eon-brain/voice.js"></script>
  {{-- 3. only the Command Center page (/eon) loads app/eon-app.js — not the ERP layout --}}
@endif
```

Notes: `Route::has()` first, then `route()` — never the other way round. The
import map must appear before any module script and a page may carry only one
(if the ERP layout ever adds its own, merge the `three` entry into it).

## 7. The boss-context bridge (contract for `server/lib/Brain.php`)

The shim passes the authenticated ERP user to EON on every bridged call. Brain
does not read it yet; when it does, this is the contract:

| Where | Include mode | Http mode |
| --- | --- | --- |
| marker | `$_SERVER['EON_HOST'] === 'laravel'` | header `X-EON-Host: laravel` |
| name | `$_SERVER['EON_BOSS_NAME']` | `X-EON-Boss-Name` (rawurlencoded UTF-8) |
| e-mail | `$_SERVER['EON_BOSS_EMAIL']` | `X-EON-Boss-Email` |
| home company | `$_SERVER['EON_BOSS_COMPANY_ID']` (`''` = none) | `X-EON-Boss-Company-Id` |
| user id | `$_SERVER['EON_BOSS_USER_ID']` | `X-EON-Boss-User-Id` |
| roles | `$_SERVER['EON_BOSS_ROLES']` (comma list) | `X-EON-Boss-Roles` |
| everything | `$GLOBALS['EON_CONTEXT'] = ['host' => 'laravel', 'boss' => [...], 'laravel' => [app, url, locale]]` | — |

Suggested reading in EON (later, in `Config::load()` or `Brain::ask()`):

```php
$name = $_SERVER['EON_BOSS_NAME'] ?? rawurldecode($_SERVER['HTTP_X_EON_BOSS_NAME'] ?? '');
if ($name !== '') { /* override Config 'boss' name/email/company_id for this request */ }
```

`window.EON_CONFIG.owner` carries the same name/e-mail/company to the browser
brain (`erp-adapter.js` merges it into `EON_OWNER`).

## 8. Isolation guarantees (why this cannot take the ERP down)

- `EON_ENABLED=false` (default) → `boot()` returns before `loadRoutesFrom`; no
  route, no publish, no side effect. `Route::has('eon.index')` is false.
- `register()` and `boot()` are wrapped in `try/catch (\Throwable)` and log a
  warning instead of throwing.
- Routes are plain strings + controller class: no closures, no DB at
  registration → `php artisan optimize` / `route:cache` serialise them.
- Every failure at request time (EON folder missing, endpoint missing, class
  clash, proxy down) is answered on a `/eon` URL as JSON or a plain page. Other
  ERP pages never execute shim code, and the Blade snippet is guarded by
  `config('eon.enabled') && Route::has(...)`.
- The shim reads only `EON_PATH/index.html`, `server/api/*.php` (through
  `server/bootstrap.php`), `ai-companion/**` and `app/**`. It writes nothing.
- Uninstall: remove the provider line, delete `Modules/Eon/`, `config/eon.php`,
  the `EON_*` env keys, then `composer dump-autoload -o && php artisan
  optimize:clear && php artisan optimize`.

## 9. Known limits

- Include mode ends the PHP request inside EON's endpoint (`exit`). Fine on
  Apache/LiteSpeed/PHP-FPM as used by Hostinger; **not** for Laravel Octane
  (long-lived workers) — use `EON_MODE=http` there.
- Unauthenticated `/eon/api/*` calls get the ERP's normal redirect to login
  (302), not JSON; the browser adapter treats that as "no server" and falls back
  to the demo dataset. Log in to the ERP first.
- `file.php` downloads and `py.php` (Python analytics) work in both modes; in
  include mode they need `python3` reachable from the web PHP process, as in the
  standalone deployment.
