<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Materials\MaterialsController;

/* Wood Art · Materials — included inside the shared role group.

   Same shape as Projects and Clients: the literal `create` path precedes the
   `{section?}` wildcard, {material} binds on `ext_id`, and the model's global
   company scope applies to binding so a code can only resolve to a Wood Art
   row. Deleting is a GET confirmation then a DELETE, which avoids an inline
   JS confirm() inside [data-wa-view]. */

Route::get('woodart/materials/create', [MaterialsController::class, 'create'])
    ->name('woodart.materials.create');

Route::post('woodart/materials', [MaterialsController::class, 'store'])
    ->name('woodart.materials.store');

Route::get('woodart/materials/{material}/edit', [MaterialsController::class, 'edit'])
    ->name('woodart.materials.edit');

Route::put('woodart/materials/{material}', [MaterialsController::class, 'update'])
    ->name('woodart.materials.update');

Route::get('woodart/materials/{material}/delete', [MaterialsController::class, 'confirmDelete'])
    ->name('woodart.materials.delete');

Route::delete('woodart/materials/{material}', [MaterialsController::class, 'destroy'])
    ->name('woodart.materials.destroy');

Route::get('woodart/materials/{section?}', [MaterialsController::class, 'show'])
    ->where('section', 'stock|movements|reorder|valuation')
    ->name('woodart.materials');
