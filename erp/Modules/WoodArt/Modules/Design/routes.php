<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Design\DesignController;

/* Wood Art · Design & 3D — included inside the shared role group.

   Same shape as every other Wood Art register: the literal `create` path
   precedes the `{section?}` wildcard, {drawing} binds on `ext_id`, and the
   model's global company scope applies to binding so a code can only ever
   resolve to a Wood Art row. Deleting is a GET confirmation then a DELETE —
   no inline JS confirm() inside [data-wa-view].

   A revision is APPENDED, never edited or deleted: there is a create pair for
   the trail and deliberately no update or destroy route. See Revision. */

Route::get('woodart/design/create', [DesignController::class, 'create'])
    ->name('woodart.design.create');

Route::post('woodart/design', [DesignController::class, 'store'])
    ->name('woodart.design.store');

Route::get('woodart/design/{drawing}/edit', [DesignController::class, 'edit'])
    ->name('woodart.design.edit');

Route::put('woodart/design/{drawing}', [DesignController::class, 'update'])
    ->name('woodart.design.update');

Route::get('woodart/design/{drawing}/revision', [DesignController::class, 'createRevision'])
    ->name('woodart.design.revision.create');

Route::post('woodart/design/{drawing}/revision', [DesignController::class, 'storeRevision'])
    ->name('woodart.design.revision.store');

Route::get('woodart/design/{drawing}/delete', [DesignController::class, 'confirmDelete'])
    ->name('woodart.design.delete');

Route::delete('woodart/design/{drawing}', [DesignController::class, 'destroy'])
    ->name('woodart.design.destroy');

Route::get('woodart/design/{section?}', [DesignController::class, 'show'])
    ->where('section', 'register|approvals|load')
    ->name('woodart.design');
