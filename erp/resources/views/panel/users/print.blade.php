<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@400;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page{size:A4 landscape;margin:10mm}:root{--tc:#3730a3;--tbg:#f5f7ff;--thd:#564fc8;--bdr:#e2e8f0;--txt:#1e293b;--tf:"Montserrat",sans-serif;--ff:"Inter",sans-serif;--nf:"Space Mono",monospace}*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}body{margin:0;padding:24px;background:#f0f2f5;color:var(--txt);font:11px var(--ff)}.sheet{max-width:1120px;margin:auto;padding:36px 40px;background:#fff;border-radius:4px;box-shadow:0 4px 20px #0000000f}.toolbar{max-width:1120px;margin:0 auto 12px;display:flex;justify-content:flex-end;gap:8px}.toolbar button,.toolbar a{border:0;border-radius:4px;padding:8px 15px;text-decoration:none;font:700 11px var(--tf);cursor:pointer}.toolbar a{background:#6b7280;color:#fff}.toolbar button{background:#4f46e5;color:#fff}.header{display:flex;justify-content:space-between;align-items:center}.group-logo{width:190px;height:auto;max-height:105px;object-fit:contain;object-position:left center;display:block}.brand-lockup{display:flex;align-items:center;gap:12px}.brand-lockup img{width:64px;height:64px;object-fit:contain}.logo-text{color:var(--tc);font:800 25px var(--tf);line-height:1}.logo-text small{display:block;margin-top:7px;color:#64748b;font:600 9px var(--tf);letter-spacing:1.2px}.company{text-align:right;font-size:11px;line-height:1.7}.company strong{display:block;font:700 16px var(--tf)}.title{text-align:center;margin:0 0 18px;padding:10px;background:var(--tbg);border-top:2px solid var(--tc);color:var(--tc);font:700 11px var(--tf);text-transform:uppercase;letter-spacing:2px}.meta{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:18px;color:#475569}.meta b{color:#172033}.chips{display:flex;flex-wrap:wrap;gap:5px}.chip{padding:4px 8px;border-radius:10px;background:#eef2ff;color:#3730a3;font-size:9px}.summary{font:700 10px var(--tf);text-align:right;white-space:nowrap}table{width:100%;border-collapse:collapse;table-layout:fixed}th{padding:8px 6px;background:var(--thd);color:#fff;text-align:left;text-transform:uppercase;font:700 8px var(--tf);letter-spacing:.4px}td{padding:7px 6px;border-bottom:1px solid var(--bdr);vertical-align:top;overflow-wrap:anywhere}tbody tr:nth-child(even){background:#f8f9ff}.center{text-align:center}.name{font-weight:700}.sub{display:block;margin-top:2px;color:#64748b;font-size:9px}.badge{display:inline-block;padding:3px 7px;border-radius:10px;font-size:8px;font-weight:800;text-transform:uppercase}.active{background:#dcfce7;color:#166534}.inactive{background:#e2e8f0;color:#475569}.resigned{background:#ffe4e6;color:#be123c}.empty{text-align:center;padding:35px;color:#64748b}.footer{display:flex;justify-content:space-between;margin-top:18px;padding-top:10px;border-top:1px solid var(--bdr);color:#64748b;font-size:9px}@media print{body{padding:0;background:#fff}.toolbar{display:none}.sheet{max-width:none;padding:0;box-shadow:none}.group-logo{max-height:90px}thead{display:table-header-group}tr{page-break-inside:avoid}.footer{position:relative}}
    </style>
</head>
<body>
    <div class="toolbar"><a href="javascript:history.back()">&larr; Back</a><button type="button" onclick="window.print()">Print / Save PDF</button></div>
    <main class="sheet">
        <header class="header">
            <div>
                @if(optional($groupCompany)->logo)
                    @php $groupLogo = file_exists(public_path($groupCompany->logo)) ? asset($groupCompany->logo) : asset('logo.png'); @endphp
                    <img class="group-logo" src="{{ $groupLogo }}" alt="EPAL Group">
                @else
                    <div class="brand-lockup"><img src="{{ asset('logo.png') }}" alt="EPAL Group"><div class="logo-text">{{ optional($groupCompany)->name ?? 'EPAL GROUP' }}<small>ENTERPRISE GROUP</small></div></div>
                @endif
            </div>
            <div class="company">
                <strong>{{ optional($groupCompany)->name ?? 'EPAL GROUP' }}</strong>
                @if(optional($groupCompany)->address){{ $groupCompany->address }}<br>@endif
                {{ optional($groupCompany)->phone }}{{ optional($groupCompany)->phone && optional($groupCompany)->email ? ' | ' : '' }}{{ optional($groupCompany)->email }}
            </div>
        </header>
        <div class="title">Employee List Report</div>
        <section class="meta">
            <div class="chips">
                <span class="chip"><b>Status:</b> {{ $status === 'all' ? 'All' : ucfirst($status) }}</span>
                @if(request('company') && $company)<span class="chip"><b>Company:</b> {{ $company->name }}</span>@endif
                @if(request('name'))<span class="chip"><b>Name:</b> {{ request('name') }}</span>@endif
                @if(request('phone'))<span class="chip"><b>Phone:</b> {{ request('phone') }}</span>@endif
                @if($department)<span class="chip"><b>Department:</b> {{ $department->name }}</span>@endif
            </div>
            <div class="summary">Total employees: {{ number_format($users->count()) }}</div>
        </section>
        <table>
            <colgroup><col style="width:4%"><col style="width:9%"><col style="width:17%"><col style="width:11%"><col style="width:18%"><col style="width:13%"><col style="width:12%"><col style="width:9%"><col style="width:7%"></colgroup>
            <thead><tr><th class="center">SL</th><th>Employee ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Department</th><th>Designation</th><th>Joining Date</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($users as $employee)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $employee->employee_id_no ?: '—' }}</td>
                        <td><span class="name">{{ $employee->name }}</span><span class="sub">{{ optional($employee->company)->name }}</span></td>
                        <td>{{ $employee->phone ?: '—' }}</td>
                        <td>{{ $employee->email ?: '—' }}</td>
                        <td>{{ optional($employee->profile?->department)->name ?? '—' }}</td>
                        <td>{{ optional($employee->profile?->designation)->name ?? '—' }}</td>
                        <td>{{ $employee->profile?->joining_date ? \Illuminate\Support\Carbon::parse($employee->profile->joining_date)->format('d M Y') : '—' }}</td>
                        <td><span class="badge {{ $employee->status }}">{{ ucfirst($employee->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">No employees match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
        <footer class="footer"><span>Printed by {{ auth()->user()->name }}</span><span>{{ now()->format('d M Y, h:i A') }}</span></footer>
    </main>
    <script>window.addEventListener('load', function(){ window.print(); });</script>
</body>
</html>
