<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Estimates\EstimatesController;

/* Wood Art · Estimates & BOQ — included inside the shared role group.

   Same shape as every other Wood Art register: the literal `create` path
   precedes the `{section?}` wildcard, {estimate} binds on `ext_id`, and the
   model's global company scope applies to binding so a code can only ever
   resolve to a Wood Art row. Deleting is a GET confirmation then a DELETE,
   which avoids an inline JS confirm() inside [data-wa-view].

   NOTE: this module has NO migration — `wa_estimates` is created by the
   Projects module, which owns the table. See Estimate for why. */

Route::get('woodart/estimates/create', [EstimatesController::class, 'create'])
    ->name('woodart.estimates.create');

Route::post('woodart/estimates', [EstimatesController::class, 'store'])
    ->name('woodart.estimates.store');

Route::get('woodart/estimates/{estimate}/edit', [EstimatesController::class, 'edit'])
    ->name('woodart.estimates.edit');

Route::put('woodart/estimates/{estimate}', [EstimatesController::class, 'update'])
    ->name('woodart.estimates.update');

Route::get('woodart/estimates/{estimate}/delete', [EstimatesController::class, 'confirmDelete'])
    ->name('woodart.estimates.delete');

Route::delete('woodart/estimates/{estimate}', [EstimatesController::class, 'destroy'])
    ->name('woodart.estimates.destroy');

Route::get('woodart/estimates/{section?}', [EstimatesController::class, 'show'])
    ->where('section', 'quotations|boq|costing')
    ->name('woodart.estimates');
