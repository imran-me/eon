{{--
    Wood Art · Accounts — extends the suite shell.

    ── NOT THE ERP'S ACCOUNTS ────────────────────────────────────────────────
    The host ERP's live Accounts (role.accounts.*, {role}/accounts,
    Dashboard\AccountController) is a different feature used by every other
    company, and nothing here touches it. This view lives in the woodart::
    namespace, is reachable only under {role}/woodart/accounts, and reads no
    database at all. See AccountsController's header for the full separation.

    ── TRANSCRIPTION ─────────────────────────────────────────────────────────
    From companies/woodart/modules/accounts/frontend/template.html, with
    empty-state copy from view.js:584/752/808/848/884/926/981 and the action
    centre's own empty state from view.js:628-631.

    ZEROS, NOT DASHES. Every other module in this suite prints a dash for an
    empty money KPI, because those modules format through money() —
    ui.money(null|0) guards on null and their values arrive null. THIS module
    formats through ui.compact() directly (view.js:595-599 and throughout), and
    ui.compact(0) returns the string "0" (platform/core/ui.js:100-106). So an
    empty Accounts renders 0, not —. The only dashes here are the four values
    the reference explicitly defaults to '—': Biggest Head on Income and on
    Expenses (view.js:683/703), Oldest Unpaid (719) and Largest bank (865).
    Journals' "Balanced" reads Yes, because |0 − 0| < 0.01 (view.js:908).

    Blocks deliberately absent, each removed by the reference's own logic when
    there is no data:
      · Payables' STATE B (view.js:724) — STATE A, the all-clear, renders.
      · Project P&L's "N project(s) over BOQ budget" banner (view.js:778).
      · Overview's two canvas charts, which need series data (see note below).
--}}
@extends('woodart::layouts.suite')

@section('wa-head-actions')
    {{-- The reference also carries a ghost "Ledgers" link (template.html:38).
         Ledgers is not mounted — and unlike Procurement it has no reference
         module folder to build from — so the action is removed rather than
         rendered dead, the same grammar used for Materials before Procurement
         existed. --}}
    <button type="button" class="wap-btn wap-btn-primary"><i class="bi bi-plus-lg"></i> New Entry</button>
@endsection

@section('wa-view')

    @if($section === 'overview')
        {{-- SCREEN 1 · OVERVIEW — the money at a glance. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Income</span><span class="wap-kpi-ico"><i class="bi bi-graph-up-arrow"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Expenses</span><span class="wap-kpi-ico"><i class="bi bi-graph-down-arrow"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Net Result</span><span class="wap-kpi-ico"><i class="bi bi-calculator"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Cash &amp; Bank</span><span class="wap-kpi-ico"><i class="bi bi-bank"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Payables</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">0</div>
                <div class="wap-kpi-foot">0 vendor(s) unpaid</div>
            </div>
        </div>

        <div class="wap-caption">ACTION CENTER &mdash; NEEDS ATTENTION</div>
        <div class="wap-card">
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-check2-circle"></i>
                    <div class="wap-empty-title">Nothing needs attention</div>
                    <div class="wap-empty-sub">No overdue payables, no job over its BOQ budget.</div>
                </div>
            </div>
        </div>

        {{-- The reference's "CASH MOVEMENT" pair follows here — an 8-month
             income-vs-expense line and an expense-by-head doughnut. Both are
             canvas charts drawn from series data, so with nothing recorded they
             would be two empty plot areas. They arrive with the data layer, as
             on Materials, Clients and Site & Install. --}}

    @elseif($section === 'income')
        {{-- SCREEN 2 · INCOME — what the interiors business earned. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Income</span><span class="wap-kpi-ico"><i class="bi bi-graph-up-arrow"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Entries</span><span class="wap-kpi-ico"><i class="bi bi-receipt"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Biggest Head</span><span class="wap-kpi-ico"><i class="bi bi-fire"></i></span></div>
                <div class="wap-kpi-value">&mdash;</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Billed to Projects</span><span class="wap-kpi-ico"><i class="bi bi-easel2"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-graph-up-arrow"></i> Income Register</h3>
                <span class="wap-card-sub">Newest first &mdash; project billings, design fees and everything else</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-journal-text"></i>
                    <div class="wap-empty-title">Nothing recorded yet</div>
                    <div class="wap-empty-sub">Record the first entry.</div>
                </div>
            </div>
        </div>

    @elseif($section === 'expenses')
        {{-- SCREEN 3 · EXPENSES — what it cost to run the workshop. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Expenses</span><span class="wap-kpi-ico"><i class="bi bi-graph-down-arrow"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Entries</span><span class="wap-kpi-ico"><i class="bi bi-receipt-cutoff"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Biggest Head</span><span class="wap-kpi-ico"><i class="bi bi-fire"></i></span></div>
                <div class="wap-kpi-value">&mdash;</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Paid to Vendors</span><span class="wap-kpi-ico"><i class="bi bi-truck"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-graph-down-arrow"></i> Expense Register</h3>
                <span class="wap-card-sub">Newest first &mdash; every cost, with its project or purchase order</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-journal-text"></i>
                    <div class="wap-empty-title">Nothing recorded yet</div>
                    <div class="wap-empty-sub">Record the first entry.</div>
                </div>
            </div>
        </div>

    @elseif($section === 'payables')
        {{-- SCREEN 4 · VENDOR PAYABLES — what Woodart owes, per order. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Outstanding</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Vendors Owed</span><span class="wap-kpi-ico"><i class="bi bi-people-fill"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Oldest Unpaid</span><span class="wap-kpi-ico"><i class="bi bi-clock-history"></i></span></div>
                <div class="wap-kpi-value">&mdash;</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Orders Settled</span><span class="wap-kpi-ico"><i class="bi bi-check2-circle"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
        </div>

        {{-- STATE A · nothing owed (the current truth). --}}
        <div class="wap-empty">
            <i class="bi bi-check2-circle"></i>
            <div class="wap-empty-title">Every order is settled</div>
            <div class="wap-empty-sub">No vendor is waiting on Woodart. New goods receipts will raise a payable here.</div>
        </div>

    @elseif($section === 'pnl')
        {{-- SCREEN 5 · PROJECT P&L — the screen no other company can show.
             Variance is BOQ budget minus material actually issued from the
             stock ledger: negative means the job is eating more material than
             it was quoted for. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Portfolio Value</span><span class="wap-kpi-ico"><i class="bi bi-easel2"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Committed Cost</span><span class="wap-kpi-ico"><i class="bi bi-briefcase"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Margin</span><span class="wap-kpi-ico"><i class="bi bi-graph-up"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Billed To Date</span><span class="wap-kpi-ico"><i class="bi bi-receipt-cutoff"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Over Budget</span><span class="wap-kpi-ico"><i class="bi bi-fire"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-clipboard-data"></i> Project Profit &amp; Loss</h3>
                <span class="wap-card-sub">Value vs cost vs the approved bill of quantities</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-clipboard-data"></i>
                    <div class="wap-empty-title">No projects</div>
                    <div class="wap-empty-sub">Projects appear here once the portfolio has work.</div>
                </div>
            </div>
        </div>

    @elseif($section === 'payroll' || $section === 'cash')
        {{-- SCREENS 6 & 9 · PAYROLL and MANAGE CASH.

             In the reference these two mount SHARED desks (EPAL.payrollDesk /
             EPAL.cashDesk, template.html:415-429) — deliberately the same code
             other concerns run, "so a fix to the cash book reaches both
             concerns at once".

             That sharing is exactly what this ERP must not do. Wiring Wood Art
             into the host's live payroll or cash screens would couple company 6
             to data and behaviour eleven other companies depend on, which
             CLAUDE.md forbids outright. So these render an honest placeholder
             instead of borrowing another company's desk. --}}
        <div class="wap-empty">
            <i class="bi bi-{{ $section === 'payroll' ? 'cash-coin' : 'cash-stack' }}"></i>
            <div class="wap-empty-title">{{ $section === 'payroll' ? 'Payroll' : 'Manage Cash' }} is not wired up yet</div>
            <div class="wap-empty-sub">
                The reference mounts a shared desk here &mdash; the same one other concerns run.
                Wood Art gets its own rather than borrowing it, so that nothing this module does
                can reach another company's books.
            </div>
        </div>

    @elseif($section === 'recurring')
        {{-- SCREEN 7 · RECURRING — standing costs that repeat every month. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Active</span><span class="wap-kpi-ico"><i class="bi bi-arrow-repeat"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Monthly Commitment</span><span class="wap-kpi-ico"><i class="bi bi-calendar-check"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Due This Month</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Paused</span><span class="wap-kpi-ico"><i class="bi bi-pause-circle"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-arrow-repeat"></i> Recurring Costs</h3>
                <span class="wap-card-sub">Workshop rent, utilities, retainers &mdash; the bills that come every month</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-arrow-repeat"></i>
                    <div class="wap-empty-title">No recurring costs</div>
                    <div class="wap-empty-sub">Add the workshop rent, utilities or a retainer.</div>
                </div>
            </div>
        </div>

    @elseif($section === 'banks')
        {{-- SCREEN 8 · BANKS — Woodart's own accounts and what is in them. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Accounts</span><span class="wap-kpi-ico"><i class="bi bi-bank"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Balance</span><span class="wap-kpi-ico"><i class="bi bi-wallet2"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Largest</span><span class="wap-kpi-ico"><i class="bi bi-safe2"></i></span></div>
                <div class="wap-kpi-value">&mdash;</div>
            </div>
        </div>

        {{-- The reference prints this banner unconditionally and links "Master
             Accounts › Manage Banks". That is a GROUP-level screen in the
             reference's own shell; this ERP has no equivalent route, so the
             sentence stays (it is the reference's copy) but the link is not
             rendered rather than pointing nowhere. --}}
        <div class="wap-banner">
            <i class="bi bi-info-circle"></i>
            <div>Accounts are group master data &mdash; added and edited on
                Master Accounts &rsaquo; Manage Banks. This screen is Woodart's view of the
                ones that belong to this concern.</div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-bank"></i> Woodart Accounts</h3>
                <span class="wap-card-sub">Balance and last movement per account</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-bank"></i>
                    <div class="wap-empty-title">No accounts</div>
                    <div class="wap-empty-sub">Add one on Master Accounts &rsaquo; Manage Banks.</div>
                </div>
            </div>
        </div>

    @elseif($section === 'journals')
        {{-- SCREEN 10 · JOURNALS — the double entry behind every screen above. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Journals</span><span class="wap-kpi-ico"><i class="bi bi-journal-text"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Debits</span><span class="wap-kpi-ico"><i class="bi bi-arrow-down-left"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Credits</span><span class="wap-kpi-ico"><i class="bi bi-arrow-up-right"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            {{-- "Yes" because |debits − credits| < 0.01 holds trivially at zero
                 (view.js:908). An empty ledger is a balanced ledger. --}}
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Balanced</span><span class="wap-kpi-ico"><i class="bi bi-check2-square"></i></span></div>
                <div class="wap-kpi-value">Yes</div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-journal-text"></i> Journal Entries</h3>
                <span class="wap-card-sub">Every posting this concern has made &mdash; newest first</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-journal-text"></i>
                    <div class="wap-empty-title">No journals yet</div>
                    <div class="wap-empty-sub">Postings appear here as soon as money moves.</div>
                </div>
            </div>
        </div>

    @else
        {{-- SCREEN 11 · SCHEDULES — money promised for a future date. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Open</span><span class="wap-kpi-ico"><i class="bi bi-calendar2-week"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Scheduled Out</span><span class="wap-kpi-ico"><i class="bi bi-arrow-up-right"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Overdue</span><span class="wap-kpi-ico"><i class="bi bi-exclamation-diamond"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Due in 7 Days</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">0</div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-calendar2-week"></i> Payment Schedules</h3>
                <span class="wap-card-sub">What is promised, to whom, and when</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-calendar2-week"></i>
                    <div class="wap-empty-title">Nothing scheduled</div>
                    <div class="wap-empty-sub">Promised payments appear here.</div>
                </div>
            </div>
        </div>
    @endif

@endsection
