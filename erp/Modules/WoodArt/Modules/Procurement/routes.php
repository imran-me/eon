<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Procurement\ProcurementController;

/* Wood Art · Procurement — included inside the shared role group.

   This module owns TWO entities, so its paths are namespaced by entity —
   woodart/procurement/orders/... and .../vendors/... — before the
   `{section?}` wildcard. The wildcard is constrained to the three section
   names, so "orders/create" cannot be swallowed by it.

   Both models bind on `ext_id` and carry a global company scope, so a code can
   only ever resolve to a Wood Art row. Deleting is a GET confirmation then a
   DELETE, avoiding an inline JS confirm() inside [data-wa-view]. */

/* ── purchase orders ──────────────────────────────────────────────────────── */
Route::get('woodart/procurement/orders/create', [ProcurementController::class, 'createOrder'])
    ->name('woodart.procurement.orders.create');

Route::post('woodart/procurement/orders', [ProcurementController::class, 'storeOrder'])
    ->name('woodart.procurement.orders.store');

Route::get('woodart/procurement/orders/{order}/edit', [ProcurementController::class, 'editOrder'])
    ->name('woodart.procurement.orders.edit');

Route::put('woodart/procurement/orders/{order}', [ProcurementController::class, 'updateOrder'])
    ->name('woodart.procurement.orders.update');

Route::get('woodart/procurement/orders/{order}/delete', [ProcurementController::class, 'confirmDeleteOrder'])
    ->name('woodart.procurement.orders.delete');

Route::delete('woodart/procurement/orders/{order}', [ProcurementController::class, 'destroyOrder'])
    ->name('woodart.procurement.orders.destroy');

/* ── vendors ──────────────────────────────────────────────────────────────── */
Route::get('woodart/procurement/vendors/create', [ProcurementController::class, 'createVendor'])
    ->name('woodart.procurement.vendors.create');

Route::post('woodart/procurement/vendors', [ProcurementController::class, 'storeVendor'])
    ->name('woodart.procurement.vendors.store');

Route::get('woodart/procurement/vendors/{vendor}/edit', [ProcurementController::class, 'editVendor'])
    ->name('woodart.procurement.vendors.edit');

Route::put('woodart/procurement/vendors/{vendor}', [ProcurementController::class, 'updateVendor'])
    ->name('woodart.procurement.vendors.update');

Route::get('woodart/procurement/vendors/{vendor}/delete', [ProcurementController::class, 'confirmDeleteVendor'])
    ->name('woodart.procurement.vendors.delete');

Route::delete('woodart/procurement/vendors/{vendor}', [ProcurementController::class, 'destroyVendor'])
    ->name('woodart.procurement.vendors.destroy');

/* ── the three screens ────────────────────────────────────────────────────── */
Route::get('woodart/procurement/{section?}', [ProcurementController::class, 'show'])
    ->where('section', 'orders|vendors|spend')
    ->name('woodart.procurement');
