<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Projects\ProjectsController;

/*
| Wood Art · Projects — module routes.
| Included by Modules/WoodArt/routes/web.php INSIDE the shared role group
| (web+auth, /{role} prefix, `role.` name prefix), so these lines declare only
| what is theirs. Route names are load-bearing: the sidebar links against them.
|
| ORDER MATTERS. `projects/create` is declared BEFORE `projects/{section?}`,
| otherwise the wildcard would claim "create" as a section and 404 it. The
| section pattern excludes it anyway, but the ordering makes that explicit.
|
| Every path stays under the `woodart/` segment — that prefix is what
| woodart-nav.js matches on for no-reload navigation (CLAUDE.md).
*/

Route::get('woodart/projects/create', [ProjectsController::class, 'create'])
    ->name('woodart.projects.create');

Route::post('woodart/projects', [ProjectsController::class, 'store'])
    ->name('woodart.projects.store');

/*
| {project} binds on `ext_id` (Project::getRouteKeyName), so these read
| /woodart/projects/WAP-101/edit. The model's global company scope applies to
| binding too, so a code can only ever resolve to a Wood Art row.
|
| Deleting is a two-step: a GET confirmation page that spells out what will
| happen, then the DELETE. That is deliberate — it avoids an inline JS confirm()
| inside [data-wa-view], and it follows the reference, whose own delete dialog
| enumerates what goes and what is kept rather than asking "are you sure?".
*/
Route::get('woodart/projects/{project}/edit', [ProjectsController::class, 'edit'])
    ->name('woodart.projects.edit');

Route::put('woodart/projects/{project}', [ProjectsController::class, 'update'])
    ->name('woodart.projects.update');

Route::patch('woodart/projects/{project}/stage', [ProjectsController::class, 'updateStage'])
    ->name('woodart.projects.stage');

Route::get('woodart/projects/{project}/delete', [ProjectsController::class, 'confirmDelete'])
    ->name('woodart.projects.delete');

Route::delete('woodart/projects/{project}', [ProjectsController::class, 'destroy'])
    ->name('woodart.projects.destroy');

Route::get('woodart/projects/{section?}', [ProjectsController::class, 'show'])
    ->where('section', 'active|design|milestones|gallery')
    ->name('woodart.projects');
