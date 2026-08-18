{{--
    ANY payroll table, as a printable report — one view behind the Loans desk,
    the Payslip desk and every card on the Reports page.

    Same format as the Party Statement (party-statement/print-statement): a
    browser print page rather than a server-rendered PDF, with the Back / Print /
    Download PDF row the print stylesheet hides. The browser has the report font
    family, renders ৳, honours flexbox and applies the @page margin — all four of
    which a DomPDF download got wrong. "Download PDF" is the browser's own
    print-to-PDF, exactly as the party statement and the salary paid/due report
    do it.

    Kept as ONE view on purpose: three desks printing the same document from three
    near-identical copies is three chances for them to drift apart.

    $sheet       — from a *BookService::sheet(): title, subtitle, headings, rows, totals
    $cards       — the summary strip: [['label','value','note','tone'], …]; the LAST
                   one takes the filled treatment, so pass the closing figure last
    $scopeLabel  — which company or companies this covers
    $filterLabel — the filters that were on when it was taken
    $company     — the letterhead
    $note        — the paragraph under "Note:", explaining how the figures relate
    $orientation — 'landscape' (default) or 'portrait'
--}}
@php
    $columns = count($sheet['headings']);
    $taka = fn ($v) => number_format((float) $v, 2);
    $orientation = $orientation ?? 'landscape';
    $cards = $cards ?? [];

    // A4 less the margins: landscape ~1123px, portrait ~794px.
    $cardWidth = $orientation === 'portrait' ? 794 : 1123;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $sheet['title'] }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    @page { size: A4 {{ $orientation }}; margin: {{ $orientation === 'portrait' ? '12mm' : '10mm' }}; }

    :root {
        --tc:  #3730a3;
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

    .card { max-width: {{ $cardWidth }}px; margin: auto; background: #fff; padding: 34px 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    .hdr { display: flex; justify-content: space-between; align-items: center; gap: 32px; margin-bottom: 24px; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }

    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 24px;
    }

    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 20px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .scope-badge { display: inline-block; padding: 1px 8px; border-radius: 9px; font-size: 10px; font-weight: 600; background: #e0e7ff; color: #3730a3; margin-right: 3px; margin-top: 4px; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl td.k { font-weight: 400; color: #64748b; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    .summary-strip { display: flex; gap: 0; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card:last-child { border-right: none; background: var(--thd); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card:last-child .sum-label { color: #c7d2fe; }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card:last-child .sum-value { color: #fff; font-size: 13px; }
    .sum-note { font-size: 10px; color: #94a3b8; font-weight: 400; font-family: var(--ff); margin-top: 2px; }
    .sum-card:last-child .sum-note { color: #c7d2fe; }

    .ledger { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 11px; }
    .ledger th {
        background: var(--thd); color: #fff;
        padding: 8px; text-align: left;
        font-family: var(--tf); font-size: 9px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 7px 8px; border-bottom: 1px solid var(--bdr); vertical-align: middle; }
    .ledger td.r    { text-align: right; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger tfoot tr { background: var(--thd); color: #fff; }
    .ledger tfoot td { padding: 8px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; }
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); }
    .ledger thead { display: table-header-group; }
    .ledger tr { page-break-inside: avoid; }

    .sh { background: var(--thd); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }
    .nb { font-size: 11px; line-height: 1.8; color: #64748b; margin-bottom: 14px; }

    .sig { margin-top: 36px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    @media print {
        body       { background: #fff !important; padding: 0 !important; }
        .btn-row   { display: none !important; }
        .card      { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        .label-bar { padding: 7px; margin-bottom: 14px; }
        .ledger    { font-size: 9.5px; }
        .ledger td { padding: 5px 6px; }
        .ledger th { padding: 6px 6px; }
        .sig       { margin-top: 24px; }
    }
</style>
</head>
<body>

{{-- Action buttons ── --}}
<div class="btn-row" style="max-width:{{ $cardWidth }}px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:history.back()" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#4f46e5;border:1px solid #4f46e5;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
</div>

<div class="card">

    {{-- Header ── --}}
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

    {{-- Label bar ── --}}
    <div class="label-bar">{{ $sheet['title'] }}</div>

    {{-- Scope / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Report Scope</h4>
            <p><strong>{{ $scopeLabel }}</strong></p>
            <p style="color:#64748b;">{{ $sheet['subtitle'] }}</p>
            <div style="margin-top:6px;">
                <span class="scope-badge">{{ $filterLabel }}</span>
            </div>
        </div>
        <div>
            <table class="meta-tbl">
                @foreach(($meta ?? []) as $metaKey => $metaValue)
                    <tr><td class="k">{{ $metaKey }}</td><td class="nf">{{ $metaValue }}</td></tr>
                @endforeach
                <tr><td class="k">Rows</td><td class="nf">{{ number_format(count($sheet['rows'])) }}</td></tr>
                <tr><td class="k">Generated</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
                <tr><td class="k">Printed By</td><td>{{ Auth::user()->name }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Summary strip ── --}}
    @if($cards)
        <div class="summary-strip">
            @foreach($cards as $card)
                <div class="sum-card">
                    <div class="sum-label">{{ $card['label'] }}</div>
                    <div class="sum-value" @if(!empty($card['tone'])) style="color:{{ $card['tone'] }};" @endif>{{ $card['value'] }}</div>
                    @if(!empty($card['note']))
                        <div class="sum-note">{{ $card['note'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Report table ── --}}
    <table class="ledger">
        <thead>
            <tr>
                @foreach($sheet['headings'] as $heading)
                    <th class="{{ !empty($heading['money']) || ($heading['align'] ?? null) === 'right' ? 'r' : '' }}">
                        {{ $heading['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($sheet['rows'] as $row)
                <tr>
                    @foreach($row as $i => $cell)
                        @php
                            $heading = $sheet['headings'][$i] ?? [];
                            $isMoney = !empty($heading['money']);
                            $isRight = $isMoney || ($heading['align'] ?? null) === 'right';
                        @endphp
                        <td class="{{ $isRight ? 'r' : '' }} {{ $isMoney ? 'mono' : '' }}">
                            {{ $isMoney ? $taka($cell) : $cell }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columns }}" style="text-align:center;padding:20px;color:#94a3b8;">
                        Nothing on this report for the chosen filters.
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if(!empty($sheet['totals']) && count($sheet['rows']))
            <tfoot>
                <tr>
                    @foreach($sheet['totals'] as $i => $total)
                        @php
                            $heading = $sheet['headings'][$i] ?? [];
                            $isMoney = !empty($heading['money']);
                            $isRight = $isMoney || ($heading['align'] ?? null) === 'right';
                        @endphp
                        <td class="{{ $isRight ? 'r' : '' }}">
                            @if($total === null)
                            @elseif($isMoney && is_numeric($total))
                                {{ $taka($total) }}
                            @else
                                {{ $total }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- Note ── --}}
    <div class="sh">Note:</div>
    <div class="nb">
        {{ $note ?? '' }}
        System-generated payroll report — Confidential.
    </div>

    {{-- Signature ── --}}
    <div class="sig">
        <div class="sig-c">
            <div class="sig-l">Authorized Signatory</div>
            <div style="font-size:10px;margin-top:3px;">For, {{ optional($company)->name ?? config('app.name') }}</div>
        </div>
    </div>

</div>
</body>
</html>
