@php
    $__reportRole = Str::slug(Auth::user()->getRoleNames()->first());
    $__reportTabs = [
        ['route' => 'role.report.general-ledger', 'match' => 'role.report.general-ledger', 'icon' => 'fa-book', 'label' => 'General Ledger', 'permission' => 'view general ledger report'],
        ['route' => 'role.report.trial-balance', 'match' => 'role.report.trial-balance', 'icon' => 'fa-scale-balanced', 'label' => 'Trial Balance', 'permission' => 'view trial balance report'],
        ['route' => 'role.report.profit-loss', 'match' => 'role.report.profit-loss', 'icon' => 'fa-chart-line', 'label' => 'Profit & Loss', 'permission' => 'view profit loss report'],
        ['route' => 'role.report.balance-sheet', 'match' => 'role.report.balance-sheet', 'icon' => 'fa-file-invoice-dollar', 'label' => 'Balance Sheet', 'permission' => 'view balance sheet report'],
        ['route' => 'role.report.account-ledger', 'match' => 'role.report.account-ledger', 'icon' => 'fa-book-open', 'label' => 'Account Ledger', 'permission' => 'view account ledger report'],
        ['route' => 'role.report.account-statement', 'match' => 'role.report.account-statement', 'icon' => 'fa-file-lines', 'label' => 'Account Statement', 'permission' => 'view account statement report'],
        ['route' => 'role.report.journal-entries', 'match' => 'role.report.journal-entries', 'icon' => 'fa-pen-to-square', 'label' => 'Journal Entries', 'permission' => 'view journal entry report'],
        ['route' => 'role.report.account-balances', 'match' => 'role.report.account-balances', 'icon' => 'fa-wallet', 'label' => 'Account Balance', 'permission' => 'view account balance report'],
    ];
@endphp
<style>
    .account-report-tabs { display:flex; flex-wrap:wrap; gap:6px; background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.07); padding:8px; margin-bottom:20px; }
    .account-report-tabs a { display:flex; align-items:center; gap:7px; padding:9px 16px; border-radius:7px; font-size:13px; font-weight:600; color:#475569; text-decoration:none; white-space:nowrap; transition:background .15s,color .15s; }
    .account-report-tabs a:hover { background:#f1f5f9; color:#1e3a5f; }
    .account-report-tabs a.active { background:#1e3a5f; color:#fff; }
    .account-report-tabs a i { font-size:12px; }
    @media print { .account-report-tabs { display:none; } }
</style>
<div class="account-report-tabs no-print">
    @foreach ($__reportTabs as $__tab)
        @can($__tab['permission'])
            <a href="{{ route($__tab['route'], ['role' => $__reportRole]) }}"
               class="{{ request()->routeIs($__tab['match']) ? 'active' : '' }}">
                <i class="fas {{ $__tab['icon'] }}"></i> {{ $__tab['label'] }}
            </a>
        @endcan
    @endforeach
</div>
