<?php

use Illuminate\Support\Facades\Route;
use Modules\WoodArt\Modules\Accounts\AccountsController;
use Modules\WoodArt\Modules\Accounts\ConstructionProjectController;
use Modules\WoodArt\Modules\Accounts\ExpenseBulkController;
use Modules\WoodArt\Modules\Accounts\ProjectSummaryController;
use Modules\WoodArt\Modules\Accounts\SiteStatementController;
use Modules\WoodArt\Modules\Accounts\Support\ProjectContext;

/* Project Accounts — included inside the shared role group.

   ONE set of screens, mounted once per company that runs projects:

       woodart/accounts/...        role.woodart.accounts*        company 6  Wood Art Interiors
       construction/accounts/...   role.construction.accounts*   company 3  Epal Constructions

   Which company a request is for is read from this URL segment
   (Support\ProjectContext) — never from anything the browser sends. Each company's
   books stay its own; only the code is shared, so a company with no dashboard of
   its own does not have to wait for one.

   DISTINCT FROM THE LIVE ERP ACCOUNTS. The host already serves
   `{role}/accounts` and `{role}/accounts/{account}` as `role.accounts.*`
   (Dashboard\AccountController) for every company. These routes sit under their
   own segment with their own names, so neither can shadow the other — Laravel
   matches on URI, and these never overlap. The woodart/ segment is also what
   woodart-nav.js matches on for no-reload navigation: do not move Wood Art's
   routes out from under it. */

foreach (['woodart', 'construction'] as $p) {

    Route::get("$p/accounts/{section?}", [AccountsController::class, 'show'])
        ->where('section', 'overview|projects|income|expenses|receivables|payables|pnl|parties|payroll|recurring|banks|cash|journals|schedules')
        ->name("$p.accounts");

    /* Bulk entry + the per-project cost sheet. Over the ERP's Expense desk:
       entry goes through ExpenseController's own store(); the sheet only reads.
       Declared after the section route: none of these paths can be mistaken for a
       `{section}` (they have more than one segment). */

    Route::get("$p/accounts/expenses/bulk", [ExpenseBulkController::class, 'form'])
        ->name("$p.accounts.bulk");

    Route::post("$p/accounts/expenses/bulk/review", [ExpenseBulkController::class, 'review'])
        ->name("$p.accounts.bulk.review");

    Route::post("$p/accounts/expenses/bulk/store", [ExpenseBulkController::class, 'store'])
        ->name("$p.accounts.bulk.store");

    Route::post("$p/accounts/expenses/bulk/edit", [ExpenseBulkController::class, 'edit'])
        ->name("$p.accounts.bulk.edit");

    Route::get("$p/accounts/project/{department}", [AccountsController::class, 'project'])
        ->whereNumber('department')
        ->name("$p.accounts.project");

    /* The boss's page for one project (client paid/due, suppliers, site team, what waits for the
       accountant), each person's account on it, and the funds provided to a person. */
    Route::get("$p/accounts/project/{department}/summary", [ProjectSummaryController::class, 'summary'])
        ->whereNumber('department')->name("$p.accounts.project.summary");
    Route::get("$p/accounts/project/{department}/journals", [ProjectSummaryController::class, 'journals'])
        ->whereNumber('department')->name("$p.accounts.project.journals");
    Route::get("$p/accounts/project/{department}/person/{user}", [ProjectSummaryController::class, 'person'])
        ->whereNumber(['department', 'user'])->name("$p.accounts.project.person");
    Route::get("$p/accounts/project/{department}/person/{user}/print", [ProjectSummaryController::class, 'personPrint'])
        ->whereNumber(['department', 'user'])->name("$p.accounts.project.person.print");
    Route::post("$p/accounts/project/{department}/fund", [ProjectSummaryController::class, 'fund'])
        ->whereNumber('department')->name("$p.accounts.project.fund");
    Route::post("$p/accounts/project/{department}/fund/post", [ProjectSummaryController::class, 'postFunds'])
        ->whereNumber('department')->name("$p.accounts.project.fund.post");
    Route::post("$p/accounts/project/{department}/fund/cover", [ProjectSummaryController::class, 'coverSpend'])
        ->whereNumber('department')->name("$p.accounts.project.fund.cover");
    Route::post("$p/accounts/project/{department}/post-all", [ProjectSummaryController::class, 'postAll'])
        ->whereNumber('department')->name("$p.accounts.project.post-all");

    /* Statement forms on a project's sheet (contract value, client installments,
       supplier dues) — a statement entry stays a statement until it is posted. */
    Route::post("$p/accounts/project/{department}/terms", [SiteStatementController::class, 'terms'])
        ->whereNumber('department')->name("$p.accounts.project.terms");

    Route::post("$p/accounts/project/{department}/statement", [SiteStatementController::class, 'store'])
        ->whereNumber('department')->name("$p.accounts.project.statement");

    Route::delete("$p/accounts/project/{department}/statement/{line}", [SiteStatementController::class, 'void'])
        ->whereNumber(['department', 'line'])->name("$p.accounts.project.statement.void");

    /* Post a statement row to the real books, reverse it, or bill the client for the
       contract. Journals only — through SitePosting, which guards the bank (the
       company's own only), the overdraw check and the one-posting-per-row rule. */
    /* Vendors & Clients: the register, each party's statement, the project's client,
       and tying cost entries to the vendor they were paid to. Metadata only — no journal. */
    Route::post("$p/accounts/project/{department}/client", [SiteStatementController::class, 'setClient'])
        ->whereNumber('department')->name("$p.accounts.project.client");
    Route::get("$p/accounts/parties/{party}", function (string $role, int $party) {
        return redirect()->route('role.party-statement.index', [
            'role' => $role, 'party_id' => $party,
            'company' => ProjectContext::id(),
        ]);
    })->whereNumber('party')->name("$p.accounts.party");

    /* Confirm pending cost entries through the Expense desk's own Approve. */
    Route::post("$p/accounts/project/{department}/confirm", [SiteStatementController::class, 'confirm'])
        ->whereNumber('department')->name("$p.accounts.project.confirm");

    Route::post("$p/accounts/project/{department}/bill", [SiteStatementController::class, 'bill'])
        ->whereNumber('department')->name("$p.accounts.project.bill");

    Route::post("$p/accounts/project/{department}/statement/{line}/post", [SiteStatementController::class, 'post'])
        ->whereNumber(['department', 'line'])->name("$p.accounts.project.statement.post");

    Route::post("$p/accounts/project/{department}/statement/{line}/reverse", [SiteStatementController::class, 'reverse'])
        ->whereNumber(['department', 'line'])->name("$p.accounts.project.statement.reverse");
}

Route::get('construction/accounts/projects/create', [ConstructionProjectController::class, 'create'])->name('construction.accounts.projects.create');
Route::post('construction/accounts/projects', [ConstructionProjectController::class, 'store'])->name('construction.accounts.projects.store');
