<?php

namespace Modules\Eon;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http as HttpClient;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * EON — the bridge between the ERP (Laravel) and EON's plain-PHP server.
 *
 *   index()  GET  /eon                 serves eon/index.html with window.EON_CONFIG injected
 *   api()    ANY  /eon/api/{endpoint}  runs eon/server/api/{endpoint}.php in-process ("include" mode, primary)
 *                                      or proxies it to EON_SERVER_URL ("http" mode / fallback)
 *   asset()  GET  /eon/{dir}/{path}    serves ai-companion/ and app/ files with correct MIME types
 *
 * The authenticated ERP user is passed to EON as the boss context — name, e-mail,
 * company_id, user id, roles — through a small bridge EON's Brain can read:
 *   include mode:  $_SERVER['EON_BOSS_NAME'|'EON_BOSS_EMAIL'|'EON_BOSS_COMPANY_ID'|'EON_BOSS_USER_ID'|'EON_BOSS_ROLES'],
 *                  $_SERVER['EON_HOST'] = 'laravel', and $GLOBALS['EON_CONTEXT'] (array)
 *   http mode:     X-EON-Boss-Name / -Email / -Company-Id / -User-Id / -Roles headers (rawurlencoded UTF-8),
 *                  X-EON-Host: laravel
 *
 * Nothing in here touches ERP tables or ERP code. Every failure is contained to a
 * /eon URL and answered as JSON (api) or a plain page (index) — never thrown at
 * something that renders on another ERP page.
 */
class EonController extends Controller
{
    /** File extensions the asset route will serve, with the MIME type browsers need (ES modules require a JavaScript type). */
    private const MIME = [
        'js' => 'text/javascript; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8', 'css' => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8', 'map' => 'application/json; charset=utf-8', 'html' => 'text/html; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'ttf' => 'font/ttf', 'otf' => 'font/otf', 'glb' => 'model/gltf-binary', 'gltf' => 'model/gltf+json', 'wasm' => 'application/wasm',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
    ];

    /** Global class names EON's server declares; if any is already taken in this process the in-process include cannot run. */
    private const EON_CLASSES = ['Config', 'Log', 'Http', 'Db', 'Dataset', 'Erp', 'Analytics', 'Memory', 'Tools', 'Brain', 'Notify', 'Py'];

    /* ------------------------------------------------------------------ paths */

    /** EON repo root (config eon.path), normalised; '' when unset. */
    public static function root(): string
    {
        $path = (string) config('eon.path', '');
        if ($path === '') {
            return '';
        }
        $real = realpath($path);
        return rtrim(str_replace('\\', '/', $real !== false ? $real : $path), '/');
    }

    private static function assetBase(): string
    {
        $base = (string) config('eon.asset_url', '');
        return rtrim($base !== '' ? $base : '/' . trim((string) config('eon.prefix', 'eon'), '/'), '/');
    }

    private static function apiBase(): string
    {
        return '/' . trim((string) config('eon.prefix', 'eon'), '/') . '/api';
    }

    /* ------------------------------------------------------------------ /eon */

    /** The Command Center: index.html with the asset paths made absolute and window.EON_CONFIG injected. */
    public function index(Request $request): SymfonyResponse
    {
        $this->authorizeBoss($request);

        $root = self::root();
        $file = $root . '/index.html';
        if ($root === '' || !is_file($file)) {
            return response($this->missingPage('EON is enabled but its files were not found at EON_PATH (' . ($root ?: 'unset') . '). '
                . 'Point EON_PATH at the eon repository root — the folder holding index.html, app/, ai-companion/ and server/.'), 503)
                ->header('Content-Type', 'text/html; charset=utf-8');
        }

        $html = (string) @file_get_contents($file);
        if ($html === '') {
            return response($this->missingPage('EON index.html is empty or unreadable.'), 503)->header('Content-Type', 'text/html; charset=utf-8');
        }

        // ./ai-companion/… and ./app/… → absolute (asset route or the published copy)
        $assets = self::assetBase();
        $html   = str_replace(['"./', "'./"], ['"' . $assets . '/', "'" . $assets . '/'], $html);

        // window.EON_CONFIG — read by ai-companion/adapters/erp-adapter.js before it boots
        $boss   = $this->bossContext($request);
        $config = [
            'server'  => self::apiBase(),
            'company' => $this->defaultCompany($boss),
            'token'   => config('eon.expose_token') ? (string) config('eon.token', '') : null,
            'demo'    => false,
            'owner'   => ['name' => $boss['name'], 'email' => $boss['email'], 'company_id' => $boss['company_id']],
            'host'    => 'laravel',
        ];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $tag  = "\n  <script>window.EON_CONFIG = Object.assign({}, window.EON_CONFIG || {}, " . $json . ");</script>\n";
        $html = str_contains($html, '</head>') ? str_replace('</head>', $tag . '</head>', $html) : $tag . $html;

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'no-store');
    }

    /* ------------------------------------------------------------------ /eon/api/{endpoint} */

    /** Bridge to eon/server/api/{endpoint}.php — include mode (primary) or http proxy. */
    public function api(Request $request, string $endpoint): SymfonyResponse
    {
        $this->authorizeBoss($request);

        $name = strtolower(preg_replace('/\.php$/i', '', $endpoint) ?? '');
        if ($name === '' || !preg_match('/^[a-z][a-z0-9\-]*$/', $name)) {
            return $this->json(['ok' => false, 'error' => 'unknown endpoint'], 404);
        }
        if ($this->crossSite($request)) {
            return $this->json(['ok' => false, 'error' => 'cross-site request refused'], 403);
        }

        $token = (string) config('eon.token', '');
        $boss  = $this->bossContext($request);
        $mode  = (string) config('eon.mode', 'include');
        $root  = self::root();
        $file  = $root . '/server/api/' . $name . '.php';

        if ($mode === 'include' && $root !== '' && is_file($file) && is_file($root . '/server/bootstrap.php')) {
            $clash = $this->classClash();
            if ($clash === null) {
                return $this->runInProcess($request, $file, $token, $boss);
            }
            // A global class EON needs is already declared in this request (a facade
            // alias got materialised earlier). Fall through to the http proxy if one is
            // configured; otherwise say so plainly.
            if ((string) config('eon.server_url', '') === '') {
                return $this->json(['ok' => false, 'error' => "EON include mode blocked: class '$clash' already exists in this request. Set EON_SERVER_URL (http mode) or EON_MODE=http."], 503);
            }
        } elseif ($mode === 'include' && (string) config('eon.server_url', '') === '') {
            return $this->json(['ok' => false, 'error' => is_file($root . '/server/bootstrap.php')
                ? "no such endpoint: $name"
                : 'EON server not found at EON_PATH (' . ($root ?: 'unset') . ')/server — set EON_PATH, or EON_SERVER_URL for http mode.'], is_file($root . '/server/bootstrap.php') ? 404 : 503);
        }

        return $this->proxy($request, $name, $token, $boss);
    }

    /**
     * Include mode. The endpoint is EON's own script: it reads $_GET / php://input, sends
     * its headers and JSON through EON's Http class and then calls exit — exactly as it
     * does when served standalone. So this method normally never returns: PHP ends the
     * request when the endpoint is done (Laravel's after-middleware and session save
     * are skipped for these JSON calls, which is intended — nothing of the ERP's is
     * modified by an EON request). If the script returns instead of exiting, whatever it
     * printed is wrapped into a normal Laravel response.
     */
    private function runInProcess(Request $request, string $file, string $token, array $boss): SymfonyResponse
    {
        // 1. authentication towards EON's own Http::auth(): the shared secret is added
        //    server-side, so the browser never needs it. EON_TOKEN is also exported so
        //    that EON's Config::load() (getenv('EON_TOKEN')) and this bridge always agree.
        if ($token !== '') {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
            unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            @putenv('EON_TOKEN=' . $token);
        }

        // 2. the boss context bridge (see class docblock)
        $_SERVER['EON_HOST'] = 'laravel';
        foreach (['name', 'email', 'company_id', 'user_id', 'roles'] as $k) {
            $_SERVER['EON_BOSS_' . strtoupper($k)] = (string) ($boss[$k] ?? '');
        }
        $GLOBALS['EON_CONTEXT'] = [
            'host'    => 'laravel',
            'boss'    => $boss,
            'laravel' => ['app' => (string) config('app.name', ''), 'url' => (string) config('app.url', ''), 'locale' => app()->getLocale()],
        ];

        // 3. behave like plain PHP: Laravel turns notices/warnings into ErrorException,
        //    which EON's Http::run() would answer as a 500 where standalone it just logs.
        //    Log to the PHP error log and carry on. (This request ends inside the include.)
        set_error_handler(static function (int $no, string $msg, string $f = '', int $l = 0): bool {
            if (!(error_reporting() & $no)) {
                return true;
            }
            error_log("EON: $msg in $f:$l");
            return true;
        });
        if (function_exists('set_time_limit')) {
            @set_time_limit(max(30, (int) config('eon.timeout', 90)));
        }

        // 4. run it. Http::json() → exit ends the request here in the normal case.
        $level = ob_get_level();
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            restore_error_handler();
            return $this->json(['ok' => false, 'error' => 'EON endpoint failed: ' . $e->getMessage()], 500);
        }
        $out = '';
        while (ob_get_level() > $level) {
            $out = ob_get_clean() . $out;
        }
        restore_error_handler();

        // 5. the script returned without exit — hand its output back as a normal response
        $status = http_response_code();
        $status = is_int($status) && $status >= 100 ? $status : 200;
        $type   = 'application/json; charset=utf-8';
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $type = trim(substr($h, 13));
            }
        }
        return response($out !== '' ? $out : '{"ok":false,"error":"empty response from EON endpoint"}', $status)
            ->header('Content-Type', $type)
            ->header('Cache-Control', 'no-store');
    }

    /** http mode: forward the call to a standalone EON server (EON_SERVER_URL = …/server/api). */
    private function proxy(Request $request, string $name, string $token, array $boss): SymfonyResponse
    {
        $base = rtrim((string) config('eon.server_url', ''), '/');
        if ($base === '') {
            return $this->json(['ok' => false, 'error' => 'EON_SERVER_URL is not set — nothing to proxy to.'], 503);
        }
        $url = $base . '/' . $name . '.php' . (($qs = $request->getQueryString()) ? '?' . $qs : '');

        $headers = ['Accept' => 'application/json', 'X-EON-Host' => 'laravel'];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        foreach (['name' => 'Name', 'email' => 'Email', 'company_id' => 'Company-Id', 'user_id' => 'User-Id', 'roles' => 'Roles'] as $k => $h) {
            if (($boss[$k] ?? '') !== '' && $boss[$k] !== null) {
                $headers['X-EON-Boss-' . $h] = rawurlencode((string) $boss[$k]);
            }
        }

        try {
            $pending = HttpClient::withHeaders($headers)
                ->timeout(max(5, (int) config('eon.timeout', 90)))
                ->withOptions(['http_errors' => false, 'allow_redirects' => false]);
            $body = (string) $request->getContent();
            $options = [];
            if ($body !== '' && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                $pending = $pending->withBody($body, (string) ($request->header('Content-Type') ?: 'application/json'));
                $options['body'] = $body;
            }
            $res = $pending->send($request->method(), $url, $options);

            $out = [
                'Content-Type'  => $res->header('Content-Type') ?: 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ];
            if (($cd = $res->header('Content-Disposition')) !== '') {
                $out['Content-Disposition'] = $cd;   // file.php downloads
            }
            return response($res->body(), $res->status(), $out);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => 'EON server unreachable: ' . $e->getMessage()], 502);
        }
    }

    /* ------------------------------------------------------------------ /eon/{ai-companion|app}/{path} */

    /** Static files of the face. Whitelisted folders and extensions only, path-traversal safe, cacheable. */
    public function asset(Request $request, string $dir, string $path): SymfonyResponse
    {
        $root = self::root();
        if ($root === '' || !in_array($dir, ['ai-companion', 'app'], true)) {
            abort(404);
        }
        $clean = str_replace('\\', '/', $path);
        if ($clean === '' || str_contains($clean, "\0") || str_contains($clean, '../') || str_starts_with($clean, '/') || preg_match('#(^|/)\.#', $clean)) {
            abort(404);
        }
        $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
        if (!isset(self::MIME[$ext])) {
            abort(404);
        }
        $base = $root . '/' . $dir;
        $file = realpath($base . '/' . $clean);
        if ($file === false || !is_file($file)) {
            abort(404);
        }
        $file = str_replace('\\', '/', $file);
        $realBase = str_replace('\\', '/', (string) realpath($base));
        if ($realBase === '' || !str_starts_with($file, $realBase . '/')) {
            abort(404);
        }

        $response = response()->file($file, [
            'Content-Type'  => self::MIME[$ext],
            'Cache-Control' => 'public, max-age=3600',
        ]);
        try {
            $response->setAutoLastModified()->setAutoEtag();
            $response->isNotModified($request);
        } catch (\Throwable) {
            // caching hints are optional
        }
        return $response;
    }

    /* ------------------------------------------------------------------ helpers */

    /** Second gate after the route middleware: the user must hold one of config('eon.roles') (empty list = any logged-in user). */
    private function authorizeBoss(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Please sign in to the ERP first.');
        }
        $roles = array_values(array_filter((array) config('eon.roles', []), 'strlen'));
        if ($roles === []) {
            return;
        }
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
                return;
            }
        } catch (\Throwable) {
            // fall through to 403
        }
        abort(403, 'EON is available to ' . implode(' / ', $roles) . ' only.');
    }

    /** Who is asking — the ERP user becomes EON's boss for this request. */
    private function bossContext(Request $request): array
    {
        $user  = $request->user();
        $roles = [];
        try {
            if ($user && method_exists($user, 'getRoleNames')) {
                $roles = array_values(array_map('strval', $user->getRoleNames()->all()));
            }
        } catch (\Throwable) {
            $roles = [];
        }
        $companyId = null;
        try {
            $companyId = $user->company_id ?? null;
        } catch (\Throwable) {
            $companyId = null;
        }
        return [
            'user_id'    => $user ? $user->getAuthIdentifier() : null,
            'name'       => (string) ($user->name ?? ''),
            'email'      => (string) ($user->email ?? ''),
            'company_id' => $companyId !== null && $companyId !== '' ? (int) $companyId : null,
            'roles'      => implode(',', $roles),
        ];
    }

    /** config('eon.company'): null/'' = whole group, 'user' = the user's home company, or a fixed company id. */
    private function defaultCompany(array $boss): ?int
    {
        $c = config('eon.company');
        if ($c === null || $c === '' || $c === 'group' || $c === 'all') {
            return null;
        }
        if ($c === 'user') {
            return $boss['company_id'];
        }
        return is_numeric($c) ? (int) $c : null;
    }

    /** True when a state-changing request did not come from this site (CSRF is lifted for /eon/api). */
    private function crossSite(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }
        $site = $request->headers->get('Sec-Fetch-Site');
        if ($site !== null && $site !== '') {
            return !in_array(strtolower($site), ['same-origin', 'none'], true);
        }
        $from = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (!$from) {
            return false;                       // non-browser client; the session cookie is still required
        }
        $host = (string) parse_url($from, PHP_URL_HOST);
        return $host === '' || strcasecmp($host, $request->getHost()) !== 0;
    }

    /** Name of the first EON global class that already exists in this process, or null when the include is safe. */
    private function classClash(): ?string
    {
        foreach (self::EON_CLASSES as $class) {
            if (class_exists($class, false)) {      // autoload=false: must not materialise a facade alias ourselves
                return $class;
            }
        }
        return null;
    }

    private function json(array $data, int $status = 200): Response
    {
        return response(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status, [
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function missingPage(string $message): string
    {
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>EON</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#0b0f1a;color:#e6e9f2;display:grid;place-items:center;height:100vh;margin:0}'
            . 'main{max-width:38rem;padding:2rem;border:1px solid #2a3350;border-radius:1rem;background:#121829}h1{margin:0 0 .5rem;font-size:1.3rem}p{line-height:1.5;color:#b8c0d8}</style></head>'
            . '<body><main><h1>EON is not ready</h1><p>' . $safe . '</p></main></body></html>';
    }
}
