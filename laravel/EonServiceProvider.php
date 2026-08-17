<?php

namespace Modules\Eon;

use Illuminate\Support\ServiceProvider;

/**
 * EON — Laravel module shim for the Epal ERP.
 *
 * This is the ONLY class the ERP has to know about. It mounts EON's plain-PHP
 * server (eon/server/) and its browser face (index.html, app/, ai-companion/)
 * under /eon inside the ERP, behind the ERP's own session login and roles.
 * EON's code stays where it is (EON_PATH); the ERP is never edited beyond the
 * one line in bootstrap/providers.php and the optional config/eon.php.
 *
 * Wiring:
 *   bootstrap/providers.php   ->  Modules\Eon\EonServiceProvider::class
 *   config/eon.php            ->  keys listed in DEFAULTS (env: EON_*)
 *   routes.php                ->  /eon, /eon/api/{endpoint}, /eon/{ai-companion|app}/{path}
 *   EonController             ->  serves index.html, proxies the API, serves assets
 *
 * Isolation rules (same spirit as the ERP's Wood Art module):
 *   - nothing here can throw at boot: everything is guarded, boot() is wrapped;
 *   - with EON_ENABLED=false (the default) no route is registered at all, so
 *     Route::has('eon.index') is false and every Blade guard stays inert;
 *   - the shim reads EON's files at request time only — a missing or half-copied
 *     EON folder degrades to a 503 JSON on /eon/api/* and a plain message on /eon,
 *     never to an error on any other ERP page.
 */
class EonServiceProvider extends ServiceProvider
{
    /**
     * Static defaults for config('eon.*'). No env() here on purpose: providers run
     * with the config cache, where env() is empty. env() belongs in config/eon.php
     * (see laravel/README.md for the file). Anything missing there falls back to these.
     */
    public const DEFAULTS = [
        'enabled'      => false,                       // EON_ENABLED     — master switch; false = shim registers nothing
        'path'         => null,                        // EON_PATH        — EON repo root (holds server/, ai-companion/, app/, index.html); null = <laravel base>/../eon
        'token'        => '',                          // EON_TOKEN       — shared secret for server/config.local.php 'token' (sent server-side, never to the browser unless expose_token)
        'server_url'   => '',                          // EON_SERVER_URL  — base URL of a standalone EON server, e.g. https://erp.epal.com.bd/eon-server/api (http mode / fallback)
        'mode'         => 'include',                   // EON_MODE        — include (run server/api/*.php in-process, primary) | http (proxy to server_url)
        'prefix'       => 'eon',                       // EON_PREFIX      — URL prefix; /eon, /eon/api/…, /eon/ai-companion/…
        'asset_url'    => '/eon',                      // EON_ASSET_URL   — where ai-companion/ and app/ are served from; '/eon' = through the controller, '/eon-assets' after vendor:publish --tag=eon-assets
        'middleware'   => ['web', 'auth'],             // EON_MIDDLEWARE  — comma list; 'web' is required (loadRoutesFrom() does not add it), add 'two_factor' if wanted
        'roles'        => ['super admin', 'admin'],    // EON_ROLES       — comma list of Spatie role names allowed in; empty = any authenticated user
        'company'      => null,                        // EON_COMPANY     — default scope: null = whole group, 'user' = the user's home company, or a company id
        'expose_token' => false,                       // EON_EXPOSE_TOKEN — also put the token into window.EON_CONFIG (only needed when the page talks to a standalone server directly)
        'timeout'      => 90,                          // EON_TIMEOUT     — seconds for the http-mode proxy (ask.php can take a while with the language model)
    ];

    public function register(): void
    {
        try {
            $config  = $this->app['config'];
            $current = $config->get('eon');
            $merged  = array_replace(self::DEFAULTS, is_array($current) ? $current : []);

            // Lists may arrive as comma strings from .env — normalise once here.
            foreach (['middleware', 'roles'] as $list) {
                if (is_string($merged[$list])) {
                    $merged[$list] = array_values(array_filter(array_map('trim', explode(',', $merged[$list])), 'strlen'));
                }
                if (!is_array($merged[$list])) {
                    $merged[$list] = self::DEFAULTS[$list];
                }
            }
            if (!in_array('web', $merged['middleware'], true)) {
                array_unshift($merged['middleware'], 'web');
            }
            if ($merged['path'] === null || $merged['path'] === '') {
                $merged['path'] = dirname($this->app->basePath()) . DIRECTORY_SEPARATOR . 'eon';
            }
            $merged['prefix']    = trim((string) $merged['prefix'], '/') ?: 'eon';
            $merged['asset_url'] = rtrim((string) ($merged['asset_url'] ?: '/' . $merged['prefix']), '/');
            $merged['mode']      = in_array($merged['mode'], ['include', 'http'], true) ? $merged['mode'] : 'include';

            $config->set('eon', $merged);
        } catch (\Throwable $e) {
            // Never let EON's configuration break the ERP's container build.
            $this->quiet('EON provider register skipped: ' . $e->getMessage());
        }
    }

    public function boot(): void
    {
        try {
            if (!$this->app['config']->get('eon.enabled')) {
                return;                                     // switched off: no routes, no publish, no trace
            }

            // Routes are plain (controller class + string middleware), so `php artisan
            // optimize` / route:cache serialises them without complaint. When routes are
            // cached, loadRoutesFrom() skips the file by itself.
            $this->loadRoutesFrom(__DIR__ . '/routes.php');

            // Optional speed step: copy the browser assets under public/eon-assets so
            // Apache/LiteSpeed serves them directly instead of the controller.
            //   php artisan vendor:publish --tag=eon-assets --force
            // then set EON_ASSET_URL=/eon-assets. NOTE: never publish to public/eon —
            // a real public/eon/ directory would shadow the /eon route in .htaccess.
            if ($this->app->runningInConsole()) {
                $root = EonController::root();
                if ($root !== '' && is_dir($root . '/ai-companion') && is_dir($root . '/app')) {
                    $this->publishes([
                        $root . '/ai-companion' => public_path('eon-assets/ai-companion'),
                        $root . '/app'          => public_path('eon-assets/app'),
                    ], 'eon-assets');
                }
            }
        } catch (\Throwable $e) {
            $this->quiet('EON provider boot skipped: ' . $e->getMessage());
        }
    }

    /** Log without ever throwing (the logger itself may not be ready during register()). */
    private function quiet(string $message): void
    {
        try {
            if ($this->app->bound('log')) {
                $this->app['log']->warning($message);
            } else {
                error_log($message);
            }
        } catch (\Throwable) {
            // nothing else to do — stay silent by design
        }
    }
}
