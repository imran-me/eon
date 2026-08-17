<?php

/*
|--------------------------------------------------------------------------
| EON — routes (loaded by Modules\Eon\EonServiceProvider::boot)
|--------------------------------------------------------------------------
| Everything sits under /{prefix} (default /eon):
|
|   GET   /eon                          → EonController@index   the Command Center (index.html + window.EON_CONFIG)
|   ANY   /eon/api/{endpoint}           → EonController@api     health | dataset | ask | brief | memory | actions | py | file  (.php optional)
|   GET   /eon/{ai-companion|app}/{path}→ EonController@asset   browser assets (only when EON_ASSET_URL points here)
|
| Middleware: config('eon.middleware') = ['web', 'auth'] by default — 'web' is
| explicit because loadRoutesFrom() does not add it — plus Spatie's role check for
| config('eon.roles') when spatie/laravel-permission is installed. The controller
| re-checks the roles itself, so the pages stay closed even without Spatie.
|
| Deliberately no closures, no database calls: this file must survive
| `php artisan route:cache` and must not be able to throw while registering.
*/

use Illuminate\Support\Facades\Route;
use Modules\Eon\EonController;

$prefix     = trim((string) config('eon.prefix', 'eon'), '/') ?: 'eon';
$middleware = (array) config('eon.middleware', ['web', 'auth']);
$roles      = array_values(array_filter((array) config('eon.roles', []), 'strlen'));

// Role gate as a fully-qualified class + parameters (no dependency on the 'role'
// alias being registered in bootstrap/app.php). Cache-safe: it is just a string.
if ($roles !== [] && class_exists(\Spatie\Permission\Middleware\RoleMiddleware::class)) {
    $middleware[] = \Spatie\Permission\Middleware\RoleMiddleware::class . ':' . implode('|', $roles);
}

// The API is called by EON's own JavaScript with fetch() and JSON bodies (no
// _token). Laravel's session cookie is SameSite=Lax and the controller enforces a
// same-origin check on every non-GET request, so the CSRF middleware is lifted
// for /eon/api/* only. Both class names are listed so an app upgraded from an
// older skeleton is covered as well; class_exists keeps this inert elsewhere.
$csrf = array_values(array_filter([
    'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
    'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
], 'class_exists'));

Route::prefix($prefix)->name('eon.')->group(function () use ($middleware, $csrf) {

    // 1. the Command Center page
    Route::middleware($middleware)
        ->get('/', [EonController::class, 'index'])
        ->name('index');

    // 2. the API bridge → eon/server/api/{endpoint}.php
    Route::middleware($middleware)
        ->withoutMiddleware($csrf)
        ->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], 'api/{endpoint}', [EonController::class, 'api'])
        ->where('endpoint', '[a-z][a-z0-9\-]*(\.php)?')
        ->name('api');

    // 3. browser assets (JS modules, CSS, three.js) — public, cache-friendly, no session.
    //    Only ai-companion/ and app/ are reachable; server/ never is.
    Route::get('{dir}/{path}', [EonController::class, 'asset'])
        ->where(['dir' => 'ai-companion|app', 'path' => '.+'])
        ->name('asset');
});
