{{-- Visa Accounts' due sheets, under the matching ledger tab
     ($vaDueKind = agents | vendors). Data from VisaAccountsController::figures(). --}}
@php
    $role = $role ?? request()->route('role');
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 0);
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $safeRoute = function ($name, $params = []) use ($role) {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name, ['role' => $role] + $params) : null; } catch (\Throwable) { return null; }
    };
    $partyUrl = $safeRoute('role.party-statement.index');
    $saleUrl = fn ($id) => $safeRoute('role.visa-sales.show', ['visaSale' => $id]);
@endphp
<div class="cfa-wrap" style="margin-top:14px">
@if($vaDueKind === 'agents')
    <section class="cfa-panel" data-panel="agents">
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>By agent</h3><small>Visa invoices from Manage Sales · sorted by due</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Agent</th><th class="num">Invoices</th><th class="num">Items</th><th class="num">Charged</th><th class="num">Received</th><th class="num">Due</th><th>Oldest due</th><th class="num">Ledger</th></tr></thead>
                <tbody>
                @forelse($agentSheet as $a)
                    <tr><td class="b">@if($partyUrl && $a['client_id'])<a href="{{ $partyUrl }}?party_id={{ $a['client_id'] }}">{{ $a['name'] }}</a>@else{{ $a['name'] }}@endif</td>
                        <td class="num">{{ $a['invoices'] }}</td><td class="num">{{ $a['items'] }}</td>
                        <td class="num cfa-in">{{ $m($a['charged']) }}</td><td class="num cfa-good">{{ $m($a['received']) }}</td>
                        <td class="num {{ $a['due'] > 0 ? 'cfa-due' : '' }}">{{ $m($a['due']) }}</td>
                        <td>{{ $a['oldest'] ? $d($a['oldest']) : '—' }}@if($a['days'] !== null)<small>{{ $a['days'] }} days</small>@endif</td>
                        <td class="num">{{ $a['ledger'] !== null ? $m($a['ledger']) : '—' }}</td></tr>
                @empty
                    <tr><td colspan="8" class="cfa-empty"><i class="fas fa-user-tie"></i>No visa invoices yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Unpaid invoices</h3><small>Oldest first</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Invoice</th><th>Date</th><th>Agent</th><th class="num">Items</th><th class="num">Total</th><th class="num">Received</th><th class="num">Due</th></tr></thead>
                <tbody>
                @forelse($unpaidSales as $s)
                    <tr><td class="b">@if($u = $saleUrl($s['id']))<a href="{{ $u }}">{{ $s['invoice'] }}</a>@else{{ $s['invoice'] }}@endif</td><td>{{ $d($s['date']) }}</td><td>{{ $s['client'] ?: '—' }}</td>
                        <td class="num">{{ $s['items']->count() }}</td><td class="num">{{ $m($s['revenue']) }}</td><td class="num cfa-good">{{ $m($s['paid']) }}</td><td class="num cfa-due">{{ $m($s['due']) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="cfa-empty"><i class="fas fa-circle-check"></i>No unpaid invoices.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>
@else
    <section class="cfa-panel" data-panel="vendors">
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>By vendor / portal</h3><small>Every application's cost, invoiced or not · sorted by due</small></div>
            @if($noSeller->count())
                <div class="cfa-pls-warn"><i class="fas fa-triangle-exclamation"></i><span><b>{{ $noSeller->count() }}</b> application{{ $noSeller->count() === 1 ? '' : 's' }} carry a cost (<b>{{ $m($noSeller->sum('cost')) }}</b>) but no vendor or portal — that cost is not on any vendor's ledger: {{ $noSeller->pluck('ref')->implode(', ') }}</span></div>
            @endif
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Vendor</th><th class="num">Applications</th><th class="num">Billed</th><th class="num">Paid</th><th class="num">Due</th><th>Oldest due</th><th class="num">Ledger</th></tr></thead>
                <tbody>
                @forelse($vendorSheet as $v)
                    <tr><td class="b">@if($v['vendor_id'] && $partyUrl)<a href="{{ $partyUrl }}?party_id={{ $v['vendor_id'] }}">{{ $v['name'] }}</a>@else{{ $v['name'] }}@endif<small>{{ $v['portal'] ? 'Portal' : ($v['vendor_id'] ? 'Vendor' : 'Not set') }}{{ $v['due_items'] ? ' · ' . $v['due_items'] . ' unpaid' : '' }}</small></td>
                        <td class="num">{{ $v['items'] }}</td><td class="num cfa-out">{{ $m($v['billed']) }}</td><td class="num cfa-good">{{ $m($v['paid']) }}</td>
                        <td class="num {{ $v['due'] > 0 ? 'cfa-due' : '' }}">{{ $m($v['due']) }}</td>
                        <td>{{ $v['oldest'] ? $d($v['oldest']) : '—' }}@if($v['oldest'])<small>{{ (int) \Illuminate\Support\Carbon::parse($v['oldest'])->diffInDays(now()) }} days</small>@endif</td>
                        <td class="num">{{ $v['ledger'] !== null ? $m($v['ledger']) : '—' }}</td></tr>
                @empty
                    <tr><td colspan="7" class="cfa-empty"><i class="fas fa-truck"></i>No visa costs yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Unpaid applications</h3><small>Every application still owing its vendor, oldest first</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Application</th><th>Passenger</th><th>Vendor</th><th>Status</th><th class="num">Cost</th><th class="num">Paid</th><th class="num">Due</th><th>Due date</th></tr></thead>
                <tbody>
                @forelse($unpaidApps as $a)
                    <tr><td class="b">{{ $a['ref'] }}<small>{{ $d($a['date']) }}</small></td><td>{{ $a['passenger'] ?: '—' }}</td><td>{{ $a['seller'] ?: '—' }}</td>
                        <td><span class="cfa-pill {{ $a['status'] === 'rejected' ? 'bad' : 'muted' }}">{{ ucwords(str_replace('_', ' ', (string) $a['status'])) }}</span></td>
                        <td class="num">{{ $m($a['cost']) }}</td><td class="num cfa-good">{{ $m($a['paid']) }}</td><td class="num cfa-due">{{ $m($a['due']) }}</td>
                        <td>@if($a['due_date'])<span class="cfa-pill {{ \Illuminate\Support\Carbon::parse($a['due_date'])->isPast() ? 'bad' : 'muted' }}">{{ $d($a['due_date']) }}</span>@else — @endif</td></tr>
                @empty
                    <tr><td colspan="8" class="cfa-empty"><i class="fas fa-circle-check"></i>Every visa cost is paid.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>
@endif
</div>
