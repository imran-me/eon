@extends('layout.app')
@section('meta-information')<title>Visa Accounts</title>@endsection
@section('css')
@include('contract-flights.partials.accounts-styles')
@include('party-statement.partials.bar-styles')
@endsection
@section('main-content')
@php
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $safeRoute = function ($name, $params = []) use ($role) {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name, ['role' => $role] + $params) : null; } catch (\Throwable) { return null; }
    };
    $partyUrl = $safeRoute('role.party-statement.index');
    $saleUrl = fn ($id) => $safeRoute('role.visa-sales.show', ['visaSale' => $id]);
    $txLabels = ['visa_sale' => ['Visa invoice', 'in'], 'visa_process' => ['Visa cost', 'out'], 'party_payment' => ['Payment', 'pay']];
    $srcLabels = ['visa_sale' => 'Visa invoice', 'visa_process' => 'Visa cost'];
    $plPax = $plRows->map(fn ($r) => ['fk' => $r['key'], 'n' => $r['tickets'], 'agent' => $r['agent'], 'month' => $r['month'], 'earned' => $r['earned'], 'inc' => false]
        + collect($r)->only(['package', 'billed', 'transfer', 'penalty', 'revenue', 'ticket_cost', 'service_cost', 'change_cost', 'cost', 'profit', 'agent_paid', 'agent_due', 'vendor_paid'])->all())->values();
    // every month but the current one, newest first — the current month is the top of the tab
    $plPastMonths = $plRows->pluck('month')->filter()->unique()->reject(fn ($mo) => $mo === now()->format('Y-m'))->sortDesc()->values();
    $plHead = '<tr><th data-nosort></th><th>Invoice</th><th>Agent</th><th>Visa / service</th><th class="num">Items</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Agent due</th><th>Status</th></tr>';
    $unbilled = ($dept['ok'] ?? false) ? $dept['unbilled'] : collect();
    $plStock = $unbilled->map(fn ($u) => [
        'pnr' => $u['ref'] . ($u['passenger'] ? ' · ' . $u['passenger'] : '') . ' · ' . ucwords(str_replace('_', ' ', (string) $u['status'])),
        'date' => $u['date'], 'amount' => $u['price'],
    ])->values();
@endphp

@include('layout.visa-processing-tabs')

<div class="cfa-wrap">
    @include('dashboard.visa_accounts.partials.top')

    {{-- ── Profit & Loss ── --}}
    <section class="cfa-panel" data-panel="pl">
        {{-- Opens on the current month; a past month opens this same card under
             its row in Past months (accounts-pl-scripts copies it). --}}
        <div class="cfa-card cfa-pl-stmt">
            <div class="cfa-card-h"><h3>Profit &amp; loss statement</h3><small id="plScope">This month</small></div>
            <div class="cfa-pls">
                <div class="cfa-pls-col">
                    <div class="cfa-pls-h in">Revenue</div>
                    <div class="cfa-pls-r"><span title="Manage Sales invoices — what the agents are charged">Invoiced to agents</span><b data-pl="package"></b></div>
                    <div class="cfa-pls-t in"><span>Total revenue</span><b data-pl="revenue"></b></div>
                </div>
                <div class="cfa-pls-col">
                    <div class="cfa-pls-h out">Cost</div>
                    <div class="cfa-pls-r"><span title="Costing price of each application and other service on those invoices">Applications &amp; services</span><b data-pl="ticket_cost"></b></div>
                    <div class="cfa-pls-t out"><span>Total cost</span><b data-pl="cost"></b></div>
                </div>
                <div class="cfa-pls-col">
                    <div class="cfa-pls-h good" title="Gross — before overheads and tax">Result</div>
                    <div class="cfa-pls-big"><span>Gross profit</span><b data-pl="profit"></b><small><em data-pl="margin"></em> margin · <em data-pl="flights"></em> invoices · <em data-pl="pax"></em> items</small></div>
                    <div class="cfa-pls-r"><span title="Every application on the invoice is approved or rejected">Earned</span><b data-pl="earned_profit"></b></div>
                    <div class="cfa-pls-r"><span title="Applications still being processed — profit expected, not earned">Booked</span><b data-pl="booked_profit"></b></div>
                </div>
            </div>
            <div class="cfa-pls-warn" id="plWarn" hidden><span data-pl="inc_pax"></span><span data-pl="inc_revenue"></span><span data-pl="clean_margin"></span></div>
            <div class="cfa-pls-cash">
                <div><span>Received from agents</span><b class="cfa-good" data-pl="agent_paid"></b></div>
                <div><span title="Paid to the vendors of the applications on these invoices">Paid to vendors</span><b class="cfa-out" data-pl="vendor_paid"></b></div>
                <div><span>Cash position</span><b data-pl="cash"></b></div>
                <div><span>Still due from agents</span><b class="cfa-due" data-pl="agent_due"></b></div>
                <div><span title="On the invoiced work">Still owed to vendors</span><b class="cfa-due" data-pl="vendor_due"></b></div>
            </div>
            <div class="cfa-pls-out" data-dd-stock data-dd-title="Not invoiced yet" data-dd-sub="Applications and services on no invoice — work done, not charged" data-dd-heads="Item|Opened|Sale price" title="Show what is not invoiced yet">
                <span class="h">Not invoiced · all months</span>
                <span>Work not billed <b class="cfa-dd-link {{ $overview['unbilled_price'] > 0 ? 'cfa-due' : '' }}">{{ $m($overview['unbilled_price']) }}</b> · {{ $overview['unbilled_count'] }} items · cost {{ $m($overview['unbilled_cost']) }}</span>
            </div>
            @if($gl !== null)
            @php $glDiff = (float) $plRows->sum('revenue') - $gl; @endphp
            <div class="cfa-pls-gl" title="Ledger = Visa Service Income (4120) on the live journal lines of these invoices.{{ $noJournal->count() ? ' ' . $noJournal->count() . ' invoice(s) have no journal entry: ' . $noJournal->pluck('invoice')->implode(', ') : '' }}">
                <i class="fas fa-book"></i>
                <span>Books check · all months · invoice revenue <b class="{{ abs($glDiff) < 1 ? 'cfa-good' : 'cfa-due' }}">{{ abs($glDiff) < 1 ? 'matches ledger' : 'off by ' . $m($glDiff) }}</b>@if($noJournal->count()) · <b class="cfa-due">{{ $noJournal->count() }} invoice{{ $noJournal->count() === 1 ? '' : 's' }} not posted to the ledger</b>@endif</span>
            </div>
            @endif
        </div>

        <div class="cfa-pl-2 cfa-pl-side">
            <div class="cfa-card">
                <div class="cfa-card-h"><h3>By agent</h3></div>
                <div class="cfa-scroll"><table class="cfa-table cfa-pl-agents" id="plAgents">
                    <thead><tr><th>Agent</th><th class="num">Items</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Due</th></tr></thead>
                    <tbody></tbody>
                </table></div>
            </div>
            <div class="cfa-card">
                <div class="cfa-card-h"><h3>Where profit is lost</h3></div>
                <div class="cfa-pl-leak" hidden><b data-pl="net_changes"></b></div>
                <div class="cfa-scroll"><table class="cfa-table cfa-pl-losses" id="plLosses">
                    <thead><tr><th>Loss-making invoice</th><th class="num">Revenue</th><th class="num">—</th><th class="num">Loss</th></tr></thead>
                    <tbody></tbody>
                </table></div>
            </div>
        </div>

        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Invoice by invoice</h3><small>Click a row for its applications and services</small></div>
            @include('party-statement.partials.table-bar', [
                'prefix' => 'pl', 'placeholder' => 'Search invoice, agent, application, passenger, country or vendor…',
                'segments' => [['all', 'All'], ['earned', 'Done'], ['booked', 'In process'], ['profit', 'Profit'], ['loss', 'Loss'], ['due', 'Agent due']],
                'dates' => true, 'print' => true,
            ])
            <div class="cfa-scroll"><table class="cfa-table" id="plTable" data-cf-sort data-now="{{ now()->format('Y-m') }}" data-row="invoice" data-unit="item" data-earned="Done (earned)" data-booked="In process (booked)">
                <thead>{!! $plHead !!}</thead>
                <tbody>
                @forelse($plRows as $r)
                    @include('dashboard.visa_accounts.partials.pl-row')
                @empty
                    <tr><td colspan="11" class="cfa-empty"><i class="fas fa-file-invoice"></i>No invoices yet — visas are billed from Manage Sales.</td></tr>
                @endforelse
                <tr id="plNone" hidden><td colspan="11" class="cfa-empty"><i class="fas fa-filter"></i>Nothing matches.</td></tr>
                </tbody>
                <tfoot class="cfa-pl-foot"><tr><td></td><td colspan="3">Total · <span data-pl="flights"></span> invoices</td><td class="num" data-pl="pax"></td><td class="num" data-pl="revenue"></td><td class="num" data-pl="cost"></td><td class="num" data-pl="profit"></td><td class="num" data-pl="margin"></td><td class="num" data-pl="agent_due"></td><td></td></tr></tfoot>
            </table></div>
        </div>

        {{-- Past months (owner, 2026-10-15): everything above is the current
             month; opening a month here shows its whole P&L — statement, by
             agent, losses, invoices. --}}
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Past months</h3><small>Click a month for its full profit &amp; loss</small></div>
            <div class="cfa-scroll"><table class="cfa-table" id="plMonths">
                <thead><tr><th>Month</th><th class="num">Invoices</th><th class="num">Items</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Agent due</th></tr></thead>
                <tbody>
                @forelse($plPastMonths as $mo)
                    @php $monthName = \Illuminate\Support\Carbon::createFromFormat('Y-m', $mo)->format('F Y'); @endphp
                    <tr class="cfa-pl-month" tabindex="0" data-month="{{ $mo }}" data-label="{{ $monthName }}" title="Show {{ $monthName }}'s profit &amp; loss">
                        <td class="b"><i class="fas fa-chevron-right cfa-pl-mi"></i> {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $mo)->format('M Y') }}</td>
                        <td class="num" data-mc="flights"></td><td class="num" data-mc="pax"></td><td class="num cfa-in" data-mc="revenue"></td><td class="num cfa-out" data-mc="cost"></td>
                        <td class="num" data-mc="profit"></td><td class="num cfa-mg" data-mc="margin"></td><td class="num" data-mc="agent_due"></td>
                    </tr>
                    <tr class="cfa-jr cfa-pl-mdetail" hidden><td colspan="8">
                        <div class="cfa-pl-mscope" data-month="{{ $mo }}"></div>
                        <div class="cfa-card cfa-pl-mrows">
                            <div class="cfa-card-h"><h3>Invoice by invoice</h3><small>{{ $monthName }} · click a row for its applications and services</small></div>
                            <div class="cfa-scroll"><table class="cfa-table cfa-pl-nested">
                                <thead>{!! $plHead !!}</thead>
                                <tbody>
                                @foreach($plRows->where('month', $mo) as $r)
                                    @include('dashboard.visa_accounts.partials.pl-row')
                                @endforeach
                                </tbody>
                            </table></div>
                        </div>
                    </td></tr>
                @empty
                    <tr><td colspan="8" class="cfa-empty"><i class="fas fa-calendar"></i>No earlier months yet.</td></tr>
                @endforelse
                </tbody>
                @if($plPastMonths->isNotEmpty())
                <tfoot class="cfa-pl-foot"><tr><td>Total · <span data-mt="months"></span></td><td class="num" data-mt="flights"></td><td class="num" data-mt="pax"></td><td class="num" data-mt="revenue"></td><td class="num" data-mt="cost"></td><td class="num" data-mt="profit"></td><td class="num" data-mt="margin"></td><td class="num" data-mt="agent_due"></td></tr></tfoot>
                @endif
            </table></div>
        </div>
        <script>
            window.PL_PAX = @json($plPax);
            window.PL_STOCK = @json($plStock);
        </script>
    </section>

    {{-- ── Transactions ── --}}
    <section class="cfa-panel" data-panel="tx" hidden>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Party ledger transactions</h3><small>Visa costs, invoices and the payments on them as they sit on each party's statement · latest first · up to 400</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Date</th><th>Party</th><th>Type</th><th>Reference</th><th>Remarks</th><th>Account</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
                <tbody>
                @forelse($transactions as $t)
                    @php [$label, $dir] = $txLabels[$t->type] ?? [\Illuminate\Support\Str::headline($t->type), 'muted']; @endphp
                    <tr>
                        <td>{{ $d($t->payment_date) }}</td>
                        <td class="b">@if($t->user_id && $partyUrl)<a href="{{ $partyUrl }}?party_id={{ $t->user_id }}">{{ $t->user?->name ?? '—' }}</a>@else{{ $t->user?->name ?? '—' }}@endif<small>{{ $t->user_type ? ucfirst($t->user_type) : '' }}</small></td>
                        <td><span class="cfa-pill {{ ['in' => '', 'out' => 'warn', 'pay' => 'ok'][$dir] ?? 'muted' }}">{{ $label }}</span></td>
                        <td>{{ $t->reference_no ?: '—' }}</td>
                        <td>{{ $t->remarks ?: '—' }}</td>
                        <td>{{ $t->account?->name ?? ($t->payment_method ?: '—') }}</td>
                        <td class="num {{ $t->debit > 0 ? 'cfa-in' : '' }}">{{ $t->debit > 0 ? $m($t->debit) : '—' }}</td>
                        <td class="num {{ $t->credit > 0 ? 'cfa-good' : '' }}">{{ $t->credit > 0 ? $m($t->credit) : '—' }}</td>
                        <td class="num">{{ $m($t->balance) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-right-left"></i>No ledger transactions yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>

    {{-- ── Journal history ── --}}
    <section class="cfa-panel" data-panel="journal" hidden>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Journal entries</h3><small>The general-ledger postings behind these applications and invoices — each with its debit and credit lines · latest first · up to 200</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Date</th><th>Reference</th><th>Source</th><th>Description</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse($journal as $j)
                    <tr>
                        <td>{{ $d($j->date) }}<small>#{{ $j->id }}</small></td>
                        <td class="b">{{ $j->reference ?: '—' }}</td>
                        <td><span class="cfa-pill muted">{{ $srcLabels[$j->source] ?? \Illuminate\Support\Str::headline($j->source) }}</span></td>
                        <td>{{ $j->description ?: '—' }}</td>
                        <td class="num">{{ $m($j->items->sum('debit')) }}</td>
                    </tr>
                    <tr class="cfa-jr"><td colspan="5"><table>
                        @foreach($j->items as $it)
                            <tr><td class="acc" style="width:90px">{{ $it->account?->code }}</td><td>{{ $it->account?->name ?? 'Account #' . $it->account_id }}{{ $it->note ? ' — ' . $it->note : '' }}</td><td class="num" style="width:130px">{{ $it->debit > 0 ? 'Dr ' . $m($it->debit) : '' }}</td><td class="num" style="width:130px">{{ $it->credit > 0 ? 'Cr ' . $m($it->credit) : '' }}</td></tr>
                        @endforeach
                    </table></td></tr>
                @empty
                    <tr><td colspan="5" class="cfa-empty"><i class="fas fa-book"></i>No journal entries yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>
</div>
@endsection
@section('script')
@include('contract-flights.partials.table-sort')
<script>
@include('party-statement.partials.bar-scripts')
@include('contract-flights.partials.accounts-pl-scripts')
// Tabs: one sheet at a time, remembered in the hash.
(function () {
    const tabs = document.querySelectorAll('button.cfa-tab');
    const panels = document.querySelectorAll('.cfa-panel');
    function show(name) {
        let found = false;
        panels.forEach(p => { const on = p.dataset.panel === name; p.hidden = !on; found = found || on; });
        if (!found) return show('pl');
        tabs.forEach(t => { const on = t.dataset.tab === name; t.classList.toggle('is-on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
    }
    tabs.forEach(t => t.addEventListener('click', () => { show(t.dataset.tab); history.replaceState(null, '', '#' + t.dataset.tab); }));
    show((location.hash || '#pl').slice(1));
})();
</script>
@endsection
