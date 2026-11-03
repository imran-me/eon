<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\EonAi\Http\Controllers\EonAiController;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| EON AI — route spine
|--------------------------------------------------------------------------
| Same shape as Modules/EON/routes/web.php: mirrors the host's /{role} panel
| group, adds nothing to routes/web.php, and builds the role constraint inside
| a try so a database hiccup while the ROUTER boots can never take the whole
| ERP down. Authorisation lives in the controller (super admin only).
*/

try {
    $eonAiRoles = Role::pluck('name')->map(fn ($name) => Str::slug($name))->implode('|');
} catch (\Throwable $e) {
    $eonAiRoles = '';
}

Route::middleware(['web', 'auth', \App\Http\Middleware\EnsurePanelRoleIsPermitted::class])
    ->prefix('{role}')
    ->name('role.')
    ->where(['role' => $eonAiRoles !== '' ? $eonAiRoles : '[A-Za-z0-9\-_]+'])
    ->group(function () {
        Route::prefix('eon-ai')->name('eon-ai.')->group(function () {
            // EON's home in the ERP. Status for now; EON's workspace later.
            Route::get('/', [EonAiController::class, 'index'])->name('index');

            // The connection point: EON's address, the shared key, on/off.
            Route::get('connection', [EonAiController::class, 'connection'])->name('connection');
            Route::post('connection', [EonAiController::class, 'save'])->name('connection.save');
            Route::post('connection/key', [EonAiController::class, 'key'])->name('connection.key');

            // The only outbound call: a signed handshake, nothing else sent.
            Route::post('connection/test', [EonAiController::class, 'test'])
                ->middleware('throttle:12,1')
                ->name('connection.test');
        });
    });
