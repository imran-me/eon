<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contract Flight — Agent Paid/Due Overall</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 landscape; margin: 10mm; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    :root { --tc: #0f766e; --tbg: #ecfdf5; --thd: #0d9488; --bdr: #e2e8f0; --txt: #1e293b; --tf: "Montserrat", sans-serif; --ff: "Inter", sans-serif; --nf: "Space Mono", monospace; }
    body { font-family: var(--ff); padding: 24px; background: #f0f2f5; color: var(--txt); font-size: 12px; }
    .card { max-width: 1080px; margin: auto; background: #fff; padding: 32px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .co-info { text-align: right; font-size: 11px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 15px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 24px; font-weight: 800; }
    .label-bar { text-align: center; background: var(--tbg); color: var(--tc); text-transform: uppercase; letter-spacing: 2px; font-weight: 700; font-family: var(--tf); font-size: 11px; padding: 9px; border-top: 2px solid var(--tc); margin-bottom: 18px; }
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 16px; font-size: 11px; }
    .meta-row h4 { font-family: var(--tf); color: var(--tc); font-size: 10px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
    .meta-tbl td { padding: 2px 0 2px 16px; font-weight: 600; font-size: 11px; }
    .summary-strip { display: flex; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 18px; }
    .sum-card { flex: 1; padding: 9px 12px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; }
    .sum-label { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; }
    table.rep { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10px; }
    table.rep th { background: var(--thd); color: #fff; padding: 7px 8px; text-align: left; font-family: var(--tf); font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em; }
    table.rep th.r, table.rep td.r { text-align: right; }
    table.rep td { padding: 6px 8px; border-bottom: 1px solid var(--bdr); }
    table.rep tbody tr:nth-child(even) td { background: #f8fefd; }
    @media screen { table.rep tbody tr:hover td { background: #eef2ff; } }
    table.rep td.mono { font-family: var(--nf); font-size: 9.5px; }
    table.rep tfoot tr { background: var(--thd); color: #fff; }
    table.rep tfoot td { padding: 7px 8px; font-weight: 700; border-bottom: none; font-family: var(--nf); font-size: 9.5px; }
    .good { color: #16a34a; font-weight: 700; }
    .bad  { color: #dc2626; font-weight: 700; }
    @media print { body { background: #fff !important; padding: 0 !important; } .btn-row { display: none !important; } .card { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; } table.rep tr { page-break-inside: avoid; } }
</style>
</head>
<body>

<div class="btn-row" style="max-width:1080px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <button onclick="window.print()" style="background:#0d9488;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
</div>

@php
    $money = fn ($v) => '৳' . number_format((float) $v, 0);
    $date  = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M y') : '—';
    $totalInvoiced = $rows->sum('invoiced');
    $totalPaid = $rows->sum('paid');
    $totalDue = $rows->sum('due');
@endphp

<div class="card">
    <div class="hdr">
        <div class="logo-txt">{{ $company->name ?? 'EPAL GROUP' }}</div>
        <div class="co-info">
            <strong>{{ $company->name ?? 'EPAL GROUP' }}</strong>
            {{ $company->address ?? '' }}<br>
            {{ $company->phone ?? '' }} @if(!empty($company->email)) | {{ $company->email }} @endif
        </div>
    </div>

    <div class="label-bar">Contract Flight — Agent Paid / Due — Overall</div>

    <div class="meta-row">
        <div>
            <h4>Report Scope</h4>
            <table class="meta-tbl">
                <tr><td>Agents with commission</td></tr>
                <tr><td>Type: Contract Flight only</td></tr>
            </table>
        </div>
        <div>
            <h4>&nbsp;</h4>
            <table class="meta-tbl">
                <tr><td>Period</td><td>{{ $date($from) }} → {{ $date($to) }}</td></tr>
                <tr><td>Generated</td><td>{{ now()->format('M Y, H:i') }}</td></tr>
                <tr><td>Printed By</td><td>{{ $generatedBy ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="summary-strip">
        <div class="sum-card"><div class="sum-label">Agents</div><div class="sum-value">{{ $rows->count() }}</div></div>
        <div class="sum-card"><div class="sum-label">Invoiced</div><div class="sum-value">{{ $money($totalInvoiced) }}</div></div>
        <div class="sum-card"><div class="sum-label">Paid</div><div class="sum-value good">{{ $money($totalPaid) }}</div></div>
        <div class="sum-card"><div class="sum-label">Due</div><div class="sum-value bad">{{ $money($totalDue) }}</div></div>
    </div>

    <table class="rep">
        <thead>
            <tr><th>#</th><th>Agent</th><th class="r">Passengers</th><th class="r">Invoiced</th><th class="r">Paid</th><th class="r">Due</th></tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="mono">{{ $i + 1 }}</td>
                    <td>{{ $row->agent_name }}</td>
                    <td class="r mono">{{ $row->passenger_count }}</td>
                    <td class="r mono">{{ $money($row->invoiced) }}</td>
                    <td class="r mono good">{{ $money($row->paid) }}</td>
                    <td class="r mono @if($row->due > 0) bad @endif">{{ $row->due > 0 ? $money($row->due) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:16px;color:#94a3b8;">No agent commission has been recorded yet.</td></tr>
            @endforelse
        </tbody>
        @if($rows->isNotEmpty())
        <tfoot>
            <tr><td colspan="3">Total</td><td class="r">{{ $money($totalInvoiced) }}</td><td class="r">{{ $money($totalPaid) }}</td><td class="r">{{ $money($totalDue) }}</td></tr>
        </tfoot>
        @endif
    </table>

    <div style="margin-top:34px;display:flex;justify-content:flex-end;">
        <div style="text-align:center;width:200px;">
            <x-signature :company="$company ?? null" />
            <div style="border-top:1px solid #333;margin-top:6px;padding-top:4px;font-size:10px;font-weight:700;font-family:'Montserrat',sans-serif;">Authorized Signatory</div>
        </div>
    </div>
</div>
</body>
</html>
