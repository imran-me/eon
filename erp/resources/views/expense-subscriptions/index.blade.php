{{--
    Subscriptions & Renewals — DM's register joined to this ERP's ledger.

    Rows are fetched live from DM (dm.epal.com.bd) by ExpenseSubscriptionController
    and are NOT stored here; DM's API is read-only, so a local copy could never be
    kept in step with the system people actually edit. What this page adds is the
    one thing DM cannot know: which expense paid a given renewal. See the
    controller's header for the honest gaps in DM's payload — vendor, owner and a
    document's cost have no DM equivalent and are left blank rather than invented.

    Because DM is the register, there is no create form here. "Add in DM" opens the
    DM portal; a form on this page would have nowhere to save to.

    ISOLATION — the rules this file holds itself to, because it renders inside the
    shared shell (sidebar, header) that every company uses:

      · Every selector is prefixed `sub-`. No bare element selectors (`table`,
        `input`, `a`), no `*`, no `:root`, no `body` — a naked rule here would
        reach the sidebar and the header on this page.
      · The <style> block lives in @section('css'), so it ships on THIS page only.
        Nothing is added to resources/css/app.css or the Vite bundle.
      · The script is an IIFE that queries inside #subDesk and nowhere else. It
        patches no shared helper and registers no document-level listener.
      · No shared class names are reused (`create-new-btn`, `edit-item-btn`,
        `action-btn`, `badge-*` all belong to sibling screens' own scripts).

    The tab that reaches this page is added to layout/expense-tabs.blade.php, which
    already wraps every route() call in try/catch — so on a server whose route
    cache has not been refreshed the tab silently disappears and every existing
    expense page renders exactly as before.
--}}
@extends('layout.app')

@section('meta-information')
    <title>Subscriptions &amp; Renewals</title>
@endsection

@section('css')
<style>
    .sub-shell {
        background: radial-gradient(circle at top left, rgba(109,40,217,0.10), transparent 34%),
                    radial-gradient(circle at top right, rgba(13,148,136,0.09), transparent 26%),
                    #f5f7fb;
        padding: 18px;
    }

    .sub-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(15,23,42,0.08);
        overflow: hidden;
    }

    /* ── Banners ────────────────────────────────────────────────────────────
       Deliberately loud, and only shown when something is actually wrong. A
       screen of realistic-looking money that is NOT the live register is worth
       being unmistakable about — silence here would be read as "all correct". */
    .sub-preview {
        display: flex; align-items: flex-start; gap: 10px;
        background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
        border-radius: 12px; padding: 11px 14px; margin-bottom: 14px;
        font-size: 12.5px; line-height: 1.5;
    }
    .sub-preview i { font-size: 15px; margin-top: 1px; flex-shrink: 0; }
    .sub-preview strong { font-weight: 800; }
    .sub-preview code {
        background: rgba(146,64,14,0.10); border-radius: 4px;
        padding: 1px 5px; font-size: 11.5px; font-weight: 700;
    }
    .sub-preview.is-down { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .sub-preview.is-down code { background: rgba(153,27,27,0.10); }

    /* ── Header ─────────────────────────────────────────────────────────── */
    .sub-head {
        background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 100%);
        color: #fff; padding: 18px 22px;
        display: flex; justify-content: space-between; align-items: center;
        gap: 12px; flex-wrap: wrap;
    }
    .sub-head h2 { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -0.02em; }
    .sub-head .sub-subtext { color: rgba(255,255,255,0.78); font-size: 12px; margin-top: 4px; }
    .sub-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .sub-btn {
        display: inline-flex; align-items: center; gap: 7px;
        border: 0; border-radius: 10px; padding: 9px 15px;
        font-size: 12.5px; font-weight: 700; cursor: pointer;
        transition: filter .15s ease, background .15s ease;
        text-decoration: none;
    }
    .sub-btn.sub-btn-add { background: #fff; color: #6d28d9; }
    /* The colour is restated on hover because these are anchors now, and the
       shared stylesheet's link colour would otherwise win. */
    .sub-btn.sub-btn-add:hover { filter: brightness(0.94); color: #6d28d9; }
    .sub-btn.sub-btn-ghost { background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.20); }
    .sub-btn.sub-btn-ghost:hover { color: #fff; }
    .sub-btn[disabled] { opacity: .55; cursor: not-allowed; }

    /* ── Due strip — the reason the screen exists ───────────────────────── */
    .sub-due-strip { display: flex; gap: 12px; flex-wrap: wrap; padding: 16px 18px 0; }
    .sub-due {
        flex: 1 1 190px; display: flex; align-items: center; gap: 12px;
        border: 1px solid; border-radius: 14px; padding: 12px 16px;
    }
    .sub-due .sub-due-ico { font-size: 19px; }
    .sub-due .sub-due-n { font-size: 21px; font-weight: 800; line-height: 1.05; }
    .sub-due .sub-due-l { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; opacity: .82; }
    .sub-due.is-overdue { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .sub-due.is-soon    { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .sub-due.is-later   { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }

    /* ── KPI grid ───────────────────────────────────────────────────────── */
    .sub-stats {
        display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px; padding: 14px 18px 0;
    }
    .sub-stat {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
        padding: 14px 16px; box-shadow: 0 6px 18px rgba(15,23,42,0.04);
        border-left: 4px solid #7c3aed;
    }
    .sub-stat .sub-stat-l { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; margin-bottom: 8px; }
    .sub-stat .sub-stat-v { color: #1f2937; font-size: 21px; font-weight: 800; line-height: 1.1; }
    .sub-stat .sub-stat-n { color: #6b7280; font-size: 11.5px; margin-top: 4px; }
    .sub-stat.is-teal   { border-left-color: #0d9488; }
    .sub-stat.is-amber  { border-left-color: #f59e0b; }
    .sub-stat.is-slate  { border-left-color: #64748b; }

    /* ── Toolbar: kind chips + search ───────────────────────────────────── */
    .sub-toolbar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        margin: 16px 18px 0; padding: 12px 14px;
        background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px;
    }
    .sub-chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .sub-chip {
        border: 1px solid #e2e8f0; background: #fff; color: #475569;
        border-radius: 999px; padding: 7px 14px; font-size: 12px; font-weight: 700;
        cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 7px;
    }
    .sub-chip:hover { border-color: #c4b5fd; color: #6d28d9; }
    .sub-chip.is-active { background: #6d28d9; border-color: #6d28d9; color: #fff; }
    .sub-chip .sub-chip-n {
        background: rgba(15,23,42,0.07); border-radius: 999px;
        padding: 1px 7px; font-size: 10.5px; font-weight: 800;
    }
    .sub-chip.is-active .sub-chip-n { background: rgba(255,255,255,0.22); }
    .sub-chip.is-danger { color: #b91c1c; border-color: #fecaca; }
    .sub-chip.is-danger:hover { border-color: #f87171; color: #991b1b; }
    .sub-chip.is-danger.is-active { background: #b91c1c; border-color: #b91c1c; color: #fff; }
    .sub-chip.is-muted { color: #64748b; border-style: dashed; }
    .sub-chip.is-muted.is-active { background: #475569; border-color: #475569; color: #fff; border-style: solid; }

    /* Reminder-only marker — sits where the Record Expense button would be. */
    .sub-act .sub-note {
        display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        padding: 6px 11px; border-radius: 8px; font-size: 11.5px; font-weight: 700;
        background: #f8fafc; color: #94a3b8; border: 1px dashed #cbd5e1; cursor: help;
    }

    .sub-search { position: relative; margin-left: auto; min-width: 230px; }
    .sub-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
    .sub-search input {
        width: 100%; padding: 9px 12px 9px 33px; font-size: 12.5px;
        border: 1px solid #e2e8f0; border-radius: 10px; outline: none; background: #fff; color: #1f2937;
    }
    .sub-search input:focus { border-color: #a78bfa; box-shadow: 0 0 0 3px rgba(124,58,237,0.12); }

    /* ── Table ──────────────────────────────────────────────────────────── */
    .sub-table-wrap { padding: 16px 18px 18px; overflow-x: auto; }
    .sub-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 940px; }
    .sub-table thead th {
        background: #f1f5f9; color: #475569; text-align: left;
        font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em;
        padding: 11px 12px; border-bottom: 1px solid #e2e8f0; white-space: nowrap;
    }
    .sub-table thead th:first-child { border-radius: 10px 0 0 0; }
    .sub-table thead th:last-child  { border-radius: 0 10px 0 0; }
    .sub-table tbody td { padding: 13px 12px; border-bottom: 1px solid #eef2f7; font-size: 13px; color: #334155; vertical-align: top; }
    .sub-table tbody tr:hover td { background: #faf5ff; }
    .sub-table .sub-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }

    .sub-name { font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 7px; }
    .sub-vendor { color: #94a3b8; font-size: 11.5px; margin-top: 2px; }
    .sub-crit {
        width: 7px; height: 7px; border-radius: 50%; background: #ef4444; flex-shrink: 0;
    }
    .sub-for { font-weight: 600; color: #334155; font-size: 12.5px; }
    .sub-for-sub { color: #94a3b8; font-size: 11.5px; margin-top: 2px; }

    .sub-amt { font-weight: 800; color: #0f172a; font-size: 13.5px; }
    .sub-amt-note { color: #94a3b8; font-size: 11px; margin-top: 2px; }

    .sub-kind {
        display: inline-flex; align-items: center; gap: 5px;
        border-radius: 999px; padding: 3px 9px; font-size: 10.5px; font-weight: 800;
        text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
    }
    .sub-kind.k-subscription { background: #ede9fe; color: #5b21b6; }
    .sub-kind.k-renewal      { background: #dbeafe; color: #1e40af; }
    .sub-kind.k-emi          { background: #ccfbf1; color: #0f766e; }

    .sub-due-cell { font-weight: 700; color: #0f172a; white-space: nowrap; }
    .sub-due-tag {
        display: inline-block; margin-top: 3px; border-radius: 999px;
        padding: 2px 8px; font-size: 10.5px; font-weight: 800; white-space: nowrap;
    }
    .sub-due-tag.t-overdue { background: #fee2e2; color: #991b1b; }
    .sub-due-tag.t-soon    { background: #fef3c7; color: #92400e; }
    .sub-due-tag.t-later   { background: #f1f5f9; color: #64748b; }
    .sub-due-tag.t-none    { background: #f1f5f9; color: #94a3b8; }

    .sub-status { border-radius: 999px; padding: 3px 10px; font-size: 11px; font-weight: 800; white-space: nowrap; }
    .sub-status.s-active { background: #dcfce7; color: #166534; }
    .sub-status.s-paused { background: #fef3c7; color: #92400e; }

    .sub-act { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .sub-act .sub-rec {
        display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        padding: 6px 11px; border-radius: 8px; font-size: 11.5px; font-weight: 800;
        background: #6d28d9; color: #fff; border: 1px solid #6d28d9; cursor: pointer;
        text-decoration: none; transition: background .15s ease;
    }
    .sub-act .sub-rec:hover { background: #5b21b6; color: #fff; }
    .sub-act .sub-seen {
        display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;
        padding: 6px 11px; border-radius: 8px; font-size: 11.5px; font-weight: 700;
        background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; text-decoration: none;
    }
    .sub-act .sub-seen:hover { background: #dcfce7; color: #14532d; }
    .sub-act .sub-open {
        width: 30px; height: 30px; border-radius: 8px; background: transparent;
        border: 1px solid #cbd5e1; color: #64748b; font-size: 11px; text-decoration: none;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .sub-act .sub-open:hover { border-color: #a78bfa; color: #6d28d9; }

    .sub-empty { display: none; padding: 54px 18px; text-align: center; color: #6b7280; }
    .sub-empty i { font-size: 42px; opacity: .35; margin-bottom: 10px; display: block; }
    .sub-empty h4 { font-weight: 700; color: #475569; margin: 0 0 4px; font-size: 15px; }
    .sub-empty p { margin: 0; font-size: 12.5px; }

    /* ── Paid marker ────────────────────────────────────────────────────────
       Green, and deliberately not one of the due colours: a settled period is
       not a deadline in any state, so it does not belong on that scale. */
    .sub-due-tag.t-paid { background: #dcfce7; color: #166534; }
    .sub-paid-row td { background: #fcfdfe; }
    .sub-table tbody tr.sub-paid-row:hover td { background: #f6faf7; }

    /* ── Dark shim — the app recolours by class and this page sets its own hex.
         Same approach layout/expense-tabs.blade.php already takes. ────────── */
    html.dark .sub-shell { background: #0f172a; }
    html.dark .sub-card { background: #1e293b; border-color: #334155; }
    html.dark .sub-stat { background: #1e293b; border-color: #334155; }
    html.dark .sub-stat .sub-stat-v { color: #e2e8f0; }
    html.dark .sub-toolbar { background: #0f172a; border-color: #334155; }
    html.dark .sub-chip { background: #1e293b; border-color: #334155; color: #94a3b8; }
    html.dark .sub-search input { background: #1e293b; border-color: #334155; color: #e2e8f0; }
    html.dark .sub-table thead th { background: #0f172a; color: #94a3b8; border-bottom-color: #334155; }
    html.dark .sub-table tbody td { color: #cbd5e1; border-bottom-color: #263449; }
    html.dark .sub-table tbody tr:hover td { background: #24314a; }
    html.dark .sub-name, html.dark .sub-amt, html.dark .sub-due-cell { color: #e2e8f0; }
    html.dark .sub-act .sub-seen { background: #052e16; border-color: #166534; color: #86efac; }
    html.dark .sub-act .sub-open { border-color: #334155; color: #94a3b8; }
    html.dark .sub-paid-row td, html.dark .sub-table tbody tr.sub-paid-row:hover td { background: #17251f; }

    @media (max-width: 1024px) {
        .sub-stats { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }
    @media (max-width: 640px) {
        .sub-shell { padding: 10px; }
        .sub-stats { grid-template-columns: 1fr; }
        .sub-search { margin-left: 0; width: 100%; }
    }
</style>
@endsection

@section('main-content')

    @include('layout.expense-tabs')

    @php
        $kindMeta = [
            'subscription' => ['Subscription', 'k-subscription'],
            'renewal'      => ['Renewal',      'k-renewal'],
            'emi'          => ['EMI',          'k-emi'],
        ];
        // Personal paperwork is counted apart from everything else. "All" means
        // all COMPANY commitments — this is the expense desk, and a director's
        // passport is not a company obligation, only a date to be reminded of.
        $companyRows = array_filter($rows, fn ($r) => $r['scope'] === 'company');

        $counts = [
            'company'      => count($companyRows),
            'subscription' => count(array_filter($companyRows, fn ($r) => $r['kind'] === 'subscription')),
            'renewal'      => count(array_filter($companyRows, fn ($r) => $r['kind'] === 'renewal')),
            'overdue'      => count(array_filter($companyRows, fn ($r) => $r['due_state'] === 'overdue')),
            'paid'         => count(array_filter($companyRows, fn ($r) => $r['paid'])),
            'personal'     => count($rows) - count($companyRows),
        ];

        // What a currency prints as. Only the two DM actually uses are special-cased;
        // anything else falls back to its code, which is never wrong — printing a
        // taka sign in front of a US dollar figure would be.
        $symbol = fn (string $currency) => match ($currency) {
            'BDT'   => '৳',
            'USD'   => '$',
            default => $currency . ' ',
        };
    @endphp

    <div class="sub-shell" id="subDesk">

        @if ($dmFileMode)
            {{-- The single most misleading state this page can be in: every figure
                 looks live and none of it is. Worth shouting about. --}}
            <div class="sub-preview is-down">
                <i class="fas fa-triangle-exclamation"></i>
                <div>
                    <strong>Not the live register.</strong>
                    <code>DM_USE_FILE_DATA=true</code> is set, so the rows below are being read from the
                    sample files in <code>app/Services/</code> — not from DM. Anything recorded against
                    them will be a real expense filed against fictional renewals. Set
                    <code>DM_API_URL</code> and <code>DM_API_TOKEN</code>, then remove that flag.
                </div>
            </div>
        @elseif ($dmFailed)
            <div class="sub-preview is-down">
                <i class="fas fa-plug-circle-xmark"></i>
                <div>
                    <strong>DM could not be reached.</strong>
                    This table is empty because the register is unavailable, <em>not</em> because nothing
                    is due — do not read it as "nothing to pay". The rest of the expense desk is
                    unaffected. Check <code>DM_API_URL</code> / <code>DM_API_TOKEN</code> and the log.
                </div>
            </div>
        @endif

        <div class="sub-card">

            <div class="sub-head">
                <div>
                    <h2><i class="fas fa-arrows-rotate mr-2"></i>Subscriptions &amp; Renewals</h2>
                    <div class="sub-subtext">
                        Live from DM · what falls due next, and which expense paid it.
                    </div>
                </div>
                <div class="sub-head-actions">
                    {{-- DM's API is read-only, so a commitment cannot be created from
                         here. The button goes where it can actually be added. --}}
                    <a href="{{ $dmPortalUrl }}" target="_blank" rel="noopener noreferrer"
                       class="sub-btn sub-btn-add" title="DM is the register — commitments are added there">
                        <i class="fas fa-arrow-up-right-from-square"></i>Add in DM
                    </a>
                </div>
            </div>

            {{-- The due strip sits above the KPIs on purpose: "what have I missed"
                 is a more urgent question than "what does this cost me". --}}
            <div class="sub-due-strip">
                <div class="sub-due is-overdue">
                    <span class="sub-due-ico"><i class="fas fa-circle-exclamation"></i></span>
                    <div>
                        <div class="sub-due-n">{{ $summary['overdue'] }}</div>
                        <div class="sub-due-l">Overdue</div>
                    </div>
                </div>
                <div class="sub-due is-soon">
                    <span class="sub-due-ico"><i class="fas fa-hourglass-half"></i></span>
                    <div>
                        <div class="sub-due-n">{{ $summary['due_7'] }}</div>
                        <div class="sub-due-l">Due in 7 days</div>
                    </div>
                </div>
                <div class="sub-due is-later">
                    <span class="sub-due-ico"><i class="fas fa-calendar-check"></i></span>
                    <div>
                        <div class="sub-due-n">{{ $summary['due_30'] }}</div>
                        <div class="sub-due-l">Due in 30 days</div>
                    </div>
                </div>
            </div>

            <div class="sub-stats">
                <div class="sub-stat">
                    <div class="sub-stat-l">Active Commitments</div>
                    <div class="sub-stat-v">{{ $summary['active'] }}</div>
                    <div class="sub-stat-n">Closed records excluded</div>
                </div>
                <div class="sub-stat is-teal">
                    <div class="sub-stat-l">Monthly Run-rate</div>
                    <div class="sub-stat-v">৳ {{ number_format($summary['monthly'], 0) }}</div>
                    {{-- The exclusion is stated because there is no FX source here.
                         A run-rate that quietly dropped the dollar subscriptions
                         would be the wrong number with nothing to show it. --}}
                    <div class="sub-stat-n">
                        BDT cycles only
                        @if ($summary['foreign_count'] > 0)
                            · {{ $summary['foreign_count'] }} in foreign currency excluded
                        @endif
                    </div>
                </div>
                <div class="sub-stat is-amber">
                    <div class="sub-stat-l">Annual Commitment</div>
                    <div class="sub-stat-v">৳ {{ number_format($summary['annual'], 0) }}</div>
                    <div class="sub-stat-n">Run-rate × 12</div>
                </div>
                {{-- The figure that makes the join to the ledger visible: what this
                     desk has actually put in the accounts, against what DM says is
                     due. EMI used to sit here; instalments live on the financing
                     desk and are not duplicated. --}}
                <div class="sub-stat is-slate">
                    <div class="sub-stat-l">Recorded in Ledger</div>
                    <div class="sub-stat-v">৳ {{ number_format($summary['recorded'], 0) }}</div>
                    <div class="sub-stat-n">{{ $summary['recorded_count'] }} period(s) linked to an expense</div>
                </div>
            </div>

            <div class="sub-toolbar">
                {{-- Each chip matches ONE tag stamped on the row below, so another
                     filter is a chip plus a tag — no change to the script. --}}
                <div class="sub-chips">
                    <button type="button" class="sub-chip is-active" data-sub-filter="company">
                        All <span class="sub-chip-n">{{ $counts['company'] }}</span>
                    </button>
                    <button type="button" class="sub-chip" data-sub-filter="subscription">
                        <i class="fas fa-repeat"></i> Subscriptions <span class="sub-chip-n">{{ $counts['subscription'] }}</span>
                    </button>
                    <button type="button" class="sub-chip" data-sub-filter="renewal">
                        <i class="fas fa-file-signature"></i> Renewals <span class="sub-chip-n">{{ $counts['renewal'] }}</span>
                    </button>
                    <button type="button" class="sub-chip is-danger" data-sub-filter="overdue">
                        <i class="fas fa-circle-exclamation"></i> Overdue <span class="sub-chip-n">{{ $counts['overdue'] }}</span>
                    </button>
                    <button type="button" class="sub-chip" data-sub-filter="paid">
                        <i class="fas fa-circle-check"></i> Paid <span class="sub-chip-n">{{ $counts['paid'] }}</span>
                    </button>
                    {{-- Kept out of every other chip and out of every figure above:
                         these are somebody's own papers, tracked for the date alone.
                         Reachable, but never payable from company money. --}}
                    <button type="button" class="sub-chip is-muted" data-sub-filter="personal"
                            title="Personal paperwork — reminder only, no expense can be filed">
                        <i class="fas fa-user"></i> Personal <span class="sub-chip-n">{{ $counts['personal'] }}</span>
                    </button>
                    {{-- No EMI chip: DM carries no instalments, and the financing
                         desk already owns them (App\Models\FinancingLoan) with the
                         principal/interest split and its own journals. A chip that
                         is always empty reads as "we have no EMI". --}}
                </div>
                <div class="sub-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="subSearch" placeholder="Search a service, company or category…" autocomplete="off">
                </div>
            </div>

            <div class="sub-table-wrap">
                <table class="sub-table">
                    <thead>
                        <tr>
                            <th>Commitment</th>
                            <th>Kind</th>
                            <th>What it's for</th>
                            <th class="sub-num">Amount</th>
                            <th>Next due</th>
                            <th>Paid from</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="subRows">
                        @forelse ($rows as $row)
                            @php
                                // Company rows carry their kind and state as well;
                                // personal rows carry ONLY 'personal', which is what
                                // keeps them out of every other chip.
                                $tags = $row['scope'] === 'company'
                                    ? array_filter(['company', $row['kind'], $row['due_state'] === 'overdue' ? 'overdue' : null, $row['paid'] ? 'paid' : null])
                                    : ['personal'];
                            @endphp
                            <tr class="{{ $row['paid'] ? 'sub-paid-row' : '' }}"
                                data-sub-tags="{{ implode(' ', $tags) }}"
                                data-sub-text="{{ Str::lower($row['name'] . ' ' . $row['company'] . ' ' . $row['category'] . ' ' . $row['sub_category'] . ' ' . $row['owner']) }}">

                                <td>
                                    <div class="sub-name">
                                        @if ($row['critical'])
                                            <span class="sub-crit" title="Critical — something stops working if this lapses"></span>
                                        @endif
                                        {{ $row['name'] }}
                                    </div>
                                    {{-- DM has no vendor field, so this line is whatever
                                         context there actually is rather than an empty
                                         separator hanging off a bullet. --}}
                                    @if ($row['company'])
                                        <div class="sub-vendor">{{ $row['company'] }}</div>
                                    @endif
                                </td>

                                <td>
                                    <span class="sub-kind {{ $kindMeta[$row['kind']][1] }}">{{ $kindMeta[$row['kind']][0] }}</span>
                                </td>

                                <td>
                                    <div class="sub-for">{{ $row['category'] }}</div>
                                    @if ($row['sub_category'])
                                        <div class="sub-for-sub">{{ $row['sub_category'] }}</div>
                                    @endif
                                </td>

                                <td class="sub-num">
                                    {{-- Printed in the currency DM billed it in. Nothing
                                         here converts anything, so a taka sign in front
                                         of a dollar figure would simply be false. --}}
                                    @if ($row['amount'] > 0)
                                        <div class="sub-amt">{{ $symbol($row['currency']) }} {{ number_format($row['amount'], 2) }}</div>
                                    @else
                                        <div class="sub-amt" title="DM carries no cost for this record">—</div>
                                    @endif
                                    <div class="sub-amt-note">
                                        {{ $row['cycle'] }}@if ($row['amount_note']) · {{ $row['amount_note'] }} @endif
                                    </div>
                                </td>

                                <td>
                                    <div class="sub-due-cell">{{ $row['next_due_label'] }}</div>
                                    <span class="sub-due-tag t-{{ $row['due_state'] }}">{{ $row['due_note'] }}</span>
                                </td>

                                <td>
                                    {{-- Empty until an expense exists, which is exactly
                                         what it should say: nobody has paid this yet. --}}
                                    <div class="sub-for">{{ $row['paid_from'] ?: '—' }}</div>
                                    @if ($row['owner'])
                                        <div class="sub-for-sub">{{ $row['owner'] }}</div>
                                    @endif
                                </td>

                                <td>
                                    <span class="sub-status s-{{ Str::lower($row['status']) }}">{{ $row['status'] }}</span>
                                </td>

                                <td>
                                    <div class="sub-act">
                                        @if ($row['paid'])
                                            <a href="{{ $row['expense_url'] }}" class="sub-seen"
                                               title="Open the expense that settled this period">
                                                <i class="fas fa-circle-check"></i>Paid
                                            </a>
                                        @elseif ($row['record_url'])
                                            <a href="{{ $row['record_url'] }}" class="sub-rec"
                                               title="File the expense for this period, pre-filled from DM">
                                                <i class="fas fa-receipt"></i>Record Expense
                                            </a>
                                        @else
                                            <span class="sub-note"
                                                  title="Filed under a personal folder in DM, so it is tracked for the date only. Paying it from company money would be drawings, not an expense.">
                                                <i class="fas fa-bell"></i>Reminder only
                                            </span>
                                        @endif

                                        @if ($row['link_url'])
                                            <a href="{{ $row['link_url'] }}" target="_blank" rel="noopener noreferrer"
                                               class="sub-open" title="Open the linked item"><i class="fas fa-external-link-alt"></i></a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            {{-- No @if wrapper around the table: an empty register and a
                                 failed fetch look identical here on purpose, and the
                                 banner above is what tells them apart. --}}
                            <tr>
                                <td colspan="8" style="text-align:center;color:#94a3b8;padding:34px 12px;font-size:12.5px">
                                    @if ($dmFailed)
                                        DM is unreachable, so nothing can be listed.
                                    @else
                                        DM returned no subscriptions or document renewals.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="sub-empty" id="subEmpty">
                    <i class="fas fa-arrows-rotate"></i>
                    <h4>Nothing matches that</h4>
                    <p>Try another kind, or clear the search.</p>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('js')
<script>
/*
 * Scoped to #subDesk and nothing else. Filtering only — every action on this
 * page is a plain link, so there is nothing here to submit or intercept.
 *
 * No document-level listeners, no shared helper touched, no global left behind —
 * the whole thing is an IIFE, and every query is rooted at an element this page
 * owns. If this script throws, it throws inside itself: the sidebar, the header
 * and the tab band above are plain server-rendered HTML and do not depend on it.
 */
(function () {
    'use strict';

    var desk = document.getElementById('subDesk');
    if (!desk) return;                       // page not on screen — nothing to wire

    var rowsBody = document.getElementById('subRows');
    var emptyBox = document.getElementById('subEmpty');
    var searchEl = document.getElementById('subSearch');
    var chips    = desk.querySelectorAll('[data-sub-filter]');
    // Matches the chip marked is-active in the markup: the desk opens on the
    // company commitments, with personal paperwork one chip away.
    var tag      = 'company';

    function applyFilter() {
        var term = (searchEl && searchEl.value ? searchEl.value : '').trim().toLowerCase();
        var rows = rowsBody ? rowsBody.querySelectorAll('tr') : [];
        var shown = 0;

        Array.prototype.forEach.call(rows, function (row) {
            // Whole-word match on the tag list, so 'paid' can never be matched
            // inside another tag as a substring would allow.
            var tags = (row.getAttribute('data-sub-tags') || '').split(' ');
            var matchesTag  = tags.indexOf(tag) !== -1;
            var matchesText = !term || (row.getAttribute('data-sub-text') || '').indexOf(term) !== -1;
            var visible = matchesTag && matchesText;

            row.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });

        if (emptyBox) emptyBox.style.display = shown === 0 ? 'block' : 'none';
    }

    Array.prototype.forEach.call(chips, function (chip) {
        chip.addEventListener('click', function () {
            Array.prototype.forEach.call(chips, function (c) { c.classList.remove('is-active'); });
            chip.classList.add('is-active');
            tag = chip.getAttribute('data-sub-filter');
            applyFilter();
        });
    });

    if (searchEl) searchEl.addEventListener('input', applyFilter);
})();
</script>
@endsection
