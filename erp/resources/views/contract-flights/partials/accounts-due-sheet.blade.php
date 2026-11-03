{{-- The Agent / Vendor due sheets of Flight Accounts, shown under the
     matching ledger tab ($cfaDueKind = agents | vendors). Data from
     ContractFlightAccountsController::figures(). --}}
@php
    $m = fn ($v) => ((float) $v < 0 ? '−' : '') . '৳' . number_format(abs((float) $v), 0);
    $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $partyUrl = route('role.party-statement.index', ['role' => $role]);
@endphp
<div class="cfa-wrap" style="margin-top:14px">
@if($cfaDueKind === 'agents')
    {{-- ── Agent due sheet ── --}}
    <section class="cfa-panel" data-panel="agents">
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>By agent</h3><small>Charged = chargeable agent prices · Received = the agent's Party Statement receipts, allocated oldest departure first · Ledger balance = their statement's running balance · sorted by due</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Agent</th><th class="num">Passengers</th><th class="num">Flights</th><th class="num">Charged</th><th class="num">Received</th><th class="num">Due</th><th class="num">Ledger balance</th><th>Collected</th><th>Oldest due</th><th></th></tr></thead>
                <tbody>
                @forelse($agentSheet as $a)
                    <tr class="cfa-agent-row" data-agent="{{ $a['agent_id'] }}" data-agent-name="{{ $a['agent_name'] }}" tabindex="0" role="button" aria-pressed="false" title="Show this agent's due passengers below">
                        <td class="b"><a href="{{ $partyUrl }}?party_id={{ $a['agent_id'] }}">{{ $a['agent_name'] }}</a><small>EP-AG-{{ str_pad($a['agent_id'], 3, '0', STR_PAD_LEFT) }}</small></td>
                        <td class="num">{{ $a['passengers'] }}</td><td class="num">{{ $a['flights'] }}</td>
                        <td class="num cfa-in">{{ $m($a['charged']) }}</td><td class="num cfa-good">{{ $m($a['received']) }}</td>
                        <td class="num {{ $a['due'] > 0 ? 'cfa-due' : 'cfa-good' }}">{{ $a['due'] > 0 ? $m($a['due']) : 'Clear' }}</td>
                        <td class="num {{ ($a['ledger_balance'] ?? 0) > 0 ? 'cfa-due' : 'cfa-good' }}" title="Running balance on the agent's Party Statement — positive means they still owe">{{ $a['ledger_balance'] === null ? '—' : $m($a['ledger_balance']) }}</td>
                        <td><div class="cfa-bar"><span style="width:{{ $a['charged'] > 0 ? min(100, $a['received'] / $a['charged'] * 100) : 100 }}%"></span></div><small>{{ $a['charged'] > 0 ? number_format(min(100, $a['received'] / $a['charged'] * 100), 0) : 100 }}%</small></td>
                        <td>@if($a['oldest_due_at'])<span class="cfa-pill {{ $a['oldest_due_days'] > 30 ? 'bad' : ($a['oldest_due_days'] > 7 ? 'warn' : 'muted') }}">{{ $a['oldest_due_days'] }} days</span><small>{{ $d($a['oldest_due_at']) }} · {{ $a['due_passengers'] }} pax</small>@else —@endif</td>
                        <td><div class="cfa-send" style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
                            <a class="cfa-btn sm" href="{{ $partyUrl }}?party_id={{ $a['agent_id'] }}" title="Open statement" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fas fa-book-open" style="font-size:11px;margin:0"></i></a>
                            <button type="button" class="cfa-btn sm" onclick="cfaSend('statement', {{ $a['agent_id'] }}, @json($a['agent_name']), this)" title="Email the statement" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fas fa-envelope" style="font-size:11px;margin:0"></i></button>
                            <button type="button" class="cfa-btn sm {{ $a['due'] > 0 ? 'warn' : '' }}" onclick="cfaSend('reminder', {{ $a['agent_id'] }}, @json($a['agent_name']), this)" title="{{ $a['due'] > 0 ? 'Send a payment reminder (email + SMS)' : 'Nothing due — reminder not needed' }}" {{ $a['due'] > 0 ? '' : 'disabled' }} style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fas fa-bell" style="font-size:11px;margin:0"></i></button>
                            <button type="button" class="cfa-btn sm wa" onclick="cfaWhatsApp({{ $a['agent_id'] }}, @json($a['agent_name']), {{ (float) $a['due'] }}, 'agent', this)" title="Message on WhatsApp" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fab fa-whatsapp" style="font-size:11px;margin:0"></i></button>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="cfa-empty"><i class="fas fa-user-tie"></i>No agent charges in this range.</td></tr>
                @endforelse
                </tbody>
                @if($agentSheet->count() > 1)
                <tfoot><tr><td>Total</td><td class="num">{{ $agentSheet->sum('passengers') }}</td><td class="num"></td><td class="num cfa-in">{{ $m($agentSheet->sum('charged')) }}</td><td class="num cfa-good">{{ $m($agentSheet->sum('received')) }}</td><td class="num cfa-due">{{ $m($agentSheet->sum('due')) }}</td><td class="num cfa-due">{{ $m($agentSheet->sum('ledger_balance')) }}</td><td colspan="3"></td></tr></tfoot>
                @endif
            </table></div>
        </div>
        <div class="cfa-card">
            <div class="cfa-card-h" id="cfaDueHead"><h3>Due passengers</h3><small id="cfaDueHint">Every passenger whose agent charge is not fully received (ledger receipts allocated oldest first), oldest departure first · click an agent above to show only theirs</small><span class="sp"></span><button type="button" class="cfa-btn" id="cfaDueClear" hidden onclick="cfaPickAgent(null)"><i class="fas fa-xmark"></i> Show all agents</button></div>
            <div class="cfa-scroll"><table class="mfp-table" id="cfaDueTable" data-cf-sort>
                <colgroup><col style="width:3%"><col style="width:15%"><col style="width:13%"><col style="width:18%"><col style="width:12%"><col style="width:9%"><col style="width:13%"><col style="width:8%"><col style="width:9%"></colgroup>
                <thead><tr>
                    <th>SL</th><th>Passenger / Passport</th><th>Agent / Travel Details</th><th>Tickets / PNR</th><th>Route</th>
                    <th>Stage</th><th>Cost Breakdown</th><th class="mfp-num">Agent Charge</th><th class="mfp-num">Cost / Profit</th>
                </tr></thead>
                <tbody>
                @forelse($duePassengerModels as $i => $p)
                    @include('contract-flights.partials.passenger-row', [
                        'p' => $p, 'flight' => $p->contractFlight, 'sl' => $i + 1, 'mode' => 'due',
                        'ledger' => $dueLedger[$p->id], 'rowAttrs' => 'data-agent="' . ($p->agent_id ?? '') . '"',
                    ])
                @empty
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-circle-check"></i>Nothing outstanding from agents.</td></tr>
                @endforelse
                <tr id="cfaDueNone" hidden><td colspan="9" class="cfa-empty"><i class="fas fa-circle-check"></i>Nothing outstanding from this agent.</td></tr>
                </tbody>
                <tfoot id="cfaDueFoot" hidden><tr><td colspan="7">Total for <span id="cfaDueFootName"></span></td><td class="mfp-num" id="cfaDueFootCells"><span class="cfa-in" id="cfaDueFootCharge"></span><small class="cfa-good" id="cfaDueFootPaid"></small><small class="cfa-due" id="cfaDueFootDue"></small></td><td></td></tr></tfoot>
            </table></div>
        </div>
    </section>

@else
    {{-- ── Vendor due sheet ── --}}
    <section class="cfa-panel" data-panel="vendors">
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>By vendor</h3><small>Flight costs, linked PNRs and per-passenger service costs billed by each vendor</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Vendor</th><th>Kinds</th><th class="num">Items</th><th class="num">Billed</th><th class="num">Paid</th><th class="num">Due</th><th class="num">Ledger balance</th><th>Paid</th><th></th></tr></thead>
                <tbody>
                @forelse($vendorSheet as $v)
                    <tr>
                        <td class="b"><a href="{{ $partyUrl }}?party_id={{ $v['vendor_id'] }}">{{ $v['vendor_name'] }}</a><small>EP-VN-{{ str_pad($v['vendor_id'], 3, '0', STR_PAD_LEFT) }}</small></td>
                        <td>{{ $v['kinds'] }}</td><td class="num">{{ $v['items'] }}</td>
                        <td class="num cfa-out">{{ $m($v['billed']) }}</td><td class="num cfa-good">{{ $m($v['paid']) }}</td>
                        <td class="num {{ $v['due'] > 0 ? 'cfa-due' : 'cfa-good' }}">{{ $v['due'] > 0 ? $m($v['due']) : 'Clear' }}</td>
                        <td class="num {{ ($v['ledger_balance'] ?? 0) > 0 ? 'cfa-due' : 'cfa-good' }}" title="Running balance on the vendor's Party Statement, all their business with us — positive means we still owe">{{ $v['ledger_balance'] === null ? '—' : $m($v['ledger_balance']) }}</td>
                        <td><div class="cfa-bar"><span style="width:{{ $v['billed'] > 0 ? min(100, $v['paid'] / $v['billed'] * 100) : 100 }}%"></span></div><small>{{ $v['billed'] > 0 ? number_format(min(100, $v['paid'] / $v['billed'] * 100), 0) : 100 }}%</small></td>
                        <td><div class="cfa-send" style="display:grid;grid-template-columns:repeat(2,24px);gap:4px;width:max-content;margin:0 auto;justify-content:center" data-row-actions>
                            <a class="cfa-btn sm" href="{{ $partyUrl }}?party_id={{ $v['vendor_id'] }}" title="Open statement" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fas fa-book-open" style="font-size:11px;margin:0"></i></a>
                            <button type="button" class="cfa-btn sm" onclick="cfaSend('statement', {{ $v['vendor_id'] }}, @json($v['vendor_name']), this)" title="Email the statement" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fas fa-envelope" style="font-size:11px;margin:0"></i></button>
                            <button type="button" class="cfa-btn sm wa" onclick="cfaWhatsApp({{ $v['vendor_id'] }}, @json($v['vendor_name']), {{ (float) $v['due'] }}, 'vendor', this)" title="Message on WhatsApp" style="width:24px;height:24px;min-width:0;min-height:0;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:11px;line-height:1;border-radius:6px"><i class="fab fa-whatsapp" style="font-size:11px;margin:0"></i></button>
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="cfa-empty"><i class="fas fa-truck"></i>No vendor costs in this range.</td></tr>
                @endforelse
                </tbody>
                @if($vendorSheet->count() > 1)
                <tfoot><tr><td>Total</td><td></td><td class="num">{{ $vendorSheet->sum('items') }}</td><td class="num cfa-out">{{ $m($vendorSheet->sum('billed')) }}</td><td class="num cfa-good">{{ $m($vendorSheet->sum('paid')) }}</td><td class="num cfa-due">{{ $m($vendorSheet->sum('due')) }}</td><td class="num cfa-due">{{ $m($vendorSheet->sum('ledger_balance')) }}</td><td colspan="2"></td></tr></tfoot>
                @endif
            </table></div>
        </div>
        <div class="cfa-card">
            <div class="cfa-card-h"><h3>Unpaid items</h3><small>Each flight cost, PNR or service cost still carrying a balance</small></div>
            <div class="cfa-scroll"><table class="cfa-table" data-cf-sort>
                <thead><tr><th>Vendor</th><th>Kind</th><th>Reference</th><th>Date</th><th class="num">Billed</th><th class="num">Paid</th><th class="num">Due</th><th>Due date</th></tr></thead>
                <tbody>
                @forelse($vendorDueItems as $i)
                    <tr>
                        <td class="b">{{ $i['vendor_name'] }}</td><td><span class="cfa-pill muted">{{ $i['kind'] }}</span></td><td>{{ $i['ref'] }}</td><td>{{ $d($i['date']) }}</td>
                        <td class="num cfa-out">{{ $m($i['billed']) }}</td><td class="num cfa-good">{{ $m($i['paid']) }}</td><td class="num cfa-due">{{ $m($i['due']) }}</td>
                        <td>@if($i['due_date'])<span class="cfa-pill {{ \Illuminate\Support\Carbon::parse($i['due_date'])->isPast() ? 'bad' : 'muted' }}">{{ $d($i['due_date']) }}</span>@else —@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="cfa-empty"><i class="fas fa-circle-check"></i>Nothing outstanding to vendors.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </section>

@endif
</div>
