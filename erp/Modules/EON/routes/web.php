<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\EON\Http\Controllers\EonController;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| EON — route spine
|--------------------------------------------------------------------------
| Mirrors the host's /{role} panel group (routes/web.php:150) so EON pages sit
| where every other page sits, and adds nothing to routes/web.php. `web` must be
| explicit because loadRoutesFrom() does not add it.
|
| The role constraint is built from the roles table, exactly as the host does.
| That is a DB read at route-registration time, so it is wrapped: if the query
| fails on a half-migrated or unreachable database, EON falls back to a
| permissive slug pattern instead of throwing while the ROUTER is booting —
| which would take down every page in the ERP, not just EON's. Failing soft here
| is the whole point of the 2026-08-10 rule.
|
| Authorisation is deliberately NOT expressed on these lines. EonController
| guards every entry point itself: a route is a door, the powers behind it are
| real, and the lock belongs on the room.
*/

try {
    $eonRoles = Role::pluck('name')->map(fn ($name) => Str::slug($name))->implode('|');
} catch (\Throwable $e) {
    $eonRoles = '';
}

Route::middleware(['web', 'auth', \App\Http\Middleware\EnsurePanelRoleIsPermitted::class])
    ->prefix('{role}')
    ->name('role.')
    ->where(['role' => $eonRoles !== '' ? $eonRoles : '[A-Za-z0-9\-_]+'])
    ->group(function () {
        Route::prefix('eon')->name('eon.')->group(function () {
            // The org-wide picture: everyone scored, ranked, with the watchlist.
            Route::get('/', [EonController::class, 'index'])->name('index');

            // One person's dossier — score, trend, drawdown, verdicts, findings.
            Route::get('employee/{employee}', [EonController::class, 'employee'])->name('employee');

            // The same dossier as a printable evaluation sheet.
            Route::get('employee/{employee}/print', [EonController::class, 'printSheet'])->name('employee.print');

            // Written periodic review: save / delete. eon_reviews only.
            Route::post('employee/{employee}/review', [EonController::class, 'saveReview'])->name('review.save');
            Route::delete('review/{review}', [EonController::class, 'deleteReview'])->name('review.delete');

            // JSON, for anything the page loads after first paint.
            Route::get('api/employee/{employee}/series', [EonController::class, 'series'])->name('api.series');
        });
    });
