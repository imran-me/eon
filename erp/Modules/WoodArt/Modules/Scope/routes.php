<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Scope\ScopeController;

/* Wood Art · Spaces & Phases — included inside the shared role group.

   Two record types share one module, so the literal segments (`spaces/create`,
   `phases/create`) come BEFORE the `{section?}` wildcard. {space} binds on
   Space::ext_id and {phase} on Phase::ext_id, and each model's global company
   scope applies to binding, so a code can only ever resolve to a Wood Art row.

   Deleting is a GET confirmation then a DELETE — no inline JS confirm() inside
   [data-wa-view]. */

/* ── spaces ─────────────────────────────────────────────────────────────── */

Route::get('woodart/scope/spaces/create', [ScopeController::class, 'createSpace'])
    ->name('woodart.scope.spaces.create');

Route::post('woodart/scope/spaces', [ScopeController::class, 'storeSpace'])
    ->name('woodart.scope.spaces.store');

Route::get('woodart/scope/spaces/{space}/edit', [ScopeController::class, 'editSpace'])
    ->name('woodart.scope.spaces.edit');

Route::put('woodart/scope/spaces/{space}', [ScopeController::class, 'updateSpace'])
    ->name('woodart.scope.spaces.update');

Route::get('woodart/scope/spaces/{space}/delete', [ScopeController::class, 'confirmDeleteSpace'])
    ->name('woodart.scope.spaces.delete');

Route::delete('woodart/scope/spaces/{space}', [ScopeController::class, 'destroySpace'])
    ->name('woodart.scope.spaces.destroy');

/* ── phases ─────────────────────────────────────────────────────────────── */

Route::get('woodart/scope/phases/create', [ScopeController::class, 'createPhase'])
    ->name('woodart.scope.phases.create');

Route::post('woodart/scope/phases', [ScopeController::class, 'storePhase'])
    ->name('woodart.scope.phases.store');

Route::get('woodart/scope/phases/{phase}/edit', [ScopeController::class, 'editPhase'])
    ->name('woodart.scope.phases.edit');

Route::put('woodart/scope/phases/{phase}', [ScopeController::class, 'updatePhase'])
    ->name('woodart.scope.phases.update');

/* Serves the board drag AND the posted arrow buttons. */
Route::patch('woodart/scope/phases/{phase}/status', [ScopeController::class, 'updatePhaseStatus'])
    ->name('woodart.scope.phases.status');

Route::get('woodart/scope/phases/{phase}/delete', [ScopeController::class, 'confirmDeletePhase'])
    ->name('woodart.scope.phases.delete');

Route::delete('woodart/scope/phases/{phase}', [ScopeController::class, 'destroyPhase'])
    ->name('woodart.scope.phases.destroy');

/* ── the four screens ───────────────────────────────────────────────────── */

Route::get('woodart/scope/{section?}', [ScopeController::class, 'show'])
    ->where('section', 'spaces|phases|materials|load')
    ->name('woodart.scope');
