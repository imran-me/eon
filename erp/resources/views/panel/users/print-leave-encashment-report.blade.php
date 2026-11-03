<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leave Encashment Report — {{ $employee->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
@page{size:A4 portrait;margin:12mm}*{box-sizing:border-box;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{margin:0;padding:28px;background:#f0f2f5;color:#1e293b;font:12px Inter,sans-serif}.card{max-width:794px;margin:auto;background:#fff;padding:40px;box-shadow:0 4px 20px #0000000f}.hdr,.meta,.summary{display:flex;justify-content:space-between;gap:20px}.hdr{align-items:center;margin-bottom:24px}.logo{font:800 26px Montserrat;color:#3730a3}.company{text-align:right;line-height:1.7}.company strong{display:block;font:700 16px Montserrat}.bar{padding:10px;text-align:center;text-transform:uppercase;letter-spacing:2px;font:700 11px Montserrat;background:#eef2ff;color:#3730a3;border-top:2px solid #3730a3;margin-bottom:24px}.meta{margin-bottom:20px}.meta h4{margin:0 0 5px;color:#3730a3;text-transform:uppercase;font:700 11px Montserrat}.meta p{margin:2px 0;line-height:1.5}.meta table td{padding:2px 0 2px 18px}.mono{font-family:"Space Mono",monospace}.summary{border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:20px}.sum{flex:1;padding:10px 12px;border-right:1px solid #e2e8f0}.sum:last-child{border:0;background:#4f46e5;color:#fff}.label{font-size:9px;text-transform:uppercase;color:#94a3b8}.value{margin-top:3px;font:700 12px "Space Mono"}.sum:last-child .label{color:#c7d2fe}.report{width:100%;border-collapse:collapse;font-size:10px}.report th{padding:7px;background:#4f46e5;color:#fff;text-align:right;text-transform:uppercase;font:600 8px Montserrat}.report th:first-child,.report td:first-child{text-align:left}.report td{padding:7px;border-bottom:1px solid #e2e8f0;text-align:right}.report tbody tr:nth-child(even){background:#f8f9ff}.report tfoot{background:#4f46e5;color:#fff;font-weight:700}.note{margin-top:20px;color:#64748b;line-height:1.6}.sig{margin:42px 0 0 auto;width:200px;border-top:1px solid #333;text-align:center;padding-top:5px;font:700 10px Montserrat}.btns{max-width:794px;margin:0 auto 12px;display:flex;justify-content:flex-end;gap:8px}.btns a,.btns button{border:0;border-radius:4px;padding:7px 18px;color:#fff;text-decoration:none;font:600 12px Montserrat;cursor:pointer}.back{background:#6b7280}.print{background:#4f46e5}@media print{body{padding:0;background:#fff}.btns{display:none}.card{padding:0;box-shadow:none;max-width:100%}}
</style>
</head>
<body>
<div class="btns"><a class="back" href="javascript:history.back()">← Back</a><button class="print" onclick="window.print()">Print</button></div>
<div class="card">
    <div class="hdr">
        <div>@if(optional($company)->logo)<img src="{{ file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png') }}" alt="" style="max-width:80px">@else<div class="logo">{{ optional($company)->name ?? config('app.name') }}</div>@endif</div>
        <div class="company"><strong>{{ optional($company)->name ?? config('app.name') }}</strong>@if(optional($company)->address){{ $company->address }}<br>@endif{{ optional($company)->phone }}{{ optional($company)->phone && optional($company)->email ? ' | ' : '' }}{{ optional($company)->email }}</div>
    </div>
    <div class="bar">Leave Encashment Report</div>
    <div class="meta">
        <div><h4>Employee Details</h4><p><strong>{{ $employee->name }}</strong></p>@if($employee->profile->designation ?? null)<p>{{ $employee->profile->designation->name }}</p>@endif @if($employee->profile->department->name ?? null)<p>{{ $employee->profile->department->name }}</p>@endif</div>
        <table><tr><td>Employee ID</td><td class="mono">{{ 'EMP-'.str_pad($employee->id,4,'0',STR_PAD_LEFT) }}</td></tr><tr><td>Accrual Period</td><td class="mono">{{ $projection['period_start']->format('d M Y') }} – {{ $projection['period_end']->format('d M Y') }}</td></tr><tr><td>Expected Payout</td><td class="mono">{{ $projection['next_anniversary_date']->format('d M Y') }}</td></tr><tr><td>Generated</td><td class="mono">{{ now()->format('d M Y') }}</td></tr></table>
    </div>
    <div class="summary">
        <div class="sum"><div class="label">Earned Days</div><div class="value">{{ number_format($projection['earned_days'],1) }}</div></div>
        <div class="sum"><div class="label">Used Days</div><div class="value">{{ number_format($projection['used_leave_days'],1) }}</div></div>
        <div class="sum"><div class="label">Encashable Days</div><div class="value">{{ number_format($projection['encashable_days'],1) }}</div></div>
        <div class="sum"><div class="label">Projected Payout</div><div class="value">৳{{ number_format($projection['projected_total_payout'],2) }}</div></div>
    </div>
    <table class="report">
        <thead><tr><th>Month</th><th>Earned Days</th><th>Used Days</th><th>Earned Value</th><th>Leave Deduction</th><th>Absent Deduction</th><th>Cumulative</th></tr></thead>
        <tbody>
        @php $earned=0;$earnedDays=0;$usedDays=0;$consumed=0;$refund=0;$running=0;$full=$projection['earned_days']>=24; @endphp
        @if($projection['opening_entry'])<tr><td colspan="4">Opening Entry ({{ number_format($projection['opening_entry']->days,1) }} days)</td><td>৳{{ number_format($projection['opening_entry']->amount,2) }}</td><td>—</td><td>৳{{ number_format($projection['opening_entry']->amount,2) }}</td></tr>@endif
        @forelse($projection['monthly_breakdown'] as $row)
            @php $earned+=(float)$row->earned_value;$earnedDays+=(float)$row->earned_days;$usedDays+=(float)$row->used_leave_days;$consumed+=(float)$row->consumed_leave_value;$refund+=(float)$row->leave_deduction;$running=$full?max(0,$projection['current_gross_salary']*(($earnedDays-$usedDays)/24))+$refund:max(0,$earned-$consumed)+$refund;$running+=(float)($projection['opening_entry']->amount??0); @endphp
            <tr><td>{{ \Carbon\Carbon::create((int)$row->year,(int)$row->month,1)->format('F Y') }}</td><td>{{ number_format($row->earned_days,1) }}</td><td>{{ number_format($row->used_leave_days,1) }}</td><td>৳{{ number_format($row->earned_value,2) }}</td><td>৳{{ number_format($row->leave_deduction,2) }}</td><td>৳{{ number_format($row->absent_deduction,2) }}</td><td>৳{{ number_format($running,2) }}</td></tr>
        @empty<tr><td colspan="7" style="text-align:center">No payroll months found.</td></tr>@endforelse
        </tbody>
        <tfoot><tr><td colspan="4">Projected Leave Encashment</td><td colspan="3">৳{{ number_format($projection['projected_total_payout'],2) }}</td></tr></tfoot>
    </table>
    <div class="note">A completed 12-month cycle converts 24 earned days into one month’s gross salary. Used leave reduces the encashable portion, while the exact leave deduction already taken from salary is refunded.</div>
    <div class="sig"><x-signature :company="$company ?? null" />Authorized Signatory<br><span style="font-weight:400">For, {{ optional($company)->name ?? config('app.name') }}</span></div>
</div>
<script>window.onload=function(){window.print();};</script>
</body>
</html>
