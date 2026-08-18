<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Accounts\AccountsController;

/* Wood Art · Accounts — included inside the shared role group.

   DISTINCT FROM THE LIVE ERP ACCOUNTS. The host already serves
   `{role}/accounts` and `{role}/accounts/{account}` as `role.accounts.*`
   (Dashboard\AccountController) for every other company. This route sits under
   the woodart/ segment with its own name, so neither can shadow the other —
   Laravel matches on URI, and these never overlap. Do not move this route out
   from under `woodart/`. */

Route::get('woodart/accounts/{section?}', [AccountsController::class, 'show'])
    ->where('section', 'overview|income|expenses|payables|pnl|payroll|recurring|banks|cash|journals|schedules')
    ->name('woodart.accounts');
