<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Production\ProductionController;

/* Wood Art · Workshop — included inside the shared role group.

   The URI segment is `production`, not `workshop`: it must equal the sidebar's
   data-wa-module value for woodart-nav.js to highlight the right menu row, and
   it must stay under the `woodart/` prefix or the nav script falls back to a
   full page load.

   Same shape as Projects, Clients, Materials and Procurement: the literal
   `create` path precedes the `{section?}` wildcard, {job} binds on `ext_id`,
   and the model's global company scope applies to binding so a code can only
   ever resolve to a Wood Art row. Deleting is a GET confirmation then a
   DELETE, which avoids an inline JS confirm() inside [data-wa-view]. */

Route::get('woodart/production/create', [ProductionController::class, 'create'])
    ->name('woodart.production.create');

Route::post('woodart/production', [ProductionController::class, 'store'])
    ->name('woodart.production.store');

Route::get('woodart/production/{job}/edit', [ProductionController::class, 'edit'])
    ->name('woodart.production.edit');

Route::put('woodart/production/{job}', [ProductionController::class, 'update'])
    ->name('woodart.production.update');

/* Serves the board drag AND the posted arrow buttons — see
   ProductionController::updateStatus(). */
Route::patch('woodart/production/{job}/status', [ProductionController::class, 'updateStatus'])
    ->name('woodart.production.status');

Route::get('woodart/production/{job}/delete', [ProductionController::class, 'confirmDelete'])
    ->name('woodart.production.delete');

Route::delete('woodart/production/{job}', [ProductionController::class, 'destroy'])
    ->name('woodart.production.destroy');

Route::get('woodart/production/{section?}', [ProductionController::class, 'show'])
    ->where('section', 'jobs|board|load')
    ->name('woodart.production');
