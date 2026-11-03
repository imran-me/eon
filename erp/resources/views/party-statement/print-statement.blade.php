<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Party Statement — {{ $party->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
@php
    // Print designer choices (PartyStatementController::sheetSettings); every
    // default reproduces the sheet as it was.
    $sheet = ($sheet ?? []) + ['title' => '', 'stamp' => '', 'sigs' => ['authorized'], 'generator' => null, 'summary' => true,
        'words' => true, 'logo' => true, 'bank' => false, 'watermark' => '', 'landscape' => false, 'accent' => null];
    $catFiltered = $catFiltered ?? false;
    $companyBanks = collect();
    if ($sheet['bank'] && $company) {
        try {
            $companyBanks = \App\Models\Bank::where('company_id', $company->id)->whereNotNull('account_number')->where('account_number', '!=', '')->where('name', 'not like', '%demo%')->orderBy('name')->get();
        } catch (\Throwable) {}
    }
@endphp
<style>
    @page { size: A4 {{ $sheet['landscape'] ? 'landscape' : 'portrait' }}; margin: 12mm; }

    :root {
        --tc:  #3730a3;
        --tbg: #f5f7ff;
        --thd: #4f46e5;
        --thd2: #564fc8;
        --bdr: #e2e8f0;
        --txt: #1e293b;
        --tfg: #837de4;
        --tf:  "Montserrat", sans-serif;
        --ff:  "Inter", sans-serif;
        --nf:  "Space Mono", monospace;
    }
    @if($sheet['accent'])
    :root { --tc: {{ $sheet['accent'] }}; --thd: {{ $sheet['accent'] }}; --thd2: {{ $sheet['accent'] }}; --tfg: {{ $sheet['accent'] }}cc; }
    @endif
    @if($sheet['landscape']) .card { max-width: 1123px !important; } .btn-row { max-width: 1123px !important; } @endif
    .card { position: relative; }
    .wm { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; overflow: hidden; z-index: 0; }
    .wm span { transform: rotate(-30deg); font-family: var(--tf); font-weight: 800; font-size: 110px; letter-spacing: .08em; color: rgba(79,70,229,.06); white-space: nowrap; }
    .stamp { position: absolute; top: 330px; right: 45%; transform: rotate(-14deg); border: 3px double currentColor; border-radius: 10px; padding: 8px 18px; font-family: var(--tf); font-weight: 800; font-size: 22px; letter-spacing: .12em; opacity: .78; z-index: 2; }
    .stamp.paid, .stamp.received, .stamp.verified { color: #059669; }
    .stamp.due { color: #dc2626; }
    .stamp.other { color: #4f46e5; }
    .sigs { margin-top: 40px; display: flex; justify-content: space-between; gap: 24px; }
    .sigs.one { justify-content: flex-end; }
    .sigs .sig-c { flex: 0 1 180px; display: flex; flex-direction: column; justify-content: flex-end; min-height: 70px; }
    .gen { margin-top: 18px; font-size: 9px; color: #94a3b8; text-align: center; border-top: 1px dashed var(--bdr); padding-top: 6px; }
    .banks { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; margin-bottom: 14px; }
    .banks div { border: 1px solid var(--bdr); border-radius: 4px; padding: 6px 9px; font-size: 10px; line-height: 1.5; }
    .banks b { color: var(--tc); font-family: var(--tf); font-size: 10.5px; }

    *, *::before, *::after {
        box-sizing: border-box; margin: 0; padding: 0;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    body { font-family: var(--ff); padding: 28px; background: #f0f2f5; color: var(--txt); font-size: 12px; }

    /* ── Card ── */
    .card { max-width: 794px; margin: auto; background: #fff; padding: 40px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }

    /* ── Header ── */
    .hdr { display: flex; justify-content: space-between; align-items: center; }
    .co-info { text-align: right; font-size: 12px; line-height: 1.7; }
    .co-info strong { font-family: var(--tf); font-size: 16px; display: block; }
    .logo-txt { font-family: var(--tf); color: var(--tc); font-size: 26px; font-weight: 800; }

    /* ── Label bar ── */
    .label-bar {
        text-align: center; background: var(--tbg); color: var(--tc);
        text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
        font-family: var(--tf); font-size: 11px; padding: 10px;
        border-top: 2px solid var(--tc); margin-bottom: 24px;
    }

    /* ── Party info / Meta ── */
    .meta-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 20px; }
    .bill-to h4 { font-family: var(--tf); color: var(--tc); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
    .bill-to p { font-size: 13px; line-height: 1.7; margin: 1px 0; }
    .meta-tbl td { padding: 2px 0 2px 18px; font-weight: 600; font-size: 12px; }
    .meta-tbl .nf { font-family: var(--nf); font-size: 11px; }

    /* ── Summary strip ── */
    .summary-strip { display: flex; gap: 0; border: 1px solid var(--bdr); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .sum-card { flex: 1; padding: 10px 14px; border-right: 1px solid var(--bdr); }
    .sum-card-closing { border-right: none; position: relative; box-shadow: inset 0 0 0 9999px rgba(0,0,0,.02); }
    .sum-card-closing.tone-due      { background: linear-gradient(135deg, #d97706, #b45309); }
    .sum-card-closing.tone-advance  { background: linear-gradient(135deg, #2563eb, #1e40af); }
    .sum-card-closing.tone-settled  { background: linear-gradient(135deg, #475569, #1e293b); }
    .sum-label { font-size: 9px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; margin-bottom: 3px; }
    .sum-card-closing .sum-label { color: rgba(255,255,255,.75); }
    .sum-value { font-family: var(--nf); font-size: 12px; font-weight: 700; color: var(--txt); }
    .sum-card-closing .sum-value { color: #fff; font-size: 14px; }
    .tone-tag { display: inline-block; margin-top: 5px; padding: 1px 8px; border-radius: 9px; font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .sum-card-closing .tone-tag { background: rgba(255,255,255,.2); color: #fff; }
    .amt-tbl .grand.due .tone-tag     { background: #fef3c7; color: #b45309; }
    .amt-tbl .grand.advance .tone-tag { background: #dbeafe; color: #1e40af; }
    .amt-tbl .grand.settled .tone-tag { background: #f1f5f9; color: #475569; }

    /* ── Ledger table ── */
    .ledger { width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 24px; font-size: 11px; }
    .ledger th {
        background: var(--thd2); color: #fff;
        padding: 8px 10px; text-align: left;
        font-family: var(--tf); font-size: 9px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .ledger th.r { text-align: right; }
    .ledger td { padding: 6px 10px; border-bottom: 1px solid var(--bdr); vertical-align: middle; }
    .ledger td.r    { text-align: right; }
    .ledger td.date { white-space: nowrap; }
    .stage-badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5px; font-weight: 600; background: #e0e7ff; color: #3730a3; }
    .exception-badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5px; font-weight: 700; background: #fee2e2; color: #b91c1c; }
    .desc-flight { display: inline-block; font-size: 8px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
    .desc-when { display: inline-block; font-size: 8px; color: #94a3b8; margin-left: 4px; }
    .desc-leg { display: flex; align-items: baseline; gap: 5px; line-height: 1.35; min-width: 0; }
    .desc-leg-route { font-weight: 700; color: #334155; font-size: 8.5px; flex-shrink: 0; }
    .desc-leg-meta { font-size: 8.5px; color: #64748b; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
    .desc-tags { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
    .desc-category { display: inline-block; font-size: 8.5px; color: #b2910d; font-weight: 600; }
    .ledger td.mono { font-family: var(--nf); font-size: 10px; }
    .voucher-ben { font-family: var(--ff); font-size: 11px; line-height: 1.5; }
    .voucher-ben .vb-name { display: block; color: #3730a3; font-weight: 500; font-size: 11px; }
    .voucher-ben .vb-pass { display: block; font-family: var(--nf); font-size: 9.5px; color: #64748b; font-weight: 400; margin-top: 1px; }
    .ledger tbody tr:nth-child(even) td { background: #f8f9ff; }
    .ledger .opening-row td { font-style: italic; color: #64748b; }
    .ledger tr.sub-row td { background: #fff !important; border-bottom: 1px dashed var(--bdr); padding: 3px 10px 3px 22px; font-size: 10px; color: #64748b; }
    .ledger tfoot tr { color: #fff; box-shadow: 0 2px 10px rgba(49,46,129,.35); }
    .ledger tfoot td { padding: 12px 8px; font-weight: 700; border-bottom: none; font-family: var(--tf); font-size: 10px; text-transform: uppercase; letter-spacing: .04em; background: var(--tfg); overflow-wrap: anywhere; vertical-align: top; }
    /* overflow-wrap:anywhere on the row above still forces a break inside
       these cells even under white-space:nowrap (a long-standing Chrome
       quirk: overflow-wrap wins over nowrap once content would otherwise
       overflow) — that's why ৳700,000.00 kept splitting onto two lines
       despite nowrap being set right here. Resetting it back to normal on
       the numeric cells is what actually stops the wrap. */
    .ledger tfoot td.r { text-align: right; font-family: var(--nf); font-size: 9.5px; letter-spacing: normal; text-transform: none; white-space: nowrap; overflow-wrap: normal; }
    .ledger tfoot td.r.closing-cell { font-size: 10px; font-weight: 800; white-space: normal; overflow-wrap: anywhere; }

    /* ── Badges ── */
    .badge { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 9px; font-weight: 600; }
    .badge-payment { background: #dcfce7; color: #166534; }
    .badge-invoice  { background: #dbeafe; color: #1e40af; }
    .badge-default  { background: #f1f5f9; color: #475569; }

    .debit      { color: #dc2626; font-weight: 600; }
    .credit     { color: #16a34a; font-weight: 600; }
    .balance-dr { color: #dc2626; font-weight: 700; }
    .balance-cr { color: #16a34a; font-weight: 700; }

    /* ── Sub-header bar ── */
    .sh { background: var(--thd2); color: #fff; padding: 5px 12px; font-family: var(--tf); font-size: 10px; font-weight: 600; margin-bottom: 8px; letter-spacing: .03em; }

    /* ── Bottom grid ── */
    .bot { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 30px; }
    .nb { font-size: 12px; line-height: 1.8; color: #475569; margin-bottom: 14px; }
    .amt-tbl { width: 100%; border-collapse: collapse; }
    .amt-tbl td { padding: 6px 0; border-bottom: 1px solid #eee; font-size: 12px; }
    .amt-tbl td.n { font-family: var(--nf); font-size: 11px; text-align: right; }
    .amt-tbl .grand td { font-weight: 700; color: var(--tc); font-size: 13px; border-bottom: 2px solid var(--thd); }
    .amt-tbl .grand.due td     { color: #b45309; }
    .amt-tbl .grand.advance td { color: #1e40af; }
    .amt-tbl .dr-row td { color: #dc2626; font-weight: 600; }
    .amt-tbl .cr-row td { color: #16a34a; font-weight: 600; }

    /* ── Signature ── */
    .sig { margin-top: 36px; display: flex; justify-content: flex-end; }
    .sig-c { text-align: center; width: 200px; }
    .sig-l { border-top: 1px solid #333; margin-top: 6px; padding-top: 4px; font-size: 10px; font-weight: 700; font-family: var(--tf); }

    /* ── Print ── */
    @media print {
        body       { background: #fff !important; padding: 0 !important; }
        .btn-row   { display: none !important; }
        .card      { box-shadow: none !important; padding: 0 !important; max-width: 100% !important; }
        .label-bar { padding: 7px; margin-bottom: 14px; }
        .ledger    { font-size: 10px; }
        .ledger td { padding: 5px 8px; }
        .ledger th { padding: 6px 8px; }
        .sig       { margin-top: 24px; }
    }
</style>
</head>
<body>

{{-- Action buttons ── --}}
<div class="btn-row" style="max-width:794px;margin:0 auto 12px auto;display:flex;gap:8px;justify-content:flex-end;">
    <a href="javascript:void(0)" onclick="if(history.length>1){history.back();}else{window.close();setTimeout(function(){location.href=document.referrer||'/';},300);}return false;" style="background:#6b7280;color:#fff;text-decoration:none;display:inline-block;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;border-radius:4px;">← Back</a>
    <button onclick="window.print()" style="background:#4f46e5;color:#fff;border:none;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">🖨️ Print</button>
    <button onclick="window.print()" style="background:#fff;color:#4f46e5;border:1px solid #4f46e5;padding:7px 18px;font-family:'Montserrat',sans-serif;font-size:12px;cursor:pointer;border-radius:4px;">📄 Download PDF</button>
</div>

<div class="card">
    @if($sheet['watermark'])<div class="wm"><span>{{ $sheet['watermark'] }}</span></div>@endif
    @if($sheet['stamp'])
        @php $stampKey = ['PAID' => 'paid', 'RECEIVED' => 'received', 'VERIFIED' => 'verified', 'PAYMENT DUE' => 'due'][$sheet['stamp']] ?? 'other'; @endphp
        <div class="stamp {{ $stampKey }}">{{ $sheet['stamp'] }}<div style="font-size:8px;letter-spacing:.1em;text-align:center;">{{ now()->format('d M Y') }}</div></div>
    @endif

    {{-- Header ── --}}
    <div class="hdr">
        <div>
            @if(! $sheet['logo'])
            <div class="logo-txt">{{ optional($company)->name ?? config('app.name') }}</div>
            @elseif(optional($company)->logo)
            @php $logoSrc = file_exists(public_path($company->logo)) ? asset($company->logo) : asset('logo.png'); @endphp
            <img src="{{ $logoSrc }}" alt="" style="width:190px; height:auto; max-height:190px; object-fit:contain; margin-left:-16px; display:block;">
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

    @php
        $selection = $selection ?? 0;
        // Neutral wording — this statement is printed for the PARTY, so
        // "Receivable"/"Payable" (which side owes which) is our internal
        // accounting language, not theirs. "Due"/"Advance" states the fact
        // of the balance without taking a side.
        $closingLabel = $closingBalance > 0 ? 'Due' : ($closingBalance < 0 ? 'Advance' : 'Settled');
        $closingTone = $closingBalance > 0 ? 'due' : ($closingBalance < 0 ? 'advance' : 'settled');
    @endphp
    {{-- Label bar ── --}}
    <div class="label-bar">{{ $sheet['title'] !== '' ? $sheet['title'] : 'Party Statement of Account' }}{{ $selection ? ' — Selected Entries' : ($catFiltered ? ' — Selected Categories' : '') }}</div>

    {{-- Party info / Meta ── --}}
    <div class="meta-row">
        <div class="bill-to">
            <h4>Party Details</h4>
            <p><strong>{{ $party->name }}</strong></p>
            @if($party->phone)<p style="font-family:'Space Mono',monospace;font-size:11px;">{{ $party->phone }}</p>@endif
            @if($party->email)<p>{{ $party->email }}</p>@endif
            @if($party->address)<p>{{ $party->address }}</p>@endif
        </div>
        <div>
            <table class="meta-tbl">
                <tr><td>Party ID</td><td class="nf">{{ $partyCode }}</td></tr>
                <tr>
                    <td>Period</td>
                    <td class="nf">
                        @if($selection)
                            {{ $selection }} selected entr{{ $selection === 1 ? 'y' : 'ies' }}
                        @elseif($dateFrom)
                            {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} → {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
                        @else
                            All Time
                        @endif
                    </td>
                </tr>
                <tr><td>Generated</td><td class="nf">{{ now()->format('d M Y') }}</td></tr>
                @if($sheet['generator'] !== '')<tr><td>Printed By</td><td>{{ $sheet['generator'] ?? Auth::user()->name }}</td></tr>@endif
            </table>
        </div>
    </div>

    {{-- Summary strip ── --}}
    @if($sheet['summary'])
    <div class="summary-strip">
        <div class="sum-card">
            <div class="sum-label">{{ $selection ? 'Entries selected' : 'Opening Balance' }}</div>
            <div class="sum-value">{{ $selection ? $selection : '৳' . number_format($openingBalance, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Debit (Dr)</div>
            <div class="sum-value" style="color:#dc2626;">৳{{ number_format($totalDebit, 2) }}</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Total Credit (Cr)</div>
            <div class="sum-value" style="color:#16a34a;">৳{{ number_format($totalCredit, 2) }}</div>
        </div>
        <div class="sum-card sum-card-closing tone-{{ $closingTone }}">
            <div class="sum-label">{{ $selection ? 'Balance after last selected entry' : 'Closing Balance' }}</div>
            <div class="sum-value">
                ৳{{ number_format(abs($closingBalance), 2) }}
                {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
            </div>
            <div class="tone-tag">{{ $closingLabel }}</div>
        </div>
    </div>
    @endif

    {{-- Ledger table — one design per file in partials/statement-table
         (owner, 2026-10-13: redesign the table only, header / footer stay).
         v19 is the confirmed design (2026-10-14); ?design=current prints the
         table as it was before, ?design=v18 the single-Amount variant. --}}
    @php
        $tableDesign = request('design');
        if (! $tableDesign || ! preg_match('/^[a-z0-9-]+$/', $tableDesign) || ! view()->exists('party-statement.partials.statement-table.' . $tableDesign)) {
            $tableDesign = 'v19';
        }
    @endphp
    @include('party-statement.partials.statement-table.' . $tableDesign)

    {{-- Bottom grid ── --}}
    <div class="bot">
        <div>
            @if($sheet['words'])
            <div class="sh">Closing Balance In Words:</div>
            <div class="nb">
                @php
                    try { $words = ucfirst((new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) abs($closingBalance))) . ' Taka'; }
                    catch (\Throwable) { $words = '৳' . number_format(abs($closingBalance), 2); }
                @endphp
                {{ $words }}
                ({{ $closingLabel }})
            </div>
            @endif

            @if($companyBanks->isNotEmpty())
            <div class="sh">Pay To — Our Bank Accounts:</div>
            <div class="banks">
                @foreach($companyBanks as $b)
                <div><b>{{ $b->name }}</b><br>A/C {{ $b->account_name ? $b->account_name.' · ' : '' }}<span style="font-family:var(--nf);">{{ $b->account_number }}</span>@if($b->branch_name)<br>{{ $b->branch_name }}@endif @if($b->routing_number) · Routing {{ $b->routing_number }}@endif @if($b->swift_code)<br>SWIFT {{ $b->swift_code }}@endif</div>
                @endforeach
            </div>
            @endif

            <div class="sh">Note:</div>
            <div class="nb" style="font-size:12px; color:#1e293b; padding:8px 10px; background:#fffbeb; border:1px dashed #f59e0b; border-radius:4px;">
                {{ $note }}
            </div>
        </div>
        <div>
            <div class="sh">Summary:</div>
            <table class="amt-tbl">
                <tr><td>Opening Balance</td><td class="n">৳{{ number_format($openingBalance, 2) }}</td></tr>
                <tr class="dr-row"><td>Total Debit (Dr)</td><td class="n">৳{{ number_format($totalDebit, 2) }}</td></tr>
                <tr class="cr-row"><td>Total Credit (Cr)</td><td class="n">৳{{ number_format($totalCredit, 2) }}</td></tr>
                <tr class="grand {{ $closingTone }}">
                    <td>Closing Balance <span class="tone-tag">{{ $closingLabel }}</span></td>
                    <td class="n">
                        ৳{{ number_format(abs($closingBalance), 2) }}
                        {{ $closingBalance > 0 ? 'Dr' : ($closingBalance < 0 ? 'Cr' : '') }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Signatures ── --}}
    @php $sigLabels = \App\Http\Controllers\Dashboard\PartyStatementController::PRINT_SIGNATURES; @endphp
    @if($sheet['sigs'])
    <div class="sigs {{ count($sheet['sigs']) === 1 ? 'one' : '' }}">
        @foreach($sheet['sigs'] as $sg)
        <div class="sig-c">
            @if($sg === 'authorized')<x-signature :company="$company" />@endif
            <div class="sig-l">{{ $sigLabels[$sg] }}</div>
            <div style="font-size:10px;margin-top:3px;">{{ $sg === 'received' ? $party->name : 'For, '.(optional($company)->name ?? config('app.name')) }}</div>
        </div>
        @endforeach
    </div>
    @endif

    @if($sheet['generator'] !== '')
    <div class="gen">Generated by {{ $sheet['generator'] ?? Auth::user()->name }} on {{ now()->format('d M Y, h:i A') }} · {{ optional($company)->name ?? config('app.name') }}</div>
    @endif

</div>

<script>window.onload = function () { window.print(); };</script>
</body>
</html>
