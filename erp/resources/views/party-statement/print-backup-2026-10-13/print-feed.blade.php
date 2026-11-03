<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Party Ledger Entries — {{ optional($company)->name ?? config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    {{-- The bank feed's sheet (banks/print-recent), read from the party's
         side: the bank column becomes the party, the beneficiary column
         becomes "paid via", Company Balance becomes the party's balance
         after the row. Landscape for the same reason — seven columns. --}}
    @page { size: A4 landscape; margin: 10mm; }

    :root {
        --tc:  #4338ca;
        --tbg: #eef2ff;
        --thd: #4f46e5;
        --bdr: #e2e8f0;
        --txt: #1e293b;
        --tf:  "Montserrat", sans-serif;
        --ff:  "Inter", sans-serif;
        --nf:  "Space Mono", monospace;
    }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body { font-family: var(--ff); padding: 28px; background: #f0f2f5; color: var(--txt); font-size: 12px; }

    .card { max-width: 1100px; margin: auto; background: #fff; padding: 36px 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }

    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 20px;
    }

    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 18px; }
    .scope h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .scope p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .scope .accts { font-size: 11px; color: #64748b; line-height: 1.6; max-width: 520px; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; white-space: nowrap; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    .summary-strip { display: flex; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 18px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; background: var(--thd); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card:last-child .sum-label { color: #c7d2fe; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card:last-child .sum-value { color: #fff; font-size: 13px; }

    .ledger { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
    .ledger th {
        background: var(--thd); color: #fff; padding: 8px 10px; text-align: left;
        font-family: var(--tf); font-size: 9px; text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 7px 10px; border-bottom: 1px solid var(--bdr); vertical-align: top; }
    .ledger td.r    { text-align: right; white-space: nowrap; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger .sub { display: block; font-size: 9.5px; color: #94a3b8; margin-top: 2px; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 8px 10px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); }

    .flow { display: inline-block; padding: 1px 6px; border-radius: 9px; font-size: 8.5px; font-weight: 700; margin-left: 4px; vertical-align: middle; }
    .flow-up   { background: #fee2e2; color: #b91c1c; }
    .flow-down { background: #dcfce7; color: #15803d; }
    .flow-cash   { background: #dcfce7; color: #15803d; }
    .flow-bank   { background: #dbeafe; color: #1e40af; }
    .flow-cheque { background: #fef3c7; color: #92400e; }
    .flow-card   { background: #ede9fe; color: #5b21b6; }
    .flow-mobile { background: #ffe4e6; color: #be123c; }
    .code { font-family: var(--nf); font-size: 9.5px; color: var(--tc); font-weight: 700; }

    {{-- Debit raises what a party owes, credit settles it — the party
         statement's colours, not the bank feed's. --}}
    .debit      { color: #dc2626; font-weight: 600; }
    .credit     { color: #16a34a; font-weight: 600; }
    .balance-dr { color: #1d4ed8; font-weight: 700; }
    .balance-cr { color: #dc2626; font-weight: 700; }

    .note { font-size: 11px; color: #64748b; line-height: 1.7; }

    .sig { margin-top: 30px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    @media print {
        body       { background: #fff !important; padding: 0 !important; }
        .btn-row   { display: none !important; }
        .card      { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        .label-bar { padding: 7px; margin-bottom: 12px; }
        .ledger    { font-size: 10px; }
        .ledger td { padding: 5px 8px; }
        .ledger th { padding: 6px 8px; }
        .ledger tr { page-break-inside: avoid; }
        .sig       { margin-top: 20px; }
    }
</style>
</head>
<body>

@php
    $types = \App\Http\Controllers\Dashboard\PartyStatementController::TYPES;
    $periodText = $selection
        ? $selection . ' selected entr' . ($selection === 1 ? 'y' : 'ies')
        : ($dateFrom && $dateTo && $dateFrom === $dateTo
            ? \Carbon\Carbon::parse($dateFrom)->format('d M Y')
            : ($dateFrom || $dateTo
                ? ($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'Start') . ' → ' . ($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : now()->format('d M Y'))
                : 'All Time'));
    $kindLabel = [
        'party_payment' => 'Payment', 'party_payment_reversal' => 'Payment reversal',
        'customer_advance_received_reversal' => 'Customer advance reversal', 'supplier_advance_paid_reversal' => 'Supplier advance reversal',
        'bank_transfer' => 'Payment', 'party_invoice' => 'Invoice',
        'ticket_sale' => 'Ticket', 'ticket_purchase' => 'Ticket purchase', 'ticket_purchase_refund' => 'Ticket cancelled — refund', 'contract_flight_reissue_fee' => 'Reissue fee', 'ticket_change_fee' => 'Ticket re-issue charge', 'ticket_change_fare' => 'Fare difference', 'contract_flight_reissue_fare' => 'Fare difference', 'ticket_change_charge' => 'Ticket change charge', 'ticket_sale_reversal' => 'Ticket sale reversed', 'vendor_refund' => 'Refund received', 'flight_sale' => 'Flight',
        'file_sale' => 'File', 'visa_sale' => 'Visa', 'visa_process' => 'Visa processing',
        'contract_flight_passenger_agent' => 'Flight passenger',
        'contract_flight_passenger_change' => 'Flight change charge',
        'contract_flight_passenger_agent_reversal' => 'Flight package reversal', 'contract_flight_airline_penalty' => 'Airline penalty', 'contract_flight_fare_difference' => 'Fare difference',
        'contract_flight_service_cost' => 'Flight service cost', 'opening_balance' => 'Opening',
    ];
    $viaPill = ['cash' => ['Cash', 'flow-cash'], 'bank_transfer' => ['Bank', 'flow-bank'], 'cheque' => ['Cheque', 'flow-cheque'], 'card' => ['Card', 'flow-card'], 'mobile_banking' => ['Mobile', 'flow-mobile']];
    $net = $totalDebit - $totalCredit;
@endphp

<div class="btn-row" style="max-width:1100px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#4f46e5;border:1px solid #4f46e5;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
</div>

<div class="card">

    <div class="hdr">
        <div>
            @if(optional($company)->logo)
            @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
            <img src="{{ $logoSrc }}" alt="" style="max-width:80px;">
            @else
            <div class="logo-txt">{{ optional($company)->name ?? config('app.name') }}</div>
            @endif
        </div>
        <div class="co-info">
            <strong>{{ optional($company)->name ?? config('app.name') }}</strong>
            @if(optional($company)->address){{ $company->address }}<br>@endif
            {{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}
        </div>
    </div>

    <div class="label-bar">Party Ledger Entries — {{ $selection ? 'Selected Entries' : 'Period Report' }}</div>

    <div class="meta-row">
        <div class="scope">
            <h4>Scope</h4>
            <p><strong>Every agent, vendor and customer</strong></p>
            <div class="accts">
                @foreach($types as $t => $m)
                    {{ $counts[$t] ?? 0 }} {{ strtolower($m['label']) }} entr{{ ($counts[$t] ?? 0) === 1 ? 'y' : 'ies' }}{{ $loop->last ? '' : ' · ' }}
                @endforeach
            </div>
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Showing</td><td class="nf">{{ $periodText }}</td></tr>
                <tr><td>Entries</td><td class="nf">{{ $rows->count() }}</td></tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y H:i') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">Total Debit (charged / billed)</div>
            <div class="sum-value" style="color:#dc2626;">৳{{ number_format($totalDebit, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Credit (received / paid)</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($totalCredit, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Net Movement</div>
            <div class="sum-value" style="color:{{ $net < 0 ? '#16a34a' : '#dc2626' }};">{{ $net < 0 ? '−' : '+' }}৳{{ number_format(abs($net), 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Entries on this sheet</div>
            <div class="sum-value">{{ $rows->count() }}</div>
        </div>
    </div>

    <table class="ledger">
        <thead>
            <tr>
                <th width="9%">Date</th>
                <th width="20%">Party</th>
                <th width="25%">Entry</th>
                <th width="14%">Paid via</th>
                <th class="r" width="10%">Debit (৳)</th>
                <th class="r" width="10%">Credit (৳)</th>
                <th class="r" width="12%">Party Balance (৳)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
            @php
                $bal   = (float) $r['balance_after'];
                $isDr  = $r['debit'] > 0;
                $receivableRow = $r['type'] && $types[$r['type']]['side'] === 'receivable';
                $flow  = $isDr ? ($receivableRow ? 'Charged' : 'Bill') : ($receivableRow ? 'Received' : 'Paid');
                $recDay = substr((string) $r['recorded_at'], 0, 10);
                $via   = $viaPill[$r['method']] ?? null;
            @endphp
            <tr>
                <td>
                    {{ $r['date'] ? \Carbon\Carbon::parse($r['date'])->format('d M y') : '—' }}
                    @if($recDay === $r['date'])<span class="sub">{{ \Carbon\Carbon::parse($r['recorded_at'])->format('H:i') }}</span>@endif
                </td>
                <td>
                    <strong>{{ $r['party'] ?? '—' }}</strong>
                    <span class="flow {{ $isDr ? 'flow-up' : 'flow-down' }}">{{ $flow }}</span>
                    @if($r['code'])<span class="sub">{{ $types[$r['type']]['label'] }} <span class="code">{{ $r['code'] }}</span></span>@endif
                </td>
                <td>
                    {{ ($r['kind'] === 'contract_flight_passenger_agent_reversal' && (float) ($r['debit'] ?? 0) > 0) ? 'Flight package charged again' : ($kindLabel[$r['kind']] ?? ucwords(str_replace('_', ' ', (string) $r['kind']))) }}
                    @if($r['remarks'])<span class="sub">{{ \Illuminate\Support\Str::limit($r['remarks'], 90) }}</span>@endif
                    {{-- The screen's Details column: what the posting module says the entry was for. --}}
                    @foreach(($r['reason'] ?? []) as $line)@if(!empty($line['text']))<span class="sub">{{ $line['text'] }}</span>@endif @endforeach
                    @if($r['ref'] || !empty($r['bank_entry']['txn_no']))<span class="sub" style="font-family:var(--nf);">{{ $r['ref'] ?: $r['bank_entry']['txn_no'] }}</span>@endif
                </td>
                <td>
                    @if($via || $r['bank'])
                        <strong>{{ $r['bank'] ?: ($via[0] ?? '') }}</strong>@if($via)<span class="flow {{ $via[1] }}">{{ $via[0] }}</span>@endif
                        @if($r['bank_no'])<span class="sub">A/C {{ $r['bank_no'] }}</span>@endif
                    @else
                        —
                    @endif
                </td>
                <td class="r {{ $r['debit']  > 0 ? 'debit'  : '' }}">{{ $r['debit']  > 0 ? number_format($r['debit'],  2) : '—' }}</td>
                <td class="r {{ $r['credit'] > 0 ? 'credit' : '' }}">{{ $r['credit'] > 0 ? number_format($r['credit'], 2) : '—' }}</td>
                <td class="r {{ $bal < 0 ? 'balance-cr' : 'balance-dr' }}">{{ number_format(abs($bal), 2) }} {{ $bal < 0 ? 'Cr' : 'Dr' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No entries for this selection.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Total — {{ $periodText }}</td>
                <td class="r">৳{{ number_format($totalDebit, 2) }}</td>
                <td class="r">৳{{ number_format($totalCredit, 2) }}</td>
                <td class="r">{{ $net < 0 ? '−' : '+' }}৳{{ number_format(abs($net), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="note">
        This sheet was generated on {{ now()->format('d M Y \a\t H:i') }} and lists party ledger entries
        @if($selection)
            for the {{ $selection }} entr{{ $selection === 1 ? 'y' : 'ies' }} selected on screen.
        @elseif($dateFrom || $dateTo)
            from {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : 'inception' }} to {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d M Y') : now()->format('d M Y') }}.
        @else
            from inception to date.
        @endif
        Party Balance is that party's balance immediately after each entry — Dr is outstanding, Cr an advance. Debit raises what the party owes (or what we owe a vendor); credit settles it.
    </div>

    <div class="sig">
        <div class="sig-c">
            <x-signature :company="$company" />
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
