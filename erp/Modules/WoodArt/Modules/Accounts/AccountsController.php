<?php

namespace Modules\WoodArt\Modules\Accounts;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Wood Art · Accounts — everything this module owns lives in this folder.
 * Mirrors the reference's companies/woodart/modules/accounts/.
 *
 * ─── THIS IS NOT THE ERP'S ACCOUNTS ──────────────────────────────────────
 * The host ERP already has a live Accounts feature that every other company
 * uses: `Dashboard\AccountController`, routes `role.accounts.*`, URIs
 * `{role}/accounts` and `{role}/accounts/{account}`. This module is separate in
 * every dimension and must stay that way:
 *
 *     live ERP        {role}/accounts...          role.accounts.*
 *     Wood Art        {role}/woodart/accounts/... role.woodart.accounts
 *
 * Different URI, different route name, different controller namespace,
 * different view namespace (`woodart::accounts`). Nothing here reads, writes,
 * extends or overrides the live one. Owner's instruction, 2026-08-11: build the
 * Wood Art side only and leave the Laravel one exactly as it is.
 *
 * ─── NO DATABASE ─────────────────────────────────────────────────────────
 * Deliberately no models, no queries, no migrations. The screens render the
 * empty state, as the rest of the suite does.
 *
 * This matters more here than anywhere else in the suite. The reference's own
 * module.json carries an ownership note: this desk owns NO table — `acc_entries`
 * belongs to Master Accounts and is shared GROUP-WIDE, with Woodart merely one
 * company-scoped tenant of it. So whenever a data layer does arrive, writing it
 * naively would post Wood Art's money into books other companies read. That is
 * precisely the isolation rule in CLAUDE.md, and it is why there is no
 * persistence here at all yet.
 */
class AccountsController extends Controller
{
    /**
     * TAB labels — from module.json's menu, and identical to the reference's
     * own tab band (template.html:44-54) and to the sidebar's sub-items.
     */
    public const SECTIONS = [
        'overview'  => 'Overview',
        'income'    => 'Income',
        'expenses'  => 'Expenses',
        'payables'  => 'Payables',
        'pnl'       => 'Project P&L',
        'payroll'   => 'Payroll',
        'recurring' => 'Recurring',
        'banks'     => 'Banks',
        'cash'      => 'Manage Cash',
        'journals'  => 'Journals',
        'schedules' => 'Schedules',
    ];

    /**
     * PAGE titles — TAB_COPY[key][0] (view.js:492-504). Unlike every other
     * module in the suite these do NOT all match the tab labels: the Overview
     * tab titles the page "Accounts", Payables becomes "Vendor Payables" and
     * Schedules becomes "Payment Schedules". Keep both maps.
     */
    private const TITLES = [
        'overview'  => 'Accounts',
        'income'    => 'Income',
        'expenses'  => 'Expenses',
        'payables'  => 'Vendor Payables',
        'pnl'       => 'Project P&L',
        'payroll'   => 'Payroll',
        'recurring' => 'Recurring',
        'banks'     => 'Banks',
        'cash'      => 'Manage Cash',
        'journals'  => 'Journals',
        'schedules' => 'Payment Schedules',
    ];

    /** Verbatim from the module's TAB_COPY (view.js:492-504). */
    private const SUBTITLES = [
        'overview'  => 'Income, expenses, journals and payment schedules for Woodart Interiors.',
        'income'    => 'Project billings, design fees and everything else the business earned.',
        'expenses'  => 'Every cost of running the workshop, with its project or order.',
        'payables'  => 'What Woodart owes, per purchase order — oldest first.',
        'pnl'       => 'Value against cost against the approved bill of quantities.',
        'payroll'   => 'Salary run, payslips, loans & advances — posted to the ledger.',
        'recurring' => 'Standing monthly costs — rent, utilities, retainers.',
        'banks'     => "Woodart's own accounts and what is in them.",
        'cash'      => 'Cash book, petty cash and cheques.',
        'journals'  => 'The double entry behind every screen in this module.',
        'schedules' => 'Money promised for a future date, and what is overdue.',
    ];

    /** See ProjectsController::show() for why $role must be declared. */
    public function show(string $role, string $section = 'overview'): View
    {
        abort_unless(\array_key_exists($section, self::SECTIONS), 404);

        return view('woodart::accounts', [
            'waModule'  => 'accounts',
            'waRoute'   => 'role.woodart.accounts',
            'waIcon'    => 'cash-stack',
            'section'   => $section,
            'sections'  => self::SECTIONS,
            'title'     => self::TITLES[$section],
            'subtitle'  => self::SUBTITLES[$section],
            // Eleven tabs: the reference gives this band .tabs-dense.
            'tabsDense' => true,
        ]);
    }
}
