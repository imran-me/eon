{{-- Ticket Accounts — Profit & Loss tab. Same statement, filter bar and
     drill-down as Flight Accounts (accounts-pl-scripts drives both): the unit
     is the Manage Sales invoice, plus each sold-ticket change. Contract-flight
     tickets, idle stock and penalties on unsold tickets have no sales revenue
     here, so they are shown apart as "outside this profit". --}}
@php
    $plPax = $plRows->map(fn ($r) => ['fk' => $r['key'], 'n' => $r['tickets'], 'agent' => $r['agent'], 'month' => $r['month'], 'earned' => $r['earned'], 'inc' => false]
        + collect($r)->only(['package', 'billed', 'transfer', 'penalty', 'revenue', 'ticket_cost', 'service_cost', 'change_cost', 'cost', 'profit', 'agent_paid', 'agent_due', 'vendor_paid'])->all())->values();
    // every month but the current one, newest first — the current month is the top of the tab
    $plPastMonths = $plRows->pluck('month')->filter()->unique()->reject(fn ($mo) => $mo === now()->format('Y-m'))->sortDesc()->values();
    $plHead = '<tr><th data-nosort></th><th>Invoice</th><th>Agent</th><th>Route</th><th class="num">Tickets</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Agent due</th><th>Status</th></tr>';
@endphp
<section class="cfa-panel" data-panel="pl">
    {{-- Opens on the current month; a past month opens this same card under
         its row in Past months (accounts-pl-scripts copies it). --}}
    <div class="cfa-card cfa-pl-stmt">
        <div class="cfa-card-h"><h3>Profit &amp; loss statement</h3><small id="plScope">This month</small></div>
        <div class="cfa-pls">
            <div class="cfa-pls-col">
                <div class="cfa-pls-h in">Revenue</div>
                <div class="cfa-pls-r"><span title="Manage Sales invoices — the agent price">Direct sales</span><b data-pl="package"></b></div>
                <div class="cfa-pls-r"><span title="Charges on re-issue / refund / void from Ticket Orders, less agent prices reversed">Change charges</span><b data-pl="billed"></b></div>
                <div class="cfa-pls-r"><span title="Tickets Contract Flight bought from Ticketing — at the net price, no profit">To Contract Flight <small>at net</small></span><b data-pl="transfer"></b></div>
                <div class="cfa-pls-t in"><span>Total revenue</span><b data-pl="revenue"></b></div>
            </div>
            <div class="cfa-pls-col">
                <div class="cfa-pls-h out">Cost</div>
                <div class="cfa-pls-r"><span title="Cost price of the tickets on those invoices">Cost of direct sales</span><b data-pl="ticket_cost"></b></div>
                <div class="cfa-pls-r"><span title="Fees owed to the seller on a change, less the airline's refund on a refund / void">Seller fees on changes</span><b data-pl="change_cost"></b></div>
                <div class="cfa-pls-r"><span title="The same tickets at the same net price — so they make no profit or loss here">Cost of Contract Flight tickets</span><b data-pl="transfer"></b></div>
                <div class="cfa-pls-r"><span title="What airlines kept on cancelled tickets nobody bought from us, and change fees on unsold tickets">Own losses</span><b data-pl="penalty"></b></div>
                <div class="cfa-pls-t out"><span>Total cost</span><b data-pl="cost"></b></div>
            </div>
            <div class="cfa-pls-col">
                <div class="cfa-pls-h good" title="Gross — before overheads and tax">Result</div>
                <div class="cfa-pls-big"><span>Gross profit</span><b data-pl="profit"></b><small><em data-pl="margin"></em> margin · <em data-pl="flights"></em> rows</small></div>
                <div class="cfa-pls-r"><span title="Every ticket on the invoice has travelled">Earned</span><b data-pl="earned_profit"></b></div>
                <div class="cfa-pls-r"><span title="Not travelled yet — profit expected, not earned">Booked</span><b data-pl="booked_profit"></b></div>
            </div>
        </div>
        <div class="cfa-pls-warn" id="plWarn" hidden><span data-pl="inc_pax"></span><span data-pl="inc_revenue"></span><span data-pl="clean_margin"></span></div>
        <div class="cfa-pls-cash">
            <div><span>Received from agents</span><b class="cfa-good" data-pl="agent_paid"></b></div>
            <div><span title="Paid to the sellers of the tickets sold">Paid to sellers</span><b class="cfa-out" data-pl="vendor_paid"></b></div>
            <div><span>Cash position</span><b data-pl="cash"></b></div>
            <div><span>Still due from agents</span><b class="cfa-due" data-pl="agent_due"></b></div>
            <div><span>Still owed to sellers</span><b class="cfa-due" data-pl="vendor_due"></b></div>
        </div>
        @if($dept['ok'] ?? false)
        <div class="cfa-pls-out" title="Bought, not sold and not on a contract flight — an asset until sold, not profit or loss">
            <span class="h">Stock</span>
            <span data-dd-stock title="Show the unsold tickets">Unsold tickets <b class="cfa-dd-link {{ $dept['totals']['stock'] > 0 ? 'cfa-due' : '' }}">{{ $m($dept['totals']['stock']) }}</b> · {{ $dept['totals']['stock_count'] }}</span>
        </div>
        @endif
        @if($gl !== null)
        @php $glDiff = (float) $sales->sum('total') - $gl; @endphp
        <div class="cfa-pls-gl" title="Ledger = Air Ticket Sales (4110) on the live journal lines of these invoices. A gap means an invoice was changed after posting, or posted to another head — see Journal History.">
            <i class="fas fa-book"></i>
            <span>Books check · all months · invoice revenue <b class="{{ abs($glDiff) < 1 ? 'cfa-good' : 'cfa-due' }}">{{ abs($glDiff) < 1 ? 'matches ledger' : 'off by ' . $m($glDiff) }}</b></span>
        </div>
        @endif
    </div>

    <div class="cfa-pl-2 cfa-pl-side">
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>By agent</h3></div>
            <div class="cfa-scroll"><table class="cfa-table cfa-pl-agents" id="plAgents">
                <thead><tr><th>Agent</th><th class="num">Rows</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Due</th></tr></thead>
                <tbody></tbody>
            </table></div>
        </div>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Where profit is lost</h3></div>
            <div class="cfa-pl-leak">
                <div class="cfa-pls-r"><span>Seller fees on changes</span><b class="cfa-out" data-pl="change_cost"></b></div>
                <div class="cfa-pls-r"><span>Charged to agents for changes</span><b class="cfa-in" data-pl="billed"></b></div>
                <div class="cfa-pls-t"><span>Net cost of changes</span><b data-pl="net_changes"></b></div>
            </div>
            <div class="cfa-scroll"><table class="cfa-table cfa-pl-losses" id="plLosses">
                <thead><tr><th>Loss-making</th><th class="num">Revenue</th><th class="num">Change cost</th><th class="num">Loss</th></tr></thead>
                <tbody></tbody>
            </table></div>
        </div>
    </div>

    <div class="cfa-card">
        <div class="cfa-card-h"><h3>Invoice by invoice</h3><small>Click a row for its tickets · Contract Flight transfers and own losses by month</small></div>
        @include('party-statement.partials.table-bar', [
            'prefix' => 'pl', 'placeholder' => 'Search invoice, agent, PNR, passenger or seller…',
            'segments' => [['all', 'All'], ['earned', 'Flown'], ['booked', 'Not flown'], ['profit', 'Profit'], ['loss', 'Loss'], ['due', 'Agent due']],
            'dates' => true, 'print' => true,
        ])
        <div class="cfa-scroll"><table class="cfa-table" id="plTable" data-cf-sort data-now="{{ now()->format('Y-m') }}" data-row="row" data-unit="ticket" data-earned="Travelled (earned)" data-booked="Not travelled (booked)">
            <thead>{!! $plHead !!}</thead>
            <tbody>
            @forelse($plRows as $r)
                @include('dashboard.ticket_accounts.partials.pl-row')
            @empty
                <tr><td colspan="11" class="cfa-empty"><i class="fas fa-file-invoice"></i>No invoices yet — tickets are sold from Manage Sales.</td></tr>
            @endforelse
            <tr id="plNone" hidden><td colspan="11" class="cfa-empty"><i class="fas fa-filter"></i>Nothing matches.</td></tr>
            </tbody>
            <tfoot class="cfa-pl-foot"><tr><td></td><td colspan="3">Total · <span data-pl="flights"></span> rows</td><td></td><td class="num" data-pl="revenue"></td><td class="num" data-pl="cost"></td><td class="num" data-pl="profit"></td><td class="num" data-pl="margin"></td><td class="num" data-pl="agent_due"></td><td></td></tr></tfoot>
        </table></div>
    </div>

    {{-- Past months (owner, 2026-10-15): everything above is the current
         month; opening a month here shows its whole P&L — statement, by
         agent, losses, invoices. --}}
    <div class="cfa-card">
        <div class="cfa-card-h"><h3>Past months</h3><small>Click a month for its full profit &amp; loss</small></div>
        <div class="cfa-scroll"><table class="cfa-table" id="plMonths">
            <thead><tr><th>Month</th><th class="num">Rows</th><th class="num">Tickets</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Agent due</th></tr></thead>
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
                        <div class="cfa-card-h"><h3>Invoice by invoice</h3><small>{{ $monthName }} · click a row for its tickets</small></div>
                        <div class="cfa-scroll"><table class="cfa-table cfa-pl-nested">
                            <thead>{!! $plHead !!}</thead>
                            <tbody>
                            @foreach($plRows->where('month', $mo) as $r)
                                @include('dashboard.ticket_accounts.partials.pl-row')
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
    <script>window.PL_PAX = @json($plPax); window.PL_STOCK = @json(($dept['ok'] ?? false) ? $dept['stock']->values() : []);</script>
</section>
