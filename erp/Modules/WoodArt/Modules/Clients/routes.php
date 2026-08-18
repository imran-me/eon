<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Clients\ClientsController;

/* Wood Art · Clients — included inside the shared role group.

   Ordering and shape mirror Projects: the literal `create` path is declared
   before the `{section?}` wildcard, and {client} binds on `ext_id`
   (Client::getRouteKeyName) so URLs read /woodart/clients/WAC-101/edit. The
   model's global company scope applies to binding, so a code can only ever
   resolve to a Wood Art row.

   Deleting is a two-step — a GET confirmation page, then the DELETE — which
   avoids an inline JS confirm() inside [data-wa-view]. */

Route::get('woodart/clients/create', [ClientsController::class, 'create'])
    ->name('woodart.clients.create');

Route::post('woodart/clients', [ClientsController::class, 'store'])
    ->name('woodart.clients.store');

Route::get('woodart/clients/{client}/edit', [ClientsController::class, 'edit'])
    ->name('woodart.clients.edit');

Route::put('woodart/clients/{client}', [ClientsController::class, 'update'])
    ->name('woodart.clients.update');

Route::get('woodart/clients/{client}/delete', [ClientsController::class, 'confirmDelete'])
    ->name('woodart.clients.delete');

Route::delete('woodart/clients/{client}', [ClientsController::class, 'destroy'])
    ->name('woodart.clients.destroy');

Route::get('woodart/clients/{section?}', [ClientsController::class, 'show'])
    ->where('section', 'directory|portfolio|segments')
    ->name('woodart.clients');
