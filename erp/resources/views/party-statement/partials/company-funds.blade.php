{{-- Company Ledger, lower half (owner, 2026-09-28): Travels' per-head
     profit and loss, what of it has come in, owner withdrawals / deposits by
     purpose, and what the Travels banks hold in total. Deposit / Withdraw post
     through the Capital & Financing desk's own code
     (PartyStatementController::companyFunds); Manage Bank is not touched.
     Expects $funds = CompanyFunds::summary(), $owners, $sgn, $fmt. --}}
@php
    $fundsUrl = \Illuminate\Support\Facades\Route::has('role.party-statement.company-funds')
        ? route('role.party-statement.company-funds', ['role' => request()->route('role')]) : null;
    $ft = $funds['totals'] ?? [];
    $purposes = \App\Services\CompanyFunds::PURPOSES;
@endphp
<style>
.cf-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 6px; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
.cf-kpi { background: #fff; border: 1px solid #e8edf4; border-radius: 10px; padding: 7px 10px; }
.cf-kpi-l { font-size: 9.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #94a3b8; }
.cf-kpi-v { font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 2px; font-variant-numeric: tabular-nums; }
.cf-kpi-s { font-size: 10px; color: #94a3b8; margin-top: 1px; }
.cf-sec { padding: 8px 12px 4px; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #4338ca; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.cf-btn { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 8px; font-size: 12px; font-weight: 700; border: 1px solid transparent; cursor: pointer; }
.cf-btn.cf-in { background: #059669; color: #fff; }
.cf-btn.cf-out { background: #dc2626; color: #fff; }
.cf-btn.cf-ghost { background: #fff; color: #334155; border-color: #e2e8f0; }
.cf-scroll { max-height: 420px; overflow: auto; }
#cfModal { position: fixed; inset: 0; z-index: 60; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,.5); padding: 12px; }
#cfModal.is-open { display: flex; }
#cfModal .cf-box { background: #fff; border-radius: 14px; width: 100%; max-width: 560px; box-shadow: 0 20px 50px rgba(0,0,0,.25); }
#cfModal .cf-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 14px 14px 0 0; color: #fff; }
#cfModal .cf-body { padding: 14px 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
#cfModal .cf-body .cf-full { grid-column: 1 / -1; }
#cfModal label { display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 3px; }
#cfModal .cf-f { width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 8px; padding: 7px 9px; font-size: 13px; background: #fff; }
#cfModal .cf-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 10px 16px; border-top: 1px solid #f1f5f9; }
#cfModal .cf-err { grid-column: 1 / -1; color: #dc2626; font-size: 12px; display: none; }
@media (max-width: 560px) { #cfModal .cf-body { grid-template-columns: 1fr; } }
</style>

<div class="ps-panel" id="cfPanel">
    <div class="ps-roster-head flex items-center justify-between gap-3 px-5 py-2" style="flex-wrap:wrap;">
        <p class="ps-roster-title text-sm font-bold flex items-center gap-2">
            <span class="ps-title-ic"><i class="fas fa-sack-dollar"></i></span> Profit &amp; Funds — Travels
        </p>
        @if($fundsUrl && ($funds['ok'] ?? false))
            <div class="flex items-center gap-2">
                <button type="button" class="cf-btn cf-in" onclick="cfOpen('deposit')"><i class="fas fa-arrow-down"></i> Deposit</button>
                <button type="button" class="cf-btn cf-out" onclick="cfOpen('withdraw')"><i class="fas fa-arrow-up"></i> Withdraw</button>
            </div>
        @endif
    </div>

    @if(! ($funds['ok'] ?? false))
        <div class="ps-empty">Profit &amp; funds could not be read on this server.</div>
    @else
        <div class="cf-kpis">
            <div class="cf-kpi"><div class="cf-kpi-l">Profit earned</div><div class="cf-kpi-v" style="color:{{ $ft['profit'] < 0 ? '#dc2626' : '#047857' }}">{{ $sgn($ft['profit']) }}</div><div class="cf-kpi-s">Flight {{ $sgn($ft['cf_profit']) }} · Ticketing {{ $sgn($ft['tk_profit']) }} · Visa {{ $sgn($ft['va_profit'] ?? 0) }}</div></div>
            <div class="cf-kpi"><div class="cf-kpi-l">Received — Contract Flight</div><div class="cf-kpi-v">{{ $fmt($ft['received']) }}</div><div class="cf-kpi-s">of {{ $fmt($ft['revenue']) }} billed</div></div>
            <div class="cf-kpi"><div class="cf-kpi-l">Not yet received</div><div class="cf-kpi-v" style="color:#b45309">{{ $fmt($ft['due']) }}</div><div class="cf-kpi-s">counted as profit, still due</div></div>
            <div class="cf-kpi"><div class="cf-kpi-l">Withdrawn by owners</div><div class="cf-kpi-v" style="color:#dc2626">{{ $fmt($ft['withdrawn']) }}</div><div class="cf-kpi-s">drawings, all entries</div></div>
            <div class="cf-kpi"><div class="cf-kpi-l">Invested</div><div class="cf-kpi-v" style="color:#059669">{{ $fmt($ft['invested']) }}</div><div class="cf-kpi-s">owner deposits</div></div>
            <div class="cf-kpi"><div class="cf-kpi-l">Profit held</div><div class="cf-kpi-v">{{ $sgn($ft['held']) }}</div><div class="cf-kpi-s">earned − withdrawn</div></div>
            <div class="cf-kpi"><div class="cf-kpi-l">Travels banks — total</div><div class="cf-kpi-v" style="color:#1d4ed8">{{ $sgn($ft['bank']) }}</div><div class="cf-kpi-s">{{ $funds['banks']->count() }} accounts, summed</div></div>
        </div>

        {{-- Department-wise profit (owner, 2026-10-12). Ticketing passes Contract
             Flight's tickets on at net price, so a ticket's profit is counted once:
             on the passenger's whole trip, never on the ticket. --}}
        @php $tkf = $funds['ticketing'] ?? ['ok' => false]; $tt = $tkf['totals'] ?? []; @endphp
        <div class="cf-sec"><span><i class="fas fa-sitemap"></i> Profit by department</span></div>
        <div class="cf-scroll">
            <table class="ps-recent">
                <thead><tr><th>Department</th><th>How profit is made</th><th style="text-align:right">Revenue</th><th style="text-align:right">Cost</th><th style="text-align:right">Profit</th></tr></thead>
                <tbody>
                    <tr>
                        <td><b>Contract Flight</b></td>
                        <td style="color:#64748b">Per passport: package + charges − tickets (at net) − services − changes</td>
                        <td style="text-align:right">{{ $fmt($ft['cf_revenue']) }}</td>
                        <td style="text-align:right">{{ $fmt($ft['cf_cost']) }}</td>
                        <td style="text-align:right"><span class="ps-bal" style="color:{{ $ft['cf_profit'] < 0 ? '#dc2626' : '#047857' }}">{{ $sgn($ft['cf_profit']) }}</span></td>
                    </tr>
                    @if($tkf['ok'] ?? false)
                    <tr>
                        <td><b>Ticketing</b></td>
                        <td style="color:#64748b">Direct sales: agent price − cost price {{ $sgn($tt['direct_profit']) }}
                            @if(abs($tt['change_revenue'] - $tt['change_cost']) >= 1) · changes {{ $sgn($tt['change_revenue'] - $tt['change_cost']) }}@endif
                            · to Contract Flight at net {{ $fmt($tt['transfer']) }} ({{ $tt['transfer_count'] }} tickets, no profit)
                            @if($tt['own_loss'] > 0) · own losses −{{ $fmt($tt['own_loss']) }}@endif</td>
                        <td style="text-align:right">{{ $fmt($tt['revenue']) }}</td>
                        <td style="text-align:right">{{ $fmt($tt['cost']) }}</td>
                        <td style="text-align:right"><span class="ps-bal" style="color:{{ $tt['profit'] < 0 ? '#dc2626' : '#047857' }}">{{ $sgn($tt['profit']) }}</span></td>
                    </tr>
                    @else
                    <tr><td><b>Ticketing</b></td><td colspan="4" style="color:#94a3b8">Could not be read on this server.</td></tr>
                    @endif
                    @php $vaf = $funds['visa'] ?? ['ok' => false]; $vt = $vaf['totals'] ?? []; @endphp
                    @if($vaf['ok'] ?? false)
                    <tr>
                        <td><b>Visa</b></td>
                        <td style="color:#64748b">Per invoice: agent price − cost of its applications and services ({{ $vt['invoices'] }} invoices){{ $vt['unbilled_count'] ? ' · ' . $vt['unbilled_count'] . ' items not invoiced yet, not counted' : '' }}</td>
                        <td style="text-align:right">{{ $fmt($vt['revenue']) }}</td>
                        <td style="text-align:right">{{ $fmt($vt['cost']) }}</td>
                        <td style="text-align:right"><span class="ps-bal" style="color:{{ $vt['profit'] < 0 ? '#dc2626' : '#047857' }}">{{ $sgn($vt['profit']) }}</span></td>
                    </tr>
                    @else
                    <tr><td><b>Visa</b></td><td colspan="4" style="color:#94a3b8">Could not be read on this server.</td></tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc;font-weight:800;">
                        <td style="padding:10px 12px;">Company</td><td style="padding:10px 12px;color:#64748b;font-weight:600">Contract Flight + Ticketing + Visa{{ ($tt['stock'] ?? 0) > 0 ? ' · unsold ticket stock ' . $fmt($tt['stock']) . ' not counted' : '' }}</td>
                        <td></td><td></td>
                        <td style="text-align:right;padding:10px 12px;">{{ $sgn($ft['profit']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="cf-sec"><span><i class="fas fa-user-group"></i> Per-head profit &amp; loss</span></div>
        <div class="cf-scroll">
            <table class="ps-recent">
                <thead>
                    <tr>
                        <th>Passenger</th><th>Flight</th><th>Agent</th>
                        <th style="text-align:right">Revenue</th><th style="text-align:right">Cost</th><th style="text-align:right">Profit</th>
                        <th style="text-align:right">Received</th><th style="text-align:right">Due</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($funds['heads'] as $h)
                        <tr>
                            <td><div class="ps-nm">{{ $h['name'] }}</div>@if($h['status'])<div class="ps-sub">{{ $h['status'] }}</div>@endif</td>
                            <td>{{ $h['flight'] }}</td>
                            <td>{{ $h['agent'] ?: '—' }}</td>
                            <td style="text-align:right;">{{ $fmt($h['revenue']) }}</td>
                            <td style="text-align:right;"><span class="ps-amt-dr">{{ $fmt($h['cost']) }}</span></td>
                            <td style="text-align:right;"><span class="ps-bal" style="color:{{ $h['profit'] < 0 ? '#dc2626' : '#047857' }};">{{ $sgn($h['profit']) }}</span></td>
                            <td style="text-align:right;">@if($h['received'])<span class="ps-amt-cr">{{ $fmt($h['received']) }}</span>@else<span class="ps-amt-none">—</span>@endif</td>
                            <td style="text-align:right;">@if($h['due'])<span style="color:#b45309;font-weight:600;">{{ $fmt($h['due']) }}</span>@else<span class="ps-amt-none">—</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="ps-empty">No passengers yet.</td></tr>
                    @endforelse
                </tbody>
                @if($funds['heads']->isNotEmpty())
                <tfoot>
                    <tr style="font-weight:800;background:#f8fafc;">
                        <td colspan="3" style="padding:10px 12px;">Total</td>
                        <td style="text-align:right;padding:10px 12px;">{{ $fmt($ft['revenue']) }}</td>
                        <td style="text-align:right;padding:10px 12px;">{{ $fmt($ft['cost']) }}</td>
                        <td style="text-align:right;padding:10px 12px;">{{ $sgn($ft['cf_profit']) }}</td>
                        <td style="text-align:right;padding:10px 12px;">{{ $fmt($ft['received']) }}</td>
                        <td style="text-align:right;padding:10px 12px;">{{ $fmt($ft['due']) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        <div class="cf-sec" style="border-top:1px solid #e2e8f0;"><span><i class="fas fa-right-left"></i> Deposits &amp; withdrawals — by purpose</span></div>
        <div class="cf-scroll" style="max-height:300px;">
            <table class="ps-recent">
                <thead><tr><th>Date</th><th>Purpose</th><th>Details</th><th>By / for</th><th style="text-align:right">In</th><th style="text-align:right">Out</th></tr></thead>
                <tbody>
                    @forelse($funds['moves'] as $m)
                        <tr>
                            <td class="ps-date">{{ \Carbon\Carbon::parse($m['date'])->format('d/m/Y') }}</td>
                            <td><div class="ps-nm">{{ \App\Services\CompanyFunds::purposeLabel($m['purpose']) }}</div>@if($m['reference'])<div class="ps-vno">{{ $m['reference'] }}</div>@endif</td>
                            <td>{{ $m['details'] ?: '—' }}</td>
                            <td>{{ $m['who'] ?: '—' }}</td>
                            <td style="text-align:right;">@if($m['direction'] === 'in')<span class="ps-amt-cr">{{ $sgn($m['amount']) }}</span>@else<span class="ps-amt-none">—</span>@endif</td>
                            <td style="text-align:right;">@if($m['direction'] === 'out')<span class="ps-amt-dr">{{ $sgn($m['amount']) }}</span>@else<span class="ps-amt-none">—</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ps-empty">No deposits or withdrawals yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

@if($fundsUrl && ($funds['ok'] ?? false))
{{-- Closes only on ✕ / Cancel, never on a backdrop click (owner, 2026-09-22). --}}
<div id="cfModal" role="dialog" aria-modal="true">
    <div class="cf-box">
        <div class="cf-head" id="cfHead">
            <strong id="cfTitle">Deposit</strong>
            <button type="button" onclick="cfClose()" style="background:rgba(255,255,255,.2);border:0;color:#fff;border-radius:8px;width:28px;height:28px;cursor:pointer;">✕</button>
        </div>
        <div class="cf-body">
            <div class="cf-full">
                <label for="cfPurpose">Purpose</label>
                <select id="cfPurpose" class="cf-f" onchange="cfPaint()"></select>
            </div>
            <div class="cf-full" id="cfOwnerWrap">
                <label for="cfOwner">Whose money</label>
                <select id="cfOwner" class="cf-f">
                    <option value="">— Choose —</option>
                    @foreach($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div class="cf-full">
                <label for="cfBank">Travels bank / cash account</label>
                <select id="cfBank" class="cf-f">
                    <option value="">— Choose —</option>
                    @foreach($funds['banks'] as $b)<option value="{{ $b['id'] }}" data-bal="{{ $b['balance'] }}">{{ $b['name'] }} — holds ৳{{ number_format($b['balance'], 2) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="cfAmount">Amount (৳)</label>
                <input type="number" id="cfAmount" class="cf-f" min="0.01" step="0.01">
            </div>
            <div>
                <label for="cfDate">Date</label>
                <input type="date" id="cfDate" class="cf-f">
            </div>
            <div class="cf-full">
                <label for="cfNotes">Note / reference <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
                <input type="text" id="cfNotes" class="cf-f" maxlength="1000">
            </div>
            <div class="cf-err" id="cfErr"></div>
        </div>
        <div class="cf-foot">
            <button type="button" class="cf-btn cf-ghost" onclick="cfClose()">Cancel</button>
            <button type="button" class="cf-btn" id="cfSave" onclick="cfSave()">Save</button>
        </div>
    </div>
</div>
<script>
(function () {
    const PURPOSES = @json($purposes);
    const URL = @json($fundsUrl);
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let dir = 'deposit';
    window.cfOpen = function (d) {
        dir = d;
        const inn = d === 'deposit';
        document.getElementById('cfTitle').textContent = inn ? 'Deposit into Travels' : 'Withdraw from Travels';
        document.getElementById('cfHead').style.background = inn ? '#059669' : '#dc2626';
        const save = document.getElementById('cfSave');
        save.textContent = inn ? 'Record deposit' : 'Record withdrawal';
        save.className = 'cf-btn ' + (inn ? 'cf-in' : 'cf-out');
        document.getElementById('cfPurpose').innerHTML = Object.entries(PURPOSES[d] || {})
            .map(([k, v]) => `<option value="${k}">${v.label}</option>`).join('');
        ['cfAmount', 'cfNotes', 'cfBank', 'cfOwner'].forEach(id => { document.getElementById(id).value = ''; });
        document.getElementById('cfDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('cfErr').style.display = 'none';
        cfPaint();
        document.getElementById('cfModal').classList.add('is-open');
    };
    window.cfClose = function () { document.getElementById('cfModal').classList.remove('is-open'); };
    window.cfPaint = function () {
        const p = (PURPOSES[dir] || {})[document.getElementById('cfPurpose').value] || {};
        document.getElementById('cfOwnerWrap').style.display = p.capital ? '' : 'none';
    };
    window.cfSave = function () {
        const err = document.getElementById('cfErr');
        const show = m => { err.textContent = m; err.style.display = 'block'; };
        const purpose = document.getElementById('cfPurpose').value;
        const p = (PURPOSES[dir] || {})[purpose] || {};
        const bank = document.getElementById('cfBank');
        const amount = Number(document.getElementById('cfAmount').value) || 0;
        if (!bank.value) return show('Choose the Travels bank or cash account.');
        if (amount <= 0) return show('Enter the amount.');
        if (p.capital && !document.getElementById('cfOwner').value) return show('Choose whose money it is.');
        const bal = Number(bank.selectedOptions[0]?.dataset.bal || 0);
        if (dir === 'withdraw' && amount > bal) return show('That account holds only ৳' + bal.toLocaleString() + '.');
        const btn = document.getElementById('cfSave'); btn.disabled = true;
        fetch(URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({
                direction: dir, purpose, amount, bank_id: bank.value,
                date: document.getElementById('cfDate').value,
                owner_user_id: document.getElementById('cfOwner').value || null,
                notes: document.getElementById('cfNotes').value || null,
            }),
        }).then(r => r.json().then(j => ({ ok: r.ok, j }))).then(({ ok, j }) => {
            if (!ok || !j.success) {
                show(j.message || (j.errors ? Object.values(j.errors)[0][0] : 'Could not save.'));
                return;
            }
            cfClose();
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Saved', text: j.message, timer: 1600, showConfirmButton: false }).then(() => location.reload());
            else location.reload();
        }).catch(() => show('Network error.')).finally(() => { btn.disabled = false; });
    };
})();
</script>
@endif
