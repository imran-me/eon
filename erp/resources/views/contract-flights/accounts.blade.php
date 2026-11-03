@extends('layout.app')
@section('meta-information')<title>Flight Accounts</title>@endsection
@section('css')
@include('contract-flights.partials.passenger-table-styles')
@include('contract-flights.partials.accounts-styles')
@include('party-statement.partials.bar-styles')
@endsection
@section('main-content')
@php
    $taka = '৳';
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . $taka . number_format(abs((float) $v), 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y, h:i A') : '—';
    $partyUrl = route('role.party-statement.index', ['role' => $role]);
    $flightsUrl = route('role.contract-flights.index', ['role' => $role]);
    $txLabels = [
        \App\Models\ContractFlightPassenger::AGENT_LEDGER_TYPE => ['Agent charge', 'in'],
        'contract_flight' => ['Flight cost', 'out'],
        \App\Models\ContractFlightPassengerServiceCost::JOURNAL_SOURCE => ['Service cost', 'out'],
        'party_payment' => ['Payment', 'pay'],
        'ticket_purchase' => ['Ticket purchase', 'out'],
        'ticket_purchase_refund' => ['Ticket refund', 'in'],
        'contract_flight_reissue_fee' => ['Reissue fee', 'out'],
        'contract_flight_reissue_fare' => ['Fare difference', 'out'],
        \App\Models\ContractFlightPassenger::AGENT_REVERSAL_TYPE => ['Package reversal', 'out'],
        \App\Models\ContractFlightPassengerChange::PRICE_REVISION_TYPE => ['Package revised', 'out'],
        \App\Models\ContractFlightPassengerChange::PENALTY_LEDGER_TYPE => ['Airline penalty', 'in'],
        \App\Models\ContractFlightPassengerChange::FARE_LEDGER_TYPE => ['Fare difference', 'in'],
    ];
    $srcLabels = [
        \App\Models\ContractFlightPassenger::AGENT_LEDGER_TYPE => 'Agent charge',
        'contract_flight' => 'Flight cost',
        \App\Models\ContractFlightPassengerServiceCost::JOURNAL_SOURCE => 'Service cost',
        'ticket_purchase' => 'Ticket purchase',
        'ticket_purchase_refund' => 'Ticket cancelled — refund',
        'contract_flight_reissue_fee' => 'Reissue fee',
        'contract_flight_reissue_fare' => 'Fare difference',
        \App\Models\ContractFlightPassenger::AGENT_REVERSAL_TYPE => 'Package reversal',
        \App\Models\ContractFlightPassengerChange::PRICE_REVISION_TYPE => 'Package revised (return cancelled)',
        \App\Models\ContractFlightPassengerChange::PENALTY_LEDGER_TYPE => 'Airline penalty',
        \App\Models\ContractFlightPassengerChange::FARE_LEDGER_TYPE => 'Fare difference',
        'party_payment' => 'Party payment',
    ];
    $rangeLabel = ($from || $to) ? (($from ? $from->format('d M Y') : 'Start') . ' – ' . ($to ? $to->format('d M Y') : 'Today')) : 'All time';
@endphp

@include('layout.contract-flight-tabs')

<div class="cfa-wrap">
    @include('contract-flights.partials.accounts-top')

    {{-- ── Profit & Loss ── --}}
    <section class="cfa-panel" data-panel="pl">
        @php
            $oneAirline = $flights->pluck('airline')->filter(fn ($a) => $a !== 'Airline pending')->unique()->count() <= 1;
            $plPax = $rows->map(fn ($r) => [
                'fk' => $r['fk'], 'agent' => $r['agent_name'] ?: 'No agent', 'month' => $r['month'],
                'earned' => $r['earned'], 'inc' => $r['cost_incomplete'],
            ] + collect($r)->only(['package', 'billed', 'revenue', 'ticket_cost', 'service_cost', 'change_cost', 'cost', 'profit', 'agent_paid', 'agent_due', 'vendor_paid'])->all())->values();
        @endphp
        {{-- The statement: read top to bottom like a ledger. Every figure is
             recomputed in the browser from the flights the bar leaves on
             screen, so it always matches the table below it. Opens on the
             current month; a past month opens the same statement under its
             row in Past months (accounts-pl-scripts copies this card). --}}
        <div class="cfa-card cfa-pl-stmt">
            <div class="cfa-card-h"><h3>Profit &amp; loss statement</h3><small id="plScope">This month</small></div>
            <div class="cfa-pls">
                <div class="cfa-pls-col">
                    <div class="cfa-pls-h in">Revenue</div>
                    <div class="cfa-pls-r"><span title="agent price per passenger · cancelled / deported on our side excluded">Package charges</span><b data-pl="package"></b></div>
                    <div class="cfa-pls-r"><span title="cancellation fees, penalties &amp; fare differences the agent bears">Change charges</span><b data-pl="billed"></b></div>
                    <div class="cfa-pls-t in"><span>Total revenue</span><b data-pl="revenue"></b></div>
                </div>
                <div class="cfa-pls-col">
                    <div class="cfa-pls-h out">Cost</div>
                    <div class="cfa-pls-r"><span title="live PNRs linked to the passengers">Tickets</span><b data-pl="ticket_cost"></b></div>
                    <div class="cfa-pls-r"><span title="training, consulting &amp; other fees we pay">Services</span><b data-pl="service_cost"></b></div>
                    <div class="cfa-pls-r"><span title="airline kept, reissue fees, charges on our side">Changes we bear</span><b data-pl="change_cost"></b></div>
                    <div class="cfa-pls-t out"><span>Total cost</span><b data-pl="cost"></b></div>
                </div>
                <div class="cfa-pls-col">
                    <div class="cfa-pls-h good" title="Gross — before overheads and tax">Result</div>
                    <div class="cfa-pls-big"><span>Gross profit</span><b data-pl="profit"></b><small><em data-pl="margin"></em> margin · <em data-pl="pax"></em> pax · <em data-pl="flights"></em> flights</small></div>
                    <div class="cfa-pls-r"><span title="flights already flown — the service is delivered">Earned</span><b data-pl="earned_profit"></b></div>
                    <div class="cfa-pls-r"><span title="not flown yet — profit expected, not earned">Booked</span><b data-pl="booked_profit"></b></div>
                </div>
            </div>
            <div class="cfa-pls-warn" id="plWarn" hidden title="Their ticket cost is not in yet, so their profit reads too high"><i class="fas fa-triangle-exclamation"></i>
                <span><b data-pl="inc_pax"></b> passengers (<b data-pl="inc_revenue"></b>) not fully ticketed — profit overstated · margin on complete costs <b data-pl="clean_margin"></b></span></div>
            <div class="cfa-pls-cash">
                <div><span>Received from agents</span><b class="cfa-good" data-pl="agent_paid"></b></div>
                <div><span title="tickets &amp; services">Paid to vendors</span><b class="cfa-out" data-pl="vendor_paid"></b></div>
                <div><span title="received − paid">Cash position</span><b data-pl="cash"></b></div>
                <div><span>Still due from agents</span><b class="cfa-due" data-pl="agent_due"></b></div>
                <div><span>Still owed to vendors</span><b class="cfa-due" data-pl="vendor_due"></b></div>
            </div>
            @if($gl)
            @php
                $glIncDiff = $overview['revenue'] - $gl['income'];
                $glCostDiff = $overview['cost'] - $gl['expense'];
                $glTone = fn ($v) => abs($v) < 1 ? 'cfa-good' : 'cfa-due';
            @endphp
            <div class="cfa-pls-gl" title="Ledger = income (4xxx) and expense (5xxx) on every live journal line behind contract flights and their tickets, all flights. A gap means something entered on a passenger is not posted (or was changed after posting) — see Journal History.">
                <i class="fas fa-book"></i>
                <span>Books check · all months · revenue <b class="{{ $glTone($glIncDiff) }}">{{ abs($glIncDiff) < 1 ? 'matches ledger' : 'off by ' . $m($glIncDiff) }}</b> · cost <b class="{{ $glTone($glCostDiff) }}">{{ abs($glCostDiff) < 1 ? 'matches ledger' : 'off by ' . $m($glCostDiff) }}</b></span>
            </div>
            @endif
        </div>

        <div class="cfa-pl-2 cfa-pl-side">
            <div class="cfa-card">
                <div class="cfa-card-h"><h3>By agent</h3></div>
                <div class="cfa-scroll"><table class="cfa-table cfa-pl-agents" id="plAgents">
                    <thead><tr><th>Agent</th><th class="num">Pax</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Due</th></tr></thead>
                    <tbody></tbody>
                </table></div>
            </div>
            <div class="cfa-card">
                <div class="cfa-card-h"><h3>Where profit is lost</h3></div>
                <div class="cfa-pl-leak">
                    <div class="cfa-pls-r"><span title="airline kept, reissue fees, our-side charges">Changes we bear</span><b class="cfa-out" data-pl="change_cost"></b></div>
                    <div class="cfa-pls-r"><span title="change charges billed">Recovered from agents</span><b class="cfa-in" data-pl="billed"></b></div>
                    <div class="cfa-pls-t"><span>Net cost of changes</span><b data-pl="net_changes"></b></div>
                </div>
                <div class="cfa-scroll"><table class="cfa-table cfa-pl-losses" id="plLosses">
                    <thead><tr><th>Loss-making flight</th><th class="num">Revenue</th><th class="num">Changes</th><th class="num">Loss</th></tr></thead>
                    <tbody></tbody>
                </table></div>
            </div>
        </div>

        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Flight by flight</h3><small>Click a row for passengers{{ $oneAirline && $flights->isNotEmpty() ? ' · all ' . $flights->pluck('airline')->first() : '' }}</small></div>
            @include('party-statement.partials.table-bar', [
                'prefix' => 'pl', 'placeholder' => 'Search flight, route, agent or passenger…',
                'segments' => [['all', 'All'], ['earned', 'Flown'], ['booked', 'Not flown'], ['profit', 'Profit'], ['loss', 'Loss'], ['due', 'Agent due'], ['incomplete', 'Cost incomplete']],
                'dates' => true, 'print' => true,
            ])
            <div class="cfa-scroll"><table class="cfa-table" id="plTable" data-cf-sort data-now="{{ now()->format('Y-m') }}">
                <thead><tr><th data-nosort></th><th>Flight</th><th>Route</th><th>Agents</th><th class="num">Pax</th><th class="num">Revenue</th><th class="num">Tickets</th><th class="num">Services</th><th class="num" title="Airline penalties kept, reissue fees and change charges we bear">Changes</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Agent due</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($flights as $f)
                    @include('contract-flights.partials.accounts-pl-flight')
                @empty
                    <tr><td colspan="13" class="cfa-empty"><i class="fas fa-plane-slash"></i>No flights yet.</td></tr>
                @endforelse
                <tr id="plNone" hidden><td colspan="13" class="cfa-empty"><i class="fas fa-filter"></i>No flight matches.</td></tr>
                </tbody>
                <tfoot class="cfa-pl-foot"><tr><td></td><td colspan="3">Total · <span data-pl="flights"></span> flights</td><td class="num" data-pl="pax"></td><td class="num" data-pl="revenue"></td><td class="num" data-pl="ticket_cost"></td><td class="num" data-pl="service_cost"></td><td class="num" data-pl="change_cost"></td><td class="num" data-pl="profit"></td><td class="num" data-pl="margin"></td><td class="num" data-pl="agent_due"></td><td></td></tr></tfoot>
            </table></div>
        </div>

        {{-- Past months (owner, 2026-10-15): everything above is the current
             month; every other month is a row here, and opening one shows
             that month's whole P&L — statement, by agent, losses, flights. --}}
        @php
            $nowMonth = now()->format('Y-m');
            [$datedMonths, $undatedMonths] = $monthly->reject(fn ($r) => $r['month'] === $nowMonth)->partition(fn ($r) => $r['month'] !== 'undated');
            $pastMonths = $datedMonths->sortByDesc('month')->concat($undatedMonths)->values();
            $hasUpcoming = $datedMonths->contains(fn ($r) => $r['month'] > $nowMonth);
        @endphp
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>{{ $hasUpcoming ? 'Upcoming & past months' : 'Past months' }}</h3><small>Click a month for its full profit &amp; loss</small></div>
            <div class="cfa-scroll"><table class="cfa-table" id="plMonths">
                <thead><tr><th>Month</th><th class="num">Flights</th><th class="num">Passengers</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Agent due</th></tr></thead>
                <tbody>
                @forelse($pastMonths as $r)
                    @php
                        $monthFlights = $flights->filter(fn ($f) => $f['month'] === $r['month']);
                        $monthName = $r['month'] === 'undated' ? 'Undated' : \Illuminate\Support\Carbon::createFromFormat('Y-m', $r['month'])->format('F Y');
                    @endphp
                    <tr class="cfa-pl-month" tabindex="0" data-month="{{ $r['month'] }}" data-label="{{ $monthName }}" title="Show {{ $monthName }}'s profit &amp; loss">
                        <td class="b"><i class="fas fa-chevron-right cfa-pl-mi"></i> {{ $r['label'] }}@if($r['month'] !== 'undated' && $r['month'] > $nowMonth)<small class="cfa-pl-up">upcoming</small>@endif</td>
                        <td class="num" data-mc="flights"></td><td class="num" data-mc="pax"></td><td class="num cfa-in" data-mc="revenue"></td><td class="num cfa-out" data-mc="cost"></td>
                        <td class="num" data-mc="profit"></td><td class="num" data-mc="margin"></td><td class="num" data-mc="agent_due"></td>
                    </tr>
                    <tr class="cfa-jr cfa-pl-mdetail" hidden><td colspan="8">
                        <div class="cfa-pl-mscope" data-month="{{ $r['month'] }}"></div>
                        <div class="cfa-card cfa-pl-mrows">
                            <div class="cfa-card-h"><h3>Flight by flight</h3><small>{{ $monthName }} · click a row for passengers</small></div>
                            <div class="cfa-scroll"><table class="cfa-table cfa-pl-nested">
                                <thead><tr><th></th><th>Flight</th><th>Route</th><th>Agents</th><th class="num">Pax</th><th class="num">Revenue</th><th class="num">Tickets</th><th class="num">Services</th><th class="num">Changes</th><th class="num">Profit</th><th class="num">Margin</th><th class="num">Agent due</th><th>Status</th></tr></thead>
                                <tbody>
                                @foreach($monthFlights as $f)
                                    @include('contract-flights.partials.accounts-pl-flight')
                                @endforeach
                                </tbody>
                            </table></div>
                        </div>
                    </td></tr>
                @empty
                    <tr><td colspan="8" class="cfa-empty"><i class="fas fa-calendar"></i>No other months yet.</td></tr>
                @endforelse
                </tbody>
                @if($pastMonths->isNotEmpty())
                <tfoot class="cfa-pl-foot"><tr><td>Total · <span data-mt="months"></span></td><td class="num" data-mt="flights"></td><td class="num" data-mt="pax"></td><td class="num" data-mt="revenue"></td><td class="num" data-mt="cost"></td><td class="num" data-mt="profit"></td><td class="num" data-mt="margin"></td><td class="num" data-mt="agent_due"></td></tr></tfoot>
                @endif
            </table></div>
        </div>
        <script>window.PL_PAX = @json($plPax);</script>
    </section>

    {{-- ── Transactions ── --}}
    <section class="cfa-panel" data-panel="tx" hidden>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Party ledger transactions</h3><small>Agent charges, flight and service costs, and the payments against them — as they sit on each party's statement · latest first · up to 400</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Date</th><th>Party</th><th>Type</th><th>Reference</th><th>Remarks</th><th>Account</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
                <tbody>
                @forelse($transactions as $t)
                    @php [$label, $dir] = $txLabels[$t->type] ?? [\Illuminate\Support\Str::headline($t->type), 'muted']; @endphp
                    <tr>
                        <td>{{ $d($t->payment_date) }}</td>
                        <td class="b"><a href="{{ $partyUrl }}?party_id={{ $t->user_id }}">{{ $t->user?->name ?? '—' }}</a><small>{{ $t->user_type ? ucfirst($t->user_type) : '' }}</small></td>
                        <td><span class="cfa-pill {{ ['in' => '', 'out' => 'warn', 'pay' => 'ok'][$dir] ?? 'muted' }}">{{ $label }}</span></td>
                        <td>{{ $t->reference_no ?: '—' }}</td>
                        <td>{{ $t->remarks ?: '—' }}</td>
                        <td>{{ $t->account?->name ?? ($t->payment_method ?: '—') }}</td>
                        <td class="num {{ $t->debit > 0 ? 'cfa-in' : '' }}">{{ $t->debit > 0 ? $m($t->debit) : '—' }}</td>
                        <td class="num {{ $t->credit > 0 ? 'cfa-good' : '' }}">{{ $t->credit > 0 ? $m($t->credit) : '—' }}</td>
                        <td class="num">{{ $m($t->balance) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-right-left"></i>No ledger transactions in this range.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>

    {{-- ── Journal history ── --}}
    <section class="cfa-panel" data-panel="journal" hidden>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Journal entries</h3><small>The general-ledger postings behind contract flights — each entry with its debit and credit lines · latest first · up to 200</small></div>
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
                    <tr><td colspan="5" class="cfa-empty"><i class="fas fa-book"></i>No journal entries in this range.</td></tr>
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
// Tabs: one panel at a time, remembered in the hash so a refresh or a shared
// link lands on the same sheet.
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
