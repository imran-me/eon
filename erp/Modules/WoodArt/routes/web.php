<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Wood Art Interiors — route spine
|--------------------------------------------------------------------------
| Declares the shared role group ONCE (mirroring the host's routes/web.php:148
| — auth guard, /{role} prefix constrained to the real role slugs, `role.`
| name prefix; `web` must be explicit because loadRoutesFrom() does not add
| it), then pulls in every module's own routes.php from its folder.
|
| A module's routes live WITH the module (Modules/<Name>/routes.php), so
| adding a module never edits this file — dropping the folder in is enough.
| Every route stays under the woodart/ URI segment: that segment is what
| woodart-nav.js matches on for no-reload navigation (see CLAUDE.md).
*/

$roles = Role::pluck('name')->map(fn ($name) => Str::slug($name))->implode('|');

Route::middleware(['web', 'auth'])
    ->prefix('{role}')
    ->name('role.')
    ->where(['role' => $roles])
    ->group(function () {
        foreach (glob(__DIR__ . '/../Modules/*/routes.php') as $moduleRoutes) {
            require $moduleRoutes;
        }
    });
