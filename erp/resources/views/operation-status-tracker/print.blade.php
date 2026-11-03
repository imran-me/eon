<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Operations Status Tracker — Print</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 landscape; margin: 12mm; }

    :root {
        --tc:  #1d4ed8;
        --tbg: #eff6ff;
        --thd: #2563eb;
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

    .card { max-width: 1100px; margin: auto; background: #fff; padding: 32px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    .hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 24px; font-weight: 800; }

    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 16px;
    }

    .meta { font-size: 11px; color: #475569; margin-bottom: 16px; line-height: 1.6; }
    .meta strong { color: var(--txt); }

    .summary-strip { display: flex; gap: 0; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 18px; }
    .sum-card { flex: 1; padding: 9px 14px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-value { font-family: var(--nf); font-size: 13px; font-weight: 700; color: var(--txt); }

    table.rows { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10.5px; }
    table.rows th {
        background: var(--thd); color: #fff;
        padding: 7px 8px; text-align: left;
        font-family: var(--tf); font-size: 9px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    table.rows td { padding: 6px 8px; border-bottom: 1px solid var(--bdr); vertical-align: top; }
    table.rows tbody tr:nth-child(even) td { background: #f8fafc; }
    .sub { display: block; font-size: 9.5px; color: #64748b; margin-top: 1px; }
    .pill { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 9px; font-weight: 600; }
    .pill-red { background: #fee2e2; color: #991b1b; }
    .pill-amber { background: #fef3c7; color: #92400e; }
    .pill-gray { background: #f1f5f9; color: #475569; }

    .sig { margin-top: 28px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    @media print {
        body       { background: #fff !important; padding: 0 !important; }
        .btn-row   { display: none !important; }
        .card      { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        table.rows { font-size: 9.5px; }
        table.rows td { padding: 5px 7px; }
        table.rows th { padding: 5px 7px; }
    }
</style>
</head>
<body>

<div class="btn-row" style="max-width:1100px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
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

    <div class="label-bar">Operations Status Tracker</div>

    <div class="meta">
        <strong>{{ $view === 'attention' ? 'Needs attention' : 'All operations' }}</strong>
        · {{ number_format($items->count()) }} record{{ $items->count() === 1 ? '' : 's' }}
        @if($filters['flag'] !== 'any') · {{ $flagOptions[$filters['flag']] }} @endif
        @if($type !== 'all') · {{ ucfirst(str_replace('_', ' ', $type)) }} @endif
        @if($filters['status'] !== 'all') · {{ ucfirst(str_replace('_', ' ', $filters['status'])) }} @endif
        @if($filters['priority'] !== 'all') · {{ ucfirst($filters['priority']) }} priority @endif
        @if($filters['from'] || $filters['to'])
            · {{ $dateFieldOptions[$filters['date_field']] }} {{ $filters['from'] ?: '…' }} to {{ $filters['to'] ?: '…' }}
        @endif
        @if($search) · “{{ $search }}” @endif
        <br>Generated {{ now()->format('d M Y, h:i A') }} by {{ Auth::user()->name }}
    </div>

    <div class="summary-strip">
        <div class="sum-card"><div class="sum-label">Total Rows</div><div class="sum-value">{{ number_format($items->count()) }}</div></div>
        <div class="sum-card"><div class="sum-label">Overdue</div><div class="sum-value">{{ number_format($items->where('is_overdue', true)->count()) }}</div></div>
        <div class="sum-card"><div class="sum-label">Blocked</div><div class="sum-value">{{ number_format($items->where('is_blocked', true)->count()) }}</div></div>
        <div class="sum-card"><div class="sum-label">Unassigned</div><div class="sum-value">{{ number_format($items->whereNull('assignee_id')->count()) }}</div></div>
    </div>

    <table class="rows">
        <thead>
            <tr>
                <th width="9%">Type</th>
                <th width="19%">Reference / Client</th>
                <th width="16%">Current Stage</th>
                <th width="13%">Assignee</th>
                <th width="14%">Deadline / Age</th>
                <th width="14%">Next Action</th>
                <th width="15%">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td style="text-transform:capitalize;">{{ str_replace('_', ' ', $item['type']) }}</td>
                    <td>
                        <strong>{{ $item['reference'] }}</strong>
                        <span class="sub">{{ $item['client'] }}{{ $item['context'] ? ' · '.$item['context'] : '' }}</span>
                    </td>
                    <td>
                        {{ $item['stage'] }}
                        @if($item['is_overdue'])<span class="pill pill-red">Overdue</span>@elseif($item['is_blocked'])<span class="pill pill-amber">Blocked</span>@endif
                    </td>
                    <td>
                        @if($item['assignee_name'])
                            {{ $item['assignee_name'] }}
                        @else
                            <span class="pill pill-gray">Unassigned</span>
                        @endif
                    </td>
                    <td>{{ $item['due_at'] ? \Carbon\Carbon::parse($item['due_at'])->format('d M Y') : '—' }}</td>
                    <td>{{ $item['next_action'] ?: '—' }}</td>
                    <td>{{ $item['latest_note'] ? Str::limit($item['latest_note'], 60) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:20px;color:#94a3b8;">No records match the current filters.</td></tr>
            @endforelse
        </tbody>
    </table>

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
