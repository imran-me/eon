@extends('layout.app')
@section('meta-information')<title>Ticket Accounts</title>@endsection
@section('css')
{{-- Same stylesheet as Flight Accounts (contract-flights/accounts), copied so both pages look identical. --}}
@include('contract-flights.partials.accounts-styles')
@include('party-statement.partials.bar-styles')
@endsection
@section('main-content')
@php
    $taka = '৳';
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . $taka . number_format(abs((float) $v), 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $safeRoute = function ($name, $params = []) use ($role) {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name, ['role' => $role] + $params) : null; } catch (\Throwable) { return null; }
    };
    $partyUrl = $safeRoute('role.party-statement.index');
    $editUrl = fn ($id) => $safeRoute('role.ticket-direct-sale.edit', ['ticket_direct_sale' => $id]);
    $saleUrl = fn ($id) => $safeRoute('role.ticket-sales.show', ['ticket_sale' => $id]);
    $txLabels = [
        'ticket_purchase' => ['Ticket purchase', 'out'], 'ticket_purchase_refund' => ['Ticket refund', 'in'],
        'ticket_sale' => ['Ticket sale', 'in'], 'ticket_refund' => ['Refund to agent', 'out'], 'ticket_reissue' => ['Re-issue', 'in'],
    ];
    $srcLabels = ['ticket_purchase' => 'Ticket purchase', 'ticket_purchase_cancel' => 'Ticket cancelled — refund', 'ticket_sale' => 'Ticket sale', 'ticket_refund' => 'Ticket refund'];
    $rangeLabel = ($from || $to) ? (($from ? $from->format('d M Y') : 'Start') . ' – ' . ($to ? $to->format('d M Y') : 'Today')) : 'All time';
    $useLabel = ['cf' => ['Contract flight', ''], 'sale' => ['Sold', 'ok'], 'idle' => ['Idle', 'warn'], 'cancelled' => ['Cancelled', 'bad']];
@endphp

@include('layout.ticketing-tabs')

<div class="cfa-wrap">
    @include('dashboard.ticket_accounts.partials.top')

    @include('dashboard.ticket_accounts.partials.pl')

    {{-- ── Refunds & cancellations ── --}}
    <section class="cfa-panel" data-panel="refunds" hidden>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Cancelled tickets</h3><small>The ticket keeps its amount · Refund = what the airline gives back · Penalty = what it kept (stays in cost)</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Issued</th><th>PNR</th><th>Passenger</th><th>Seller</th><th>Cancelled</th><th>Note</th><th class="num">Amount</th><th class="num">Refund</th><th class="num">Penalty</th></tr></thead>
                <tbody>
                @forelse($cancelled as $t)
                    <tr><td>{{ $d($t['issued']) }}</td><td class="b">@if($u = $editUrl($t['id']))<a href="{{ $u }}">{{ $t['pnr'] ?: '—' }}</a>@else{{ $t['pnr'] ?: '—' }}@endif</td>
                        <td>{{ $t['passenger'] ?: '—' }}</td><td>{{ $t['seller'] ?: '—' }}</td><td>{{ $d($t['cancelled_at']) }}</td>
                        <td style="max-width:260px">{{ \Illuminate\Support\Str::limit($t['note'] ?: '—', 90) }}</td>
                        <td class="num">{{ $m($t['cost']) }}</td><td class="num cfa-good">{{ $m($t['refund']) }}</td><td class="num cfa-bad">{{ $m($t['penalty']) }}</td></tr>
                @empty
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-ban"></i>No cancelled tickets in this range.</td></tr>
                @endforelse
                </tbody>
                @if($cancelled->count() > 1)
                <tfoot><tr><td colspan="6">Total</td><td class="num">{{ $m($cancelled->sum('cost')) }}</td><td class="num cfa-good">{{ $m($cancelled->sum('refund')) }}</td><td class="num cfa-bad">{{ $m($cancelled->sum('penalty')) }}</td></tr></tfoot>
                @endif
            </table></div>
        </div>
        {{-- Ticket Orders -> Change (2026-10-06); a contract flight ticket's change is on Flight Accounts --}}
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Re-issues, reschedules, refunds &amp; voids</h3><small>Made from Ticket Orders · Seller fee = owed to the ticket's seller (ticket cost) · Charged = one line on the agent's statement · Reversed = agent price taken back</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Date</th><th>Change</th><th>PNR</th><th>Passenger</th><th>Agent</th><th>Note</th><th class="num">Seller fee</th><th class="num">Charged</th><th class="num">Reversed</th></tr></thead>
                <tbody>
                @forelse(($changes ?? collect()) as $c)
                    <tr><td>{{ $d($c['date']) }}</td><td><span class="cfa-pill {{ in_array($c['kind'], ['refund', 'void'], true) ? 'bad' : '' }}">{{ \App\Models\TicketChange::KINDS[$c['kind']] ?? $c['kind'] }}</span></td>
                        <td class="b">{{ $c['pnr'] ?: '—' }}</td><td>{{ $c['passenger'] ?: '—' }}</td><td>{{ $c['client'] ?: '—' }}</td>
                        <td style="max-width:260px">{{ \Illuminate\Support\Str::limit($c['note'] ?: '—', 90) }}</td>
                        <td class="num {{ $c['cost_added'] > 0 ? 'cfa-bad' : '' }}">{{ $c['cost_added'] > 0 ? $m($c['cost_added']) : '—' }}</td>
                        <td class="num {{ $c['charged'] > 0 ? 'cfa-in' : '' }}">{{ $c['charged'] > 0 ? $m($c['charged']) : '—' }}</td>
                        <td class="num">{{ $c['reversed'] > 0 ? $m($c['reversed']) : '—' }}</td></tr>
                @empty
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-right-left"></i>No ticket changes in this range.</td></tr>
                @endforelse
                </tbody>
                @if(($changes ?? collect())->count() > 1)
                <tfoot><tr><td colspan="6">Total</td><td class="num cfa-bad">{{ $m($changes->sum('cost_added')) }}</td><td class="num cfa-in">{{ $m($changes->sum('charged')) }}</td><td class="num">{{ $m($changes->sum('reversed')) }}</td></tr></tfoot>
                @endif
            </table></div>
        </div>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Refunds to agents &amp; re-issues</h3><small>Entered from Ticket Services</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Date</th><th>Kind</th><th>Invoice</th><th>Agent</th><th class="num">Airline refund / new cost</th><th class="num">Penalty</th><th class="num">Service charge</th><th class="num">Net to agent / new price</th><th class="num">Due</th></tr></thead>
                <tbody>
                @foreach($refunds as $r)
                    <tr><td>{{ $d($r->refund_date) }}</td><td><span class="cfa-pill bad">Refund</span></td><td class="b">{{ $r->original_invoice }}</td><td>{{ $r->agent_name ?: '—' }}</td>
                        <td class="num">{{ $m($r->airline_refund) }}</td><td class="num cfa-bad">{{ $m($r->penalty) }}</td><td class="num">{{ $m($r->service_charge) }}</td><td class="num">{{ $m($r->net_refund) }}</td><td class="num cfa-due">{{ $m($r->due_amount) }}</td></tr>
                @endforeach
                @foreach($reissues as $r)
                    <tr><td>{{ $d($r->reissue_date) }}</td><td><span class="cfa-pill">Re-issue</span><small>{{ $r->new_ticket_no }}</small></td><td class="b">{{ $r->original_invoice }}</td><td>{{ $r->agent_name ?: '—' }}</td>
                        <td class="num">{{ $m($r->new_cost) }}</td><td class="num cfa-bad">{{ $m($r->penalty) }}</td><td class="num">{{ $m($r->service_charge) }}</td><td class="num">{{ $m($r->new_sale_price) }}</td><td class="num cfa-due">{{ $m($r->due_amount) }}</td></tr>
                @endforeach
                @if($refunds->isEmpty() && $reissues->isEmpty())
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-rotate-left"></i>No refunds or re-issues in this range.</td></tr>
                @endif
                </tbody>
            </table></div>
        </div>
    </section>

    {{-- ── Transactions ── --}}
    <section class="cfa-panel" data-panel="tx" hidden>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Party ledger transactions</h3><small>Ticket costs, payments, refunds and sales as they sit on each party's statement · latest first · up to 400</small></div>
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
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-right-left"></i>No ledger transactions in this range.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>

    {{-- ── Journal history ── --}}
    <section class="cfa-panel" data-panel="journal" hidden>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Journal entries</h3><small>The general-ledger postings behind these tickets and invoices — each entry with its debit and credit lines · latest first · up to 200</small></div>
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
