<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $label }} List — {{ optional($company)->name ?? config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
@php
    $partyRows = collect($rows);
    $activeCount = $partyRows->where('status', 'Active')->count();
    $debitTotal = (float) $partyRows->where('balance_type', 'Dr')->sum('balance');
    $creditTotal = (float) $partyRows->where('balance_type', 'Cr')->sum('balance');
    $netTotal = $debitTotal - $creditTotal;
    $filterLabels = collect([
        $search ? 'Search: '.$search : null,
        ($filters['service_category'] ?? null) ? 'Service: '.ucfirst($filters['service_category']) : null,
        ($filters['vendor_type_id'] ?? null) ? 'Vendor type filter applied' : null,
        ($filters['vendor_category_id'] ?? null) ? 'Work category filter applied' : null,
    ])->filter();
@endphp
<style>
    @page { size: A4 landscape; margin: 12mm; }
    :root {
        --tc:#4338ca; --tbg:#eef2ff; --thd:#4f46e5; --bdr:#e2e8f0;
        --txt:#1e293b; --tf:"Montserrat",sans-serif; --ff:"Inter",sans-serif;
        --nf:"Space Mono",monospace;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
    body{font-family:var(--ff);padding:28px;background:#f0f2f5;color:var(--txt);font-size:12px}
    .btn-row{max-width:1123px;margin:0 auto 12px;display:flex;gap:8px;justify-content:flex-end}
    .btn-row a,.btn-row button{padding:7px 18px;border-radius:4px;font:600 12px var(--tf);cursor:pointer;text-decoration:none}
    .btn-back{background:#6b7280;color:#fff;border:0}.btn-print{background:#4f46e5;color:#fff;border:0}
    .card{max-width:1123px;margin:auto;background:#fff;padding:40px;border-radius:4px;box-shadow:0 4px 20px rgba(0,0,0,.06)}
    .hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
    .co-info{text-align:right;font-size:12px;line-height:1.7}.co-info strong{font-family:var(--tf);font-size:16px;display:block}
    .logo-txt{font-family:var(--tf);color:var(--tc);font-size:26px;font-weight:800}
    .label-bar{text-align:center;background:var(--tbg);color:var(--tc);text-transform:uppercase;letter-spacing:2px;font:700 11px var(--tf);padding:10px;border-top:2px solid var(--tc);margin-bottom:20px}
    .meta-row{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:18px}
    .scope h4{font:700 11px var(--tf);color:var(--tc);text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px}
    .scope p{font-size:13px;line-height:1.7}.scope .filters{font-size:11px;color:#64748b;line-height:1.6;max-width:700px}
    .meta-tbl td{padding:2px 0 2px 18px;font-weight:600;font-size:12px;white-space:nowrap}.meta-tbl .nf{font:11px var(--nf)}
    .summary-strip{display:flex;border:1px solid var(--bdr);border-radius:6px;overflow:hidden;margin-bottom:18px}
    .sum-card{flex:1;padding:10px 14px;border-right:1px solid var(--bdr)}.sum-card:last-child{border-right:0;background:var(--thd)}
    .sum-label{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;font-weight:600;margin-bottom:3px}
    .sum-card:last-child .sum-label{color:#c7d2fe}.sum-value{font:700 12px var(--nf)}.sum-card:last-child .sum-value{color:#fff;font-size:13px}
    .ledger{width:100%;table-layout:fixed;border-collapse:collapse;margin-bottom:20px;font-size:10px}
    .ledger th{background:var(--thd);color:#fff;padding:8px 6px;text-align:left;white-space:nowrap;overflow:hidden;font:700 8px var(--tf);text-transform:uppercase;letter-spacing:.04em}
    .ledger td{padding:6px 7px;border-bottom:1px solid var(--bdr);vertical-align:top;overflow-wrap:anywhere}
    .ledger tbody tr:nth-child(even) td{background:#f8f9ff}.ledger .mono{font:9px var(--nf);overflow-wrap:normal}.ledger .r{text-align:right;white-space:nowrap}
    .ledger .sub{display:block;font-size:8.5px;color:#94a3b8;margin-top:2px}.code{font:700 8.5px var(--nf);color:var(--tc)}
    .dr{color:#dc2626;font-weight:700}.cr{color:#16a34a;font-weight:700}.none{color:#cbd5e1}
    .badge{display:inline-block;padding:1px 6px;border-radius:9px;font-size:8px;font-weight:700}.active{background:#dcfce7;color:#15803d}.inactive{background:#fee2e2;color:#b91c1c}
    .ledger tfoot tr{background:var(--thd);color:#fff}.ledger tfoot td{padding:8px 10px;border:0;font:700 9px var(--tf)}.ledger tfoot .r{font-family:var(--nf)}
    .note{font-size:11px;color:#64748b;line-height:1.7}
    .sig{margin-top:30px;display:flex;justify-content:flex-end}.sig-c{text-align:center;width:200px}.sig-l{border-top:1px solid #333;margin-top:6px;padding-top:4px;font:700 10px var(--tf)}
    @media print{
        body{background:#fff!important;padding:0!important}.btn-row{display:none!important}.card{box-shadow:none!important;padding:0!important;max-width:100%!important}
        .label-bar{padding:7px;margin-bottom:12px}.ledger{font-size:9px}.ledger td{padding:4px 6px}.ledger th{padding:6px}.ledger tr{page-break-inside:avoid}.sig{margin-top:20px}
    }
</style>
</head>
<body>
<div class="btn-row">
    <a class="btn-back" href="javascript:void(0)" onclick="if(history.length>1){history.back();}else{window.close();}return false;">← Back</a>
    <button class="btn-print" type="button" onclick="window.print()">🖨 Print</button>
</div>

<div class="card">
    <div class="hdr">
        <div>
            @if(optional($company)->logo)
                @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
                <img src="{{ $logoSrc }}" alt="" style="max-width:110px;max-height:110px;object-fit:contain">
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

    <div class="label-bar">{{ $label }} List</div>

    <div class="meta-row">
        <div class="scope">
            <h4>Scope</h4>
            <p><strong>{{ $filterLabels->isEmpty() ? 'All '.strtolower($label).' records' : 'Filtered '.strtolower($label).' records' }}</strong></p>
            <div class="filters">{{ $filterLabels->isEmpty() ? 'No filters applied' : $filterLabels->implode(' · ') }}</div>
        </div>
        <table class="meta-tbl">
            <tr><td>{{ $label }}s</td><td class="nf">{{ $partyRows->count() }}</td></tr>
            <tr><td>Balances as of</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
            <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y H:i') }}</td></tr>
            <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
        </table>
    </div>

    <div class="summary-strip">
        <div class="sum-card"><div class="sum-label">Total records</div><div class="sum-value">{{ $partyRows->count() }}</div></div>
        <div class="sum-card"><div class="sum-label">Active</div><div class="sum-value" style="color:#16a34a">{{ $activeCount }}</div></div>
        <div class="sum-card"><div class="sum-label">Total debit</div><div class="sum-value" style="color:#dc2626">৳{{ number_format($debitTotal, 2) }}</div></div>
        <div class="sum-card"><div class="sum-label">Total credit</div><div class="sum-value" style="color:#16a34a">৳{{ number_format($creditTotal, 2) }}</div></div>
        <div class="sum-card"><div class="sum-label">Net position</div><div class="sum-value">৳{{ number_format(abs($netTotal), 2) }} {{ $netTotal < 0 ? 'Cr' : 'Dr' }}</div></div>
    </div>

    <table class="ledger">
        <thead>
            <tr>
                <th style="width:3%">#</th><th style="width:8%">{{ $label }} ID</th><th style="width:14%">Name</th>
                <th style="width:12%">Services / Categories</th><th style="width:11%">Contact Person</th>
                <th style="width:14%">Email</th><th style="width:9%">Phone</th><th style="width:14%">Address</th>
                <th class="r" style="width:8%">Balance</th><th style="width:7%">Last Txn</th><th style="width:6%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($partyRows as $row)
                <tr>
                    <td class="mono">{{ $loop->iteration }}</td>
                    <td><span class="code">{{ $row['party_id'] }}</span></td>
                    <td><strong>{{ $row['name'] }}</strong></td>
                    <td>{{ $row['services'] ?: '—' }}</td>
                    <td>{{ $row['contact_person'] ?: '—' }}</td>
                    <td>{{ $row['email'] ?: '—' }}</td>
                    <td class="mono">{{ $row['phone'] ?: '—' }}</td>
                    <td>{{ $row['address'] ?: '—' }}</td>
                    <td class="r {{ $row['balance_type'] === 'Dr' ? 'dr' : ($row['balance_type'] === 'Cr' ? 'cr' : 'none') }}">{{ number_format($row['balance'], 2) }} {{ $row['balance_type'] }}</td>
                    <td class="mono">{{ $row['last_transaction'] ?: '—' }}</td>
                    <td><span class="badge {{ $row['status'] === 'Active' ? 'active' : 'inactive' }}">{{ $row['status'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="11" style="text-align:center;padding:20px;color:#94a3b8">No {{ strtolower($label) }} records found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td colspan="8">{{ $label }} list — {{ $partyRows->count() }} records</td><td class="r">৳{{ number_format(abs($netTotal), 2) }} {{ $netTotal < 0 ? 'Cr' : 'Dr' }}</td><td colspan="2"></td></tr>
        </tfoot>
    </table>

    <div class="note">This sheet lists the current filtered {{ strtolower($label) }} records and their whole-ledger balances. Dr = receivable from party; Cr = payable or advance position.</div>
    <div class="sig">
        <div class="sig-c">
            <x-signature :company="$company" />
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>
</div>
<script>window.onload = function () { window.print(); };</script>
</body>
</html>
