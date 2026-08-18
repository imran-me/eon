<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Installation\InstallationController;

/* Wood Art · Site & Install — included inside the shared role group.

   The URI segment is `installation`, not `site-install`: it must equal the
   sidebar's data-wa-module value for woodart-nav.js to highlight the right
   menu row, and it must stay under the `woodart/` prefix or the nav script
   falls back to a full page load.

   Same shape as every other Wood Art register: the literal `create` path
   precedes the `{section?}` wildcard, {install} binds on `ext_id`, and the
   model's global company scope applies to binding so a code can only ever
   resolve to a Wood Art row. Deleting is a GET confirmation then a DELETE,
   which avoids an inline JS confirm() inside [data-wa-view]. */

Route::get('woodart/installation/create', [InstallationController::class, 'create'])
    ->name('woodart.installation.create');

Route::post('woodart/installation', [InstallationController::class, 'store'])
    ->name('woodart.installation.store');

Route::get('woodart/installation/{install}/edit', [InstallationController::class, 'edit'])
    ->name('woodart.installation.edit');

Route::put('woodart/installation/{install}', [InstallationController::class, 'update'])
    ->name('woodart.installation.update');

Route::get('woodart/installation/{install}/delete', [InstallationController::class, 'confirmDelete'])
    ->name('woodart.installation.delete');

Route::delete('woodart/installation/{install}', [InstallationController::class, 'destroy'])
    ->name('woodart.installation.destroy');

Route::get('woodart/installation/{section?}', [InstallationController::class, 'show'])
    ->where('section', 'schedule|snags|teams')
    ->name('woodart.installation');
