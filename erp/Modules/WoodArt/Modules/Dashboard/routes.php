<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Dashboard\DashboardController;

/* Wood Art · Dashboard — included inside the shared role group.

   It carries a {section?} segment like every other Wood Art module even though
   it has only one screen. That is deliberate: the shared sidebar builds every
   Wood Art link as route($name, ['role' => …, 'section' => …]), so matching the
   shape means the sidebar needs a one-line array entry and NO change to its
   link-building logic — the smallest possible edit to a shared file. */

Route::get('woodart/dashboard/{section?}', [DashboardController::class, 'show'])
    ->where('section', 'overview')
    ->name('woodart.dashboard');
