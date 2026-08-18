<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payroll Report — {{ ucfirst($type) }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 landscape; margin: 10mm; }

    :root {
        --tc:  #3730a3; --tbg: #eef2ff; --thd: #4f46e5;
        --bdr: #e2e8f0; --txt: #1e293b;
        --tf: "Montserrat", sans-serif; --ff: "Inter", sans-serif; --nf: "Space Mono", monospace;
    }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
    }

    body { font-family: var(--ff); padding: 24px; background: #f0f2f5; color: var(--txt); font-size: 12px; }
    .card { max-width: 1080px; margin: auto; background: #fff; padding: 32px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .co-info { text-align: right; font-size: 11px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 15px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 24px; font-weight: 800; }

    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 9px;
        border-top: 2px solid var(--tc); margin-bottom: 18px;
    }

    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 16px; font-size: 11px; }
    .meta-row h4 { font-family: var(--tf); color: var(--tc); font-size: 10px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
    .meta-tbl td { padding: 2px 0 2px 16px; font-weight: 600; font-size: 11px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 10px; }

    .summary-strip { display: flex; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 18px; }
    .sum-card { flex: 1; padding: 9px 12px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; }
    .sum-label { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; }

    table.rep { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10px; }
    table.rep th {
        background: var(--thd); color: #fff; padding: 7px 8px; text-align: left;
        font-family: var(--tf); font-size: 8.5px; text-transform: uppercase; letter-spacing: .04em;
    }
    table.rep th.r, table.rep td.r { text-align: right; }
    table.rep th.c, table.rep td.c { text-align: center; }
    table.rep td { padding: 6px 8px; border-bottom: 1px solid var(--bdr); }
    table.rep tbody tr:nth-child(even) td { background: #f8f9ff; }
    table.rep td.mono { font-family: var(--nf); font-size: 9.5px; }
    table.rep tfoot tr { background: var(--thd); color: #fff; }
    table.rep tfoot td { padding: 7px 8px; font-weight: 700; border-bottom: none; font-family: var(--nf); font-size: 9.5px; }

    .good { color: #16a34a; font-weight: 700; }
    .bad  { color: #dc2626; font-weight: 700; }
    .warn { color: #ca8a04; font-weight: 700; }

    .sig { margin-top: 34px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    @media print {
        body     { background: #fff !important; padding: 0 !important; }
        .btn-row { display: none !important; }
        .card    { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        table.rep tfoot { display: table-footer-group; }
        table.rep tr    { page-break-inside: avoid; }
    }
</style>
</head>
<body>

<div class="btn-row" style="max-width:1080px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
</div>

@php
    $money = fn ($v) => '৳' . number_format((float) $v, 0);
    $mn    = fn ($m) => \Carbon\Carbon::createFromDate(2000, max(1, (int) $m), 1)->format('M');
    $date  = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M y') : '—';

    // Column set per report type: [heading, css class, value closure]
    $cols = match ($type) {
        'individual' => [
            ['Month',      '',  fn($r) => $mn($r->month) . ' ' . $r->year],
            ['Gross',      'r', fn($r) => $money($r->gross_salary)],
            ['Bonus',      'r', fn($r) => $r->bonus_amount > 0 ? $money($r->bonus_amount) : '—'],
            ['Deductions', 'r', fn($r) => $money($r->total_deductions)],
            ['Net',        'r', fn($r) => $money($r->net_salary)],
            ['Paid',       'r', fn($r) => '<span class="good">' . $money($r->paid_amount) . '</span>'],
            ['Due',        'r', fn($r) => $r->due_amount > 0 ? '<span class="bad">' . $money($r->due_amount) . '</span>' : '—'],
            ['Cumulative', 'r', fn($r) => $money($r->running_due)],
            ['Status',     'c', fn($r) => $r->status === 'Paid' ? 'Paid' : ((float) $r->paid_amount > 0 ? 'Partial' : 'Due')],
        ],
        'loan' => [
            ['Emp ID',     'mono', fn($r) => e($r->employee_id_no ?: '—')],
            ['Employee',   '',  fn($r) => e($r->employee_name)],
            ['Loan',       'r', fn($r) => $money($r->amount)],
            ['Monthly',    'r', fn($r) => $money($r->monthly_deduction)],
            ['Recovered',  'r', fn($r) => '<span class="good">' . $money($r->recovered_amount) . '</span>'],
            ['Remaining',  'r', fn($r) => $r->remaining_amount > 0 ? '<span class="bad">' . $money($r->remaining_amount) . '</span>' : '—'],
            ['Start',      '',  fn($r) => $date($r->start_date)],
            ['End',        '',  fn($r) => $date($r->end_date)],
            ['Status',     'c', fn($r) => e($r->status)],
        ],
        'advance' => [
            ['Emp ID',    'mono', fn($r) => e($r->employee_id_no ?: '—')],
            ['Employee',  '',  fn($r) => e($r->employee_name)],
            ['For Month', '',  fn($r) => e($r->month ?: '—')],
            ['Amount',    'r', fn($r) => $money($r->amount)],
            ['Schedule',  '',  fn($r) => $date($r->schedule_date)],
            ['Reason',    '',  fn($r) => e(Str::limit($r->reason, 40) ?: '—')],
            ['Status',    'c', fn($r) => e($r->status)],
        ],
        'payslip' => [
            ['Payslip #',  'mono', fn($r) => e($r->payslip_number)],
            ['Emp ID',     'mono', fn($r) => e($r->employee_id_no ?: '—')],
            ['Employee',   '',  fn($r) => e($r->employee_name)],
            ['Period',     '',  fn($r) => $mn($r->month) . ' ' . $r->year],
            ['Issued',     '',  fn($r) => $date($r->issue_date)],
            ['Gross',      'r', fn($r) => $money($r->gross_salary)],
            ['Deductions', 'r', fn($r) => $money($r->total_deductions)],
            ['Net',        'r', fn($r) => $money($r->net_salary)],
            ['Paid',       'r', fn($r) => '<span class="good">' . $money($r->paid_amount) . '</span>'],
            ['Due',        'r', fn($r) => $r->due_amount > 0 ? '<span class="bad">' . $money($r->due_amount) . '</span>' : '—'],
        ],
        default => [
            ['Emp ID',     'mono', fn($r) => e($r->employee_id_no ?: '—')],
            ['Employee',   '',  fn($r) => e($r->employee_name)],
            ['Months',     'c', fn($r) => $r->months_count],
            ['Gross',      'r', fn($r) => $money($r->total_gross)],
            ['Deductions', 'r', fn($r) => $money($r->total_deductions)],
            ['Net',        'r', fn($r) => $money($r->total_net)],
            ['Paid',       'r', fn($r) => '<span class="good">' . $money($r->total_paid) . '</span>'],
            ['Due',        'r', fn($r) => $r->total_due > 0 ? '<span class="bad">' . $money($r->total_due) . '</span>' : '—'],
        ],
    };

    $titles = [
        'overall'    => 'Salary Paid / Due — Overall',
        'individual' => 'Salary Paid / Due — Individual',
        'loan'       => 'Employee Loan Report',
        'advance'    => 'Advance Salary Report',
        'payslip'    => 'Payslip Report',
    ];
@endphp

<div class="card">

    <div class="hdr">
        <div>
            @if(optional($company)->logo && file_exists(public_path($company->logo)))
                <img src="{{ asset($company->logo) }}" alt="" style="max-width:74px;">
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

    <div class="label-bar">{{ $titles[$type] ?? 'Payroll Report' }}</div>

    <div class="meta-row">
        <div>
            <h4>Report Scope</h4>
            @if($selectedEmployee)
                <p><strong>Employee:</strong> {{ $selectedEmployee->name }}</p>
                <p><strong>Employee ID:</strong>
                    <span style="font-family:var(--nf);font-size:10px;">{{ $selectedEmployee->employee_id_no ?: '—' }}</span>
                </p>
            @else
                <p><strong>Employees:</strong> All</p>
            @endif
            <p><strong>Company:</strong> {{ optional($scopeCompany ?? null)->name ?? 'All Companies' }}</p>
            @if($status)<p><strong>Status:</strong> {{ $status }}</p>@endif
            @if($search)<p><strong>Search:</strong> "{{ $search }}"</p>@endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr>
                    <td>Period</td>
                    <td class="nf">
                        @if($from || $to)
                            {{ $from ? \Carbon\Carbon::createFromFormat('Y-m', $from)->format('M Y') : 'Beginning' }}
                            → {{ $to ? \Carbon\Carbon::createFromFormat('Y-m', $to)->format('M Y') : 'Now' }}
                        @else
                            All Time
                        @endif
                    </td>
                </tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y H:i') }}</td></tr>
                <tr><td>Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    @if(!empty($summary))
    <div class="summary-strip">
        @foreach($summary as $label => $cell)
        <div class="sum-card">
            <div class="sum-label">{{ $label }}</div>
            <div class="sum-value {{ in_array($cell['type'], ['good','bad','warn']) ? $cell['type'] : '' }}">
                {{ $cell['type'] === 'count' ? number_format($cell['value']) : '৳' . number_format($cell['value'], 0) }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <table class="rep">
        <thead>
            <tr>
                <th style="width:26px;">#</th>
                @foreach($cols as [$heading, $cls, $_])
                    <th class="{{ $cls === 'mono' ? '' : $cls }}">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                @foreach($cols as [$heading, $cls, $fn])
                    <td class="{{ $cls }}">{!! $fn($r) !!}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($cols) + 1 }}" style="text-align:center;padding:20px;color:#94a3b8;">
                    No records found for this filter.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
