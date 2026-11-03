{{--
    Account report toolbar — TWO levels, by what each report is FOR.

    Fourteen flat tabs were too many, and several were one set of data seen three ways (General Ledger /
    Account Ledger / Account Statement; Account Balance = Trial Balance again; Journal Entries = Accounts →
    Manage Journals). Now four main tabs; the reports of the one you are in show as a thin row beneath:

      Statements              Profit & Loss · Balance Sheet · Cash Flow · Trial Balance
      Receivables & Payables  AR Aging · AP Aging
      Analysis                Monthly Report · Expenses by Category · Service Profitability
      Ledgers                 General Ledger · Account Ledger · Account Statement · Account Balance · Journal Entries

    Nothing was removed: every route, permission and drill-down link is unchanged. A main tab opens the
    first report of its group; a group a user has no permission for at all is simply not shown.

    Prefixed .rp- (the band family of layout/bank-tabs and layout/expense-tabs). Self-contained: it resolves
    its own $role and prefixes locals with reportTab*, because the including views define their own
    $company, $accounts and $role further down. No script — links only.
--}}
@php
    $reportTabUser = auth()->user();
    $reportTabRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(optional($reportTabUser)->getRoleNames()->first() ?? '');

    // group: statements | arap | analysis | ledger
    $reportTabItems = [
        ['group' => 'statements', 'label' => 'Profit & Loss',        'icon' => 'fa-chart-line',           'route' => 'role.report.profit-loss',          'permission' => 'view profit loss report',       'active' => ['role.report.profit-loss', 'role.report.profit-loss.*']],
        ['group' => 'statements', 'label' => 'Balance Sheet',        'icon' => 'fa-file-invoice-dollar',  'route' => 'role.report.balance-sheet',        'permission' => 'view balance sheet report',     'active' => ['role.report.balance-sheet', 'role.report.balance-sheet.*']],
        ['group' => 'statements', 'label' => 'Cash Flow',            'icon' => 'fa-money-bill-transfer',  'route' => 'role.report.cash-flow',            'permission' => 'view balance sheet report',     'active' => ['role.report.cash-flow*']],
        ['group' => 'statements', 'label' => 'Trial Balance',        'icon' => 'fa-scale-balanced',       'route' => 'role.report.trial-balance',        'permission' => 'view trial balance report',     'active' => ['role.report.trial-balance']],

        ['group' => 'arap',       'label' => 'AR Aging',             'icon' => 'fa-hand-holding-dollar',  'route' => 'role.report.ar-aging',             'permission' => 'view account statement report', 'active' => ['role.report.ar-aging*']],
        ['group' => 'arap',       'label' => 'AP Aging',             'icon' => 'fa-file-invoice',         'route' => 'role.report.ap-aging',             'permission' => 'view account statement report', 'active' => ['role.report.ap-aging*']],

        ['group' => 'analysis',   'label' => 'Monthly Report',       'icon' => 'fa-gauge-high',           'route' => 'role.report.monthly-report',       'permission' => 'view balance sheet report',     'active' => ['role.report.monthly-report*']],
        ['group' => 'analysis',   'label' => 'Expenses by Category', 'icon' => 'fa-chart-pie',            'route' => 'role.report.expenses-by-category', 'permission' => 'view profit loss report',       'active' => ['role.report.expenses-by-category*']],
        ['group' => 'analysis',   'label' => 'Service Profitability', 'icon' => 'fa-chart-column',        'route' => 'role.report.monthly-profit',       'permission' => 'view profit loss report',       'active' => ['role.report.monthly-profit*']],

        ['group' => 'ledger',     'label' => 'General Ledger',       'icon' => 'fa-book',                 'route' => 'role.report.general-ledger',       'permission' => 'view general ledger report',    'active' => ['role.report.general-ledger']],

        ['group' => 'ledger',     'label' => 'Account Ledger',       'icon' => 'fa-book-open',            'route' => 'role.report.account-ledger',       'permission' => 'view account ledger report',    'active' => ['role.report.account-ledger'],    'note' => 'one account, with running balance'],
        ['group' => 'ledger',     'label' => 'Account Statement',    'icon' => 'fa-file-lines',           'route' => 'role.report.account-statement',    'permission' => 'view account statement report', 'active' => ['role.report.account-statement'], 'note' => 'one account, statement layout'],
        ['group' => 'ledger',     'label' => 'Account Balance',      'icon' => 'fa-wallet',               'route' => 'role.report.account-balances',     'permission' => 'view account balance report',   'active' => ['role.report.account-balances'],  'note' => 'balances per account (see Trial Balance)'],
        ['group' => 'ledger',     'label' => 'Journal Entries',      'icon' => 'fa-pen-to-square',        'route' => 'role.report.journal-entries',      'permission' => 'view journal entry report',     'active' => ['role.report.journal-entries'],   'note' => 'also under Accounts → Manage Journals'],
    ];

    $reportTabGroups = [
        'statements' => 'Statements',
        'arap'       => 'Receivables & Payables',
        'analysis'   => 'Analysis',
        'ledger'     => 'Ledgers',
    ];

    $reportTabLinks = [];          // group => [links]
    $reportTabCount = 0;

    foreach ($reportTabItems as $reportTabItem) {
        if ($reportTabItem['permission'] && ! $reportTabUser?->can($reportTabItem['permission'])) {
            continue;
        }

        // A missing route must never take the whole page down with it.
        try {
            $reportTabItem['url'] = route($reportTabItem['route'], ['role' => $reportTabRole]);
        } catch (\Throwable $e) {
            continue;
        }

        $reportTabItem['is_active'] = request()->routeIs(...$reportTabItem['active']);
        $reportTabCount++;
        $reportTabLinks[$reportTabItem['group']][] = $reportTabItem;
    }

    // The group you are in (its reports show beneath); a main tab opens its group's first report.
    $reportTabActiveGroup = null;
    foreach ($reportTabLinks as $reportTabKey => $reportTabList) {
        foreach ($reportTabList as $reportTabLink) {
            if ($reportTabLink['is_active']) {
                $reportTabActiveGroup = $reportTabKey;
            }
        }
    }
@endphp

@if($reportTabCount > 1)
<div class="rp-toolbar no-print">
    <nav class="rp-navband" aria-label="Account report sections">
        @foreach($reportTabGroups as $reportTabKey => $reportTabCaption)
            @if(! empty($reportTabLinks[$reportTabKey]))
            @php $reportTabOn = $reportTabActiveGroup === $reportTabKey; @endphp
            <a href="{{ $reportTabLinks[$reportTabKey][0]['url'] }}"
               class="rp-navtab {{ $reportTabOn ? 'is-active' : '' }}" @if($reportTabOn) aria-current="page" @endif>
                <span>{{ $reportTabCaption }}</span>
                <b class="rp-navcount">{{ count($reportTabLinks[$reportTabKey]) }}</b>
            </a>
            @endif
        @endforeach
    </nav>

    @if($reportTabActiveGroup && count($reportTabLinks[$reportTabActiveGroup]) > 1)
    <nav class="rp-subband" aria-label="{{ $reportTabGroups[$reportTabActiveGroup] }} reports">
        @foreach($reportTabLinks[$reportTabActiveGroup] as $reportTabLink)
            <a href="{{ $reportTabLink['url'] }}" class="rp-subtab {{ $reportTabLink['is_active'] ? 'is-active' : '' }}"
               title="{{ $reportTabLink['note'] ?? $reportTabLink['label'] }}" @if($reportTabLink['is_active']) aria-current="page" @endif>
                <i class="fas {{ $reportTabLink['icon'] }}"></i><span>{{ $reportTabLink['label'] }}</span>
            </a>
        @endforeach
    </nav>
    @endif
</div>

<style>
    /* Same band family as layout/bank-tabs.blade.php and layout/expense-tabs.blade.php. */
    .rp-toolbar{margin:0 0 16px;}
    /* Pages that nest their content in a `p-4 md:p-6` wrapper start 36px down
       (main's p-3 + the wrapper's own padding). Pull those back so every report
       page's band sits at the same height. */
    .p-4 > .rp-toolbar{margin-top:-16px;}
    @media(min-width:768px){ .p-4 > .rp-toolbar{margin-top:-24px;} }

    /* level 1 — the four kinds of report */
    .rp-navband{display:flex;flex-wrap:wrap;align-items:stretch;gap:0;background:#fff;
        border-bottom:1px solid #e2e8f0;border-radius:10px 10px 0 0;}
    .rp-navtab{flex:1 0 auto;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:8px;
        text-align:center;padding:12px 18px 13px;margin-bottom:-1px;border-bottom:3px solid transparent;
        font-size:13px;font-weight:800;color:#64748b;white-space:nowrap;text-decoration:none;
        transition:color .15s,border-color .15s,background .15s;}
    .rp-navtab + .rp-navtab{border-left:1px solid #e2e8f0;}
    .rp-navtab:hover{color:#0f172a;background:rgba(37,99,235,.07);text-decoration:none;}
    .rp-navtab.is-active{color:#2563eb;border-bottom-color:#2563eb;background:rgba(37,99,235,.05);}
    .rp-navcount{font-size:10.5px;font-weight:800;background:#e2e8f0;color:#64748b;border-radius:999px;padding:1px 7px;}
    .rp-navtab.is-active .rp-navcount{background:#dbeafe;color:#2563eb;}

    /* level 2 — the reports of the group you are in */
    .rp-subband{display:flex;flex-wrap:wrap;gap:6px;padding:9px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 10px 10px;margin-bottom:12px;}
    .rp-subtab{display:inline-flex;align-items:center;gap:7px;padding:6px 13px;border-radius:999px;font-size:12px;font-weight:700;color:#64748b;
        text-decoration:none;border:1px solid transparent;transition:all .15s;white-space:nowrap;}
    .rp-subtab i{font-size:11px;opacity:.85;}
    .rp-subtab:hover{background:#fff;border-color:#cbd5e1;color:#0f172a;text-decoration:none;}
    .rp-subtab.is-active{background:#2563eb;color:#fff;border-color:#2563eb;box-shadow:0 1px 3px rgba(37,99,235,.35);}
    .rp-toolbar > .rp-navband:last-child{margin-bottom:12px;border-radius:10px;}

    /* The app's dark shim recolours by class and these set their own hex. */
    html.dark .rp-navband{background:#1e293b;border-bottom-color:#334155;}
    html.dark .rp-navtab{color:#94a3b8;} html.dark .rp-navtab + .rp-navtab{border-left-color:#334155;}
    html.dark .rp-navtab:hover{color:#e2e8f0;background:rgba(59,130,246,.14);}
    html.dark .rp-navtab.is-active{color:#60a5fa;border-bottom-color:#60a5fa;background:rgba(59,130,246,.10);}
    html.dark .rp-navcount{background:#334155;color:#94a3b8;}
    html.dark .rp-subband{background:#0f172a;border-color:#334155;}
    html.dark .rp-subtab{color:#94a3b8;} html.dark .rp-subtab:hover{background:#1e293b;border-color:#475569;color:#e2e8f0;}
    html.dark .rp-subtab.is-active{background:#2563eb;color:#fff;}

    @media(max-width:700px){
        .rp-navtab{padding:10px 10px 11px;font-size:12px;flex:1 1 45%;}
        .rp-subband{flex-wrap:nowrap;overflow-x:auto;}
    }

    /* These pages print. The band is navigation, never part of the report. */
    @media print{ .rp-toolbar{display:none !important;} }
</style>
@endif
