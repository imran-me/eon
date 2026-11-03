{{--
    Financial statements — layout-free print page (A4, company letterhead), used by all four.
    Layout-free on purpose: print rules in the shared layout would reach every company's pages.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $frTitle }} — {{ $letterhead?->name ?? config('app.name') }}</title>
    @include('report.partials.fr-styles')
    <style>
        body { margin: 0; background: #e5e7eb; font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .pp-bar { max-width: 794px; margin: 14px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .pp-bar button, .pp-bar a { background: #0f766e; color: #fff; border: 0; border-radius: 8px; padding: 8px 16px; font-weight: 700; font-size: 13px; cursor: pointer; text-decoration: none; }
        .pp-page { width: 794px; max-width: 100%; box-sizing: border-box; margin: 12px auto 24px; background: #fff; padding: 36px 40px; box-shadow: 0 6px 24px rgba(0,0,0,.12); }
        .pp-letter { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f766e; padding-bottom: 14px; margin-bottom: 14px; }
        .pp-letter h2 { margin: 0; font-size: 20px; color: #0f766e; font-weight: 800; }
        .pp-letter .pp-addr { font-size: 11.5px; color: #475569; text-align: right; line-height: 1.5; }
        .pp-title { text-align: center; margin: 6px 0 14px; }
        .pp-title h1 { margin: 0; font-size: 18px; color: #0f172a; }
        .pp-title div { font-size: 12px; color: #64748b; margin-top: 3px; }
        .pp-page .fr-shell { background: #fff; padding: 0; border-radius: 0; }
        .pp-page .fr-st th { background: #f1f5f9; }
        .pp-foot { margin-top: 18px; font-size: 10.5px; color: #94a3b8; display: flex; justify-content: space-between; }
        @media print {
            body { background: #fff; }
            .pp-bar { display: none; }
            .pp-page { box-shadow: none; margin: 0; width: auto; padding: 0; }
            @page { size: A4; margin: 14mm; }
            .fr-st tr { page-break-inside: avoid; }
            .fr-st tr.fr-key td, .fr-st tr.fr-grand td, .fr-st tr.fr-sec td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="pp-bar"><a href="javascript:history.back()">Back</a><button onclick="window.print()">Print / Save as PDF</button></div>
    <div class="pp-page">
        <div class="pp-letter">
            <div>
                @if(optional($letterhead)->logo && file_exists(public_path($letterhead->logo)))<img src="{{ asset($letterhead->logo) }}" alt="" style="max-height:54px; margin-bottom:4px"><br>@endif
                <h2>{{ $letterhead?->name ?? config('app.name') }}</h2>
            </div>
            <div class="pp-addr">
                @if(optional($letterhead)->address){{ $letterhead->address }}<br>@endif
                {{ optional($letterhead)->phone }}{{ optional($letterhead)->phone && optional($letterhead)->email ? ' | ' : '' }}{{ optional($letterhead)->email }}
            </div>
        </div>

        <div class="pp-title">
            <h1>{{ $frTitle }}</h1>
            <div>
                {{ $frCompany?->name ?? 'All companies (whole group)' }} ·
                @if($frMode === 'month') {{ \Carbon\Carbon::createFromFormat('Y-m', $frMonth)->format('F Y') }}
                @elseif($frMode === 'period') {{ \Carbon\Carbon::parse($frFrom)->format('d M Y') }} to {{ \Carbon\Carbon::parse($frTo)->format('d M Y') }}
                @else As of {{ \Carbon\Carbon::parse($frAsOf)->format('d M Y') }} @endif
                · {{ $frSub }}
            </div>
        </div>

        <div class="fr-shell">
            @php $frPrinting = true; @endphp
            @if($kind === 'pl') @include('report.statements._pl')
            @elseif($kind === 'bs') @include('report.statements._bs')
            @elseif($kind === 'cf') @include('report.statements._cf')
            @elseif($kind === 'exp') @include('report.statements._exp')
            @elseif($kind === 'aging') @include('report.statements._aging')
            @elseif($kind === 'monthly') @include('report.statements._monthly')
            @endif
            @if($kind === 'bs' && ! $bs['balanced'])<div class="fr-warn">Assets and liabilities + equity differ by {{ number_format(abs($bs['difference']), 2) }} — see the ledger.</div>@endif
            @if($kind === 'cf' && ! $cf['reconciles'])<div class="fr-warn">Ending cash differs from the cash and bank accounts by {{ number_format(abs($cf['difference']), 2) }} — see the ledger.</div>@endif
        </div>

        <div class="pp-foot"><span>Amounts in BDT · generated {{ $frGeneratedAt->format('d M Y H:i') }} by {{ auth()->user()->name }}</span><span>{{ config('app.name') }}</span></div>
    </div>
</body>
</html>