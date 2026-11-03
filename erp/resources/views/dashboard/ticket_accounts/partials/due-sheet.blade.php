{{-- Ticket Accounts' due sheets, shown under the matching ledger tab
     ($tkDueKind = agents | vendors). Data from TicketAccountsController::figures(). --}}
@php
    $role = $role ?? request()->route('role');
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 0);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1) . '%';
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $safeRoute = function ($name, $params = []) use ($role) {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name, ['role' => $role] + $params) : null; } catch (\Throwable) { return null; }
    };
    $partyUrl = $safeRoute('role.party-statement.index');
    $editUrl = fn ($id) => $safeRoute('role.ticket-direct-sale.edit', ['ticket_direct_sale' => $id]);
    $saleUrl = fn ($id) => $safeRoute('role.ticket-sales.show', ['ticket_sale' => $id]);
    $useLabel = ['cf' => ['Contract flight', ''], 'sale' => ['Sold', 'ok'], 'idle' => ['Idle', 'warn'], 'cancelled' => ['Cancelled', 'bad']];
@endphp
<div class="cfa-wrap" style="margin-top:14px">
@if($tkDueKind === 'agents')
    {{-- ── Agent due sheet ── --}}
    <section class="cfa-panel" data-panel="agents">
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>By agent</h3><small>Manage Sales invoices — contract flight agents are on Flight Accounts · sorted by due</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Agent</th><th class="num">Invoices</th><th class="num">Tickets</th><th class="num">Charged</th><th class="num">Received</th><th class="num">Due</th><th>Oldest due</th><th class="num">Ledger</th></tr></thead>
                <tbody>
                @forelse($agentSheet as $a)
                    <tr><td class="b">@if($partyUrl)<a href="{{ $partyUrl }}?party_id={{ $a['client_id'] }}">{{ $a['name'] }}</a>@else{{ $a['name'] }}@endif</td>
                        <td class="num">{{ $a['invoices'] }}</td><td class="num">{{ $a['tickets'] }}</td>
                        <td class="num cfa-in">{{ $m($a['charged']) }}</td><td class="num cfa-good">{{ $m($a['received']) }}</td>
                        <td class="num {{ $a['due'] > 0 ? 'cfa-due' : '' }}">{{ $m($a['due']) }}</td>
                        <td>{{ $a['oldest'] ? $d($a['oldest']) : '—' }}@if($a['days'] !== null)<small>{{ $a['days'] }} days</small>@endif</td>
                        <td class="num">{{ $a['ledger'] !== null ? $m($a['ledger']) : '—' }}</td></tr>
                @empty
                    <tr><td colspan="8" class="cfa-empty"><i class="fas fa-user-tie"></i>No ticket invoices in this range.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Unpaid invoices</h3><small>Oldest first</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Invoice</th><th>Date</th><th>Agent</th><th class="num">Tickets</th><th class="num">Total</th><th class="num">Received</th><th class="num">Due</th></tr></thead>
                <tbody>
                @forelse($unpaidSales as $s)
                    <tr><td class="b">@if($u = $saleUrl($s['id']))<a href="{{ $u }}">{{ $s['invoice'] }}</a>@else{{ $s['invoice'] }}@endif</td><td>{{ $d($s['date']) }}</td><td>{{ $s['client'] ?: '—' }}</td>
                        <td class="num">{{ $s['items'] }}</td><td class="num">{{ $m($s['total']) }}</td><td class="num cfa-good">{{ $m($s['paid']) }}</td><td class="num cfa-due">{{ $m($s['due']) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="cfa-empty"><i class="fas fa-circle-check"></i>No unpaid invoices.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>

@else
    {{-- ── Vendor due sheet ── --}}
    <section class="cfa-panel" data-panel="vendors">
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>By vendor / portal</h3><small>Billed = ticket cost · Refunds = airline refunds credited on cancelled tickets · Due = unpaid on live tickets · Ledger = the party's statement balance · sorted by due</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Seller</th><th class="num">Tickets</th><th class="num">Billed</th><th class="num">Paid</th><th class="num">Refunds</th><th class="num">Due</th><th>Oldest due</th><th class="num">Ledger / wallet</th></tr></thead>
                <tbody>
                @forelse($vendorSheet as $v)
                    <tr><td class="b">@if($v['vendor_id'] && $partyUrl)<a href="{{ $partyUrl }}?party_id={{ $v['vendor_id'] }}">{{ $v['name'] }}</a>@else{{ $v['name'] }}@endif<small>{{ $v['portal'] ? 'Portal' : 'Vendor' }}{{ $v['due_items'] ? ' · ' . $v['due_items'] . ' unpaid' : '' }}</small></td>
                        <td class="num">{{ $v['tickets'] }}</td><td class="num cfa-out">{{ $m($v['billed']) }}</td><td class="num cfa-good">{{ $m($v['paid']) }}</td>
                        <td class="num">{{ $v['refunds'] > 0 ? $m($v['refunds']) : '—' }}</td>
                        <td class="num {{ $v['due'] > 0 ? 'cfa-bad' : '' }}">{{ $m($v['due']) }}</td>
                        <td>{{ $v['oldest'] ? $d($v['oldest']) : '—' }}@if($v['oldest'])<small>{{ (int) \Illuminate\Support\Carbon::parse($v['oldest'])->diffInDays(now()) }} days</small>@endif</td>
                        <td class="num">@if($v['wallet'] !== null)<span class="{{ $v['wallet'] < 0 ? 'cfa-bad' : '' }}">{{ $m($v['wallet']) }}</span><small>wallet</small>@elseif($v['ledger'] !== null){{ $m($v['ledger']) }}<small>statement</small>@else — @endif</td></tr>
                @empty
                    <tr><td colspan="8" class="cfa-empty"><i class="fas fa-truck"></i>No tickets in this range.</td></tr>
                @endforelse
                </tbody>
                @if($vendorSheet->count() > 1)
                <tfoot><tr><td>Total</td><td class="num">{{ $vendorSheet->sum('tickets') }}</td><td class="num cfa-out">{{ $m($vendorSheet->sum('billed')) }}</td><td class="num cfa-good">{{ $m($vendorSheet->sum('paid')) }}</td><td class="num">{{ $m($vendorSheet->sum('refunds')) }}</td><td class="num cfa-bad">{{ $m($vendorSheet->sum('due')) }}</td><td></td><td></td></tr></tfoot>
                @endif
            </table></div>
        </div>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Unpaid tickets</h3><small>Every live ticket still owing its seller, oldest first</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Issued</th><th>PNR</th><th>Passenger</th><th>Route</th><th>Seller</th><th>Used for</th><th class="num">Cost</th><th class="num">Paid</th><th class="num">Due</th></tr></thead>
                <tbody>
                @forelse($unpaidTickets as $t)
                    <tr><td>{{ $d($t['issued']) }}</td>
                        <td class="b">@if($u = $editUrl($t['id']))<a href="{{ $u }}">{{ $t['pnr'] ?: '—' }}</a>@else{{ $t['pnr'] ?: '—' }}@endif</td>
                        <td>{{ $t['passenger'] ?: '—' }}</td><td>{{ $t['route'] ?: '—' }}</td><td>{{ $t['seller'] ?: '—' }}</td>
                        <td><span class="cfa-pill {{ $useLabel[$t['use']][1] }}">{{ $t['use_label'] ?: $useLabel[$t['use']][0] }}</span></td>
                        <td class="num">{{ $m($t['cost']) }}</td><td class="num cfa-good">{{ $m($t['paid']) }}</td><td class="num cfa-bad">{{ $m($t['due']) }}</td></tr>
                @empty
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-circle-check"></i>Every ticket in this range is paid.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>

@endif
</div>
