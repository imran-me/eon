@extends('layout.app')

@section('meta-information')
    <title>{{ $bank->name }} — Statement vs Ledger</title>
@endsection

@section('main-content')
@include('layout.bank-tabs')

@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $c    = $comparison;
    $money = fn ($v) => $v === null ? '—' : number_format($v, 2);
    $day   = fn ($d) => \Carbon\Carbon::parse($d)->format('d M Y');
    $statusLabel = [
        'matched'          => 'Matched',
        'missing_in_erp'   => 'Missing in ERP',
        'not_on_statement' => 'Not on bank statement',
        'date_differs'     => 'Date differs',
        'amount_differs'   => 'Amount differs',
        'charge_missing'   => 'Bank charge missing',
        'cancelled'        => 'Reversed in ERP',
        'memo'             => 'Memo line',
    ];
    $statusClass = [
        'matched'          => 'rc-ok',
        'missing_in_erp'   => 'rc-bad',
        'not_on_statement' => 'rc-bad',
        'date_differs'     => 'rc-warn',
        'amount_differs'   => 'rc-bad',
        'charge_missing'   => 'rc-bad',
        'cancelled'        => 'rc-muted',
        'memo'             => 'rc-muted',
    ];
@endphp

<style>
    /* Scoped to this page (rc- = reconciliation compare). Kept inline so the
       page needs no CSS rebuild — see the note on public/build in CLAUDE.md. */
    .rc-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 18px; }
    @media (min-width: 1100px) { .rc-tiles { grid-template-columns: repeat(8, minmax(0, 1fr)); } }
    .rc-tile { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; background: #fff; min-width: 0; }
    .rc-tile .rc-tile-n { font-size: 20px; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .rc-tile .rc-tile-l { font-size: 11px; color: #6b7280; margin-top: 3px; text-transform: uppercase; letter-spacing: .03em; }
    .rc-tile.rc-ok .rc-tile-n { color: #15803d; }
    .rc-tile.rc-bad .rc-tile-n { color: #b91c1c; }
    .rc-tile.rc-warn .rc-tile-n { color: #b45309; }
    .rc-tile.rc-zero .rc-tile-n { color: #9ca3af; }

    .rc-banner { border-radius: 10px; padding: 12px 16px; font-size: 14px; font-weight: 600; margin-bottom: 18px; display: flex; gap: 10px; align-items: center; }
    .rc-banner.rc-ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .rc-banner.rc-bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

    .rc-issues { list-style: none; margin: 0 0 18px; padding: 0; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
    .rc-issues li { display: flex; gap: 12px; align-items: flex-start; padding: 8px 14px; font-size: 13px; border-top: 1px solid #f3f4f6; }
    .rc-issues li:first-child { border-top: 0; }
    .rc-issues .rc-issue-date { flex: 0 0 82px; font-weight: 700; color: #374151; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .rc-issues .rc-issue-kind { flex: 0 0 150px; }
    .rc-issues .rc-issue-text { flex: 1; color: #374151; }
    .rc-issues li.rc-error { background: #fff7f7; }
    .rc-issues li.rc-warn { background: #fffbeb; }

    .rc-pill { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .rc-pill.rc-ok { background: #dcfce7; color: #15803d; }
    .rc-pill.rc-bad { background: #fee2e2; color: #b91c1c; }
    .rc-pill.rc-warn { background: #fef3c7; color: #b45309; }
    .rc-pill.rc-muted { background: #f3f4f6; color: #6b7280; }

    .rc-wrap { overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 10px; }
    .rc-table { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; min-width: 860px; }
    .rc-table th, .rc-table td { padding: 5px 6px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    .rc-table thead th { position: sticky; top: 0; background: #f9fafb; color: #6b7280; font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; font-weight: 700; text-align: left; border-bottom: 1px solid #e5e7eb; z-index: 1; }
    .rc-table thead th.rc-group { text-align: center; font-size: 11.5px; color: #374151; background: #f3f4f6; }
    .rc-table thead th.rc-group.rc-bank { color: #1d4ed8; background: #eff6ff; }
    .rc-table thead th.rc-group.rc-erp { color: #6d28d9; background: #f5f3ff; }
    .rc-table .rc-num { text-align: right; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .rc-table .rc-mid { text-align: center; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; background: #fafafa; white-space: nowrap; overflow: hidden; }
    .rc-table .rc-nowrap { white-space: nowrap; }
    /* One line per row: long descriptions are cut with an ellipsis and carried in full in the cell's title. */
    .rc-table .rc-desc, .rc-table .rc-nowrap { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rc-table .rc-in { color: #15803d; }
    .rc-table .rc-out { color: #374151; }
    .rc-table .rc-note { color: #6b7280; font-size: 10.5px; font-weight: 400; display: block; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rc-table .rc-empty { color: #d1d5db; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rc-table tr.rc-day-head td { background: #f8fafc; font-weight: 700; color: #1f2937; border-top: 2px solid #e5e7eb; padding-top: 8px; }
    .rc-table tr.rc-day-head td .rc-day-totals { font-weight: 500; color: #6b7280; font-size: 11.5px; margin-left: 12px; }
    .rc-table tr.rc-day-foot td { background: #fcfcfd; font-weight: 700; border-bottom: 1px solid #e5e7eb; }
    .rc-table tr.rc-day-foot.rc-bad td { background: #fef2f2; }
    .rc-table tr.rc-day-foot.rc-bad td.rc-num { color: #b91c1c; }
    .rc-table tr.rc-row-missing_in_erp td, .rc-table tr.rc-row-not_on_statement td, .rc-table tr.rc-row-amount_differs td, .rc-table tr.rc-row-charge_missing td { background: #fff7f7; }
    .rc-table tr.rc-row-date_differs td { background: #fffbeb; }
    .rc-table tr.rc-row-cancelled td, .rc-table tr.rc-row-memo td { color: #9ca3af; }
    .rc-table tr.rc-row-cancelled td.rc-strike { text-decoration: line-through; }
    .rc-table .rc-amber { color: #b45309; font-weight: 700; }
    .rc-table .rc-delta { color: #9ca3af; font-size: 11px; }
    .rc-table .rc-carried { color: #9ca3af; font-weight: 400; font-size: 11px; }
    .rc-only-problems .rc-row-matched, .rc-only-problems .rc-day-clean { display: none; }

    .rc-details { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 14px; margin-top: 14px; font-size: 13px; }
    .rc-details summary { cursor: pointer; font-weight: 700; color: #374151; }
    .rc-details pre { white-space: pre-wrap; word-break: break-word; font-size: 11.5px; line-height: 1.45; background: #f9fafb; border-radius: 8px; padding: 10px; margin-top: 10px; max-height: 420px; overflow: auto; }
    .rc-details ul { margin: 8px 0 0; padding-left: 18px; color: #6b7280; }
</style>

<div class="bg-white rounded-lg shadow-md p-4 md:p-6">

    <a href="{{ route('role.banks.reconciliation', ['role' => $role, 'bank' => $bank->id]) }}"
       class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-700 mb-4">
        <i class="fas fa-arrow-left"></i> Back to Reconciliation
    </a>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 text-white px-6 py-6 mb-5">
        <div>
            <h2 class="text-xl font-bold"><i class="fas fa-columns mr-2"></i>{{ $bank->name }} — Bank Statement vs Ledger</h2>
            <div class="text-sm opacity-85 mt-1">
                @if($fileName)<span class="font-semibold">{{ $fileName }}</span>@if($pages) · {{ $pages }} page{{ $pages === 1 ? '' : 's' }}@endif · @endif
                @if($parsed['date_from'])
                    {{ $day($parsed['date_from']) }} → {{ $day($parsed['date_to']) }} ·
                    {{ count($parsed['rows']) }} bank row{{ count($parsed['rows']) === 1 ? '' : 's' }}
                    @if($c) · {{ $c['summary']['ledger_rows'] }} ERP row{{ $c['summary']['ledger_rows'] === 1 ? '' : 's' }} @endif
                    @if($parsed['order'] === 'desc') · printed newest-first, read oldest-first @endif
                @else
                    nothing readable was found in this file
                @endif
            </div>
        </div>
        <a href="{{ route('role.banks.reconciliation', ['role' => $role, 'bank' => $bank->id]) }}"
           class="px-4 py-2 rounded-md text-sm font-medium bg-white text-indigo-700" style="white-space:nowrap;">
            <i class="fas fa-file-pdf mr-1"></i>Upload another statement
        </a>
    </div>

    @if($accountMismatch)
        <div class="rc-banner rc-bad"><i class="fas fa-triangle-exclamation"></i>
            <span>This statement is for account <strong>{{ $parsed['account_no'] }}</strong>, but <strong>{{ $bank->name }}</strong> is account <strong>{{ $bank->account_number }}</strong>. Wrong bank — the comparison below is meaningless. Open the right bank's Reconcile page and upload it there.</span>
        </div>
    @endif

    @if($c && ! $bank->account_id)
        <div class="rc-banner rc-bad"><i class="fas fa-link-slash"></i>
            <span>This bank has no ledger account linked, so there is nothing on the ERP side to compare — every bank row below will read as missing.</span>
        </div>
    @endif

    @if($c && $parsed['warnings'])
        <div class="rc-banner rc-warn" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;"><i class="fas fa-circle-question"></i>
            <span>@foreach($parsed['warnings'] as $w){{ $w }} @endforeach</span>
        </div>
    @endif

    @if(! $c)
        <div class="rc-banner rc-bad"><i class="fas fa-triangle-exclamation"></i>
            <span>This PDF could not be read as a statement.</span>
        </div>
        <div class="text-sm text-gray-700 mb-4">
            @foreach($parsed['warnings'] as $w)
                <p class="mb-2">{{ $w }}</p>
            @endforeach
            <p class="text-gray-500 text-xs">
                {{ $parsed['line_count'] }} line{{ $parsed['line_count'] === 1 ? '' : 's' }} of text came out of the file.
                A statement row must carry a date and end with a balance (a figure with two decimals). If the text below looks
                like a proper statement and still was not read, send this page's extracted text to the developer — it is what the
                reader needs to learn this bank's layout.
            </p>
        </div>
    @else
        @if($c['clean'])
            <div class="rc-banner rc-ok"><i class="fas fa-circle-check"></i>
                <span>Every bank transaction has its ERP entry, every day closes on the same balance, and the period's opening and closing figures agree.</span>
            </div>
        @else
            @php $errors_ = count(array_filter($c['issues'], fn ($i) => $i['severity'] === 'error')); $warns_ = count($c['issues']) - $errors_; @endphp
            <div class="rc-banner rc-bad"><i class="fas fa-triangle-exclamation"></i>
                <span>
                    {{ $errors_ }} difference{{ $errors_ === 1 ? '' : 's' }} found between the bank and the ledger
                    @if($warns_) plus {{ $warns_ }} warning{{ $warns_ === 1 ? '' : 's' }} @endif
                    — closing balance at the end of the statement:
                    bank {{ $money($c['closing']['statement']) }}, ERP {{ $money($c['closing']['ledger']) }}
                    @if($c['closing']['matched']) (they agree — the differences cancel out or were corrected by the end).
                    @else (off by {{ number_format(abs($c['closing']['diff']), 2) }}). @endif
                </span>
            </div>
        @endif

        @php $s = $c['summary']; @endphp
        <div class="rc-tiles">
            <div class="rc-tile rc-ok"><div class="rc-tile-n">{{ $s['matched'] }}</div><div class="rc-tile-l">Matched</div></div>
            <div class="rc-tile {{ $s['missing_in_erp'] ? 'rc-bad' : 'rc-zero' }}"><div class="rc-tile-n">{{ $s['missing_in_erp'] }}</div><div class="rc-tile-l">Missing in ERP</div></div>
            <div class="rc-tile {{ $s['not_on_statement'] ? 'rc-bad' : 'rc-zero' }}"><div class="rc-tile-n">{{ $s['not_on_statement'] }}</div><div class="rc-tile-l">Not on statement</div></div>
            <div class="rc-tile {{ $s['charge_missing'] ? 'rc-bad' : 'rc-zero' }}"><div class="rc-tile-n">{{ $s['charge_missing'] }}</div><div class="rc-tile-l">Bank charges missing</div></div>
            <div class="rc-tile {{ $s['amount_differs'] ? 'rc-bad' : 'rc-zero' }}"><div class="rc-tile-n">{{ $s['amount_differs'] }}</div><div class="rc-tile-l">Amount differs</div></div>
            <div class="rc-tile {{ $s['date_differs'] ? 'rc-warn' : 'rc-zero' }}"><div class="rc-tile-n">{{ $s['date_differs'] }}</div><div class="rc-tile-l">Date differs</div></div>
            <div class="rc-tile {{ $s['day_mismatches'] ? 'rc-bad' : 'rc-zero' }}"><div class="rc-tile-n">{{ $s['day_mismatches'] }}<span style="font-size:12px;color:#9ca3af;font-weight:600;"> / {{ $s['days'] }}</span></div><div class="rc-tile-l">Days closing off{{ $s['timing_days'] ? ' · ' . $s['timing_days'] . ' timing only' : '' }}</div></div>
            <div class="rc-tile {{ ($c['opening']['matched'] && $c['closing']['matched']) ? 'rc-ok' : 'rc-bad' }}">
                <div class="rc-tile-n" style="font-size:13px;">{{ $c['opening']['diff'] === null ? '?' : ($c['opening']['matched'] ? 'Agree' : number_format($c['opening']['diff'], 2)) }} / {{ $c['closing']['matched'] ? 'Agree' : number_format($c['closing']['diff'], 2) }}</div>
                <div class="rc-tile-l">Opening / closing</div>
            </div>
        </div>

        @if($c['issues'])
            <h3 class="text-sm font-bold text-gray-700 mb-2"><i class="fas fa-list-check mr-1"></i>What to fix</h3>
            <ul class="rc-issues">
                @foreach($c['issues'] as $issue)
                    <li class="rc-{{ $issue['severity'] }}">
                        <span class="rc-issue-date">{{ \Carbon\Carbon::parse($issue['date'])->format('d M Y') }}</span>
                        <span class="rc-issue-kind">
                            @php
                                $kindLabel = match ($issue['kind']) {
                                    'opening_mismatch' => ['Opening balance', 'rc-bad'],
                                    'day_mismatch'     => ['Closing balance', 'rc-bad'],
                                    'unreadable'       => ['Unreadable row', 'rc-warn'],
                                    default            => [$statusLabel[$issue['kind']] ?? $issue['kind'], $statusClass[$issue['kind']] ?? 'rc-muted'],
                                };
                            @endphp
                            <span class="rc-pill {{ $kindLabel[1] }}">{{ $kindLabel[0] }}</span>
                        </span>
                        <span class="rc-issue-text">{{ $issue['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
            <h3 class="text-sm font-bold text-gray-700"><i class="fas fa-table-columns mr-1"></i>Side by side, day by day</h3>
            <label class="text-xs text-gray-500" style="cursor:pointer;user-select:none;">
                <input type="checkbox" id="rcOnlyProblems" style="vertical-align:-2px;margin-right:4px;"> Show only the problems
            </label>
        </div>

        <div class="rc-wrap" id="rcWrap">
            <table class="rc-table">
                <colgroup>
                    <col style="width:52px"><col><col style="width:94px"><col style="width:94px">
                    <col style="width:118px">
                    <col style="width:104px"><col><col style="width:94px"><col style="width:94px">
                </colgroup>
                <thead>
                    <tr>
                        <th colspan="4" class="rc-group rc-bank"><i class="fas fa-building-columns mr-1"></i>Bank statement</th>
                        <th class="rc-group"></th>
                        <th colspan="4" class="rc-group rc-erp"><i class="fas fa-book mr-1"></i>ERP ledger</th>
                    </tr>
                    <tr>
                        <th>Time</th><th>Particulars</th><th class="rc-num">Amount</th><th class="rc-num">Balance</th>
                        <th class="rc-mid">Result</th>
                        <th>Txn</th><th>Description</th><th class="rc-num">Amount</th><th class="rc-num">Balance</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($c['days'] as $d)
                    @php
                        $dayHasProblem = ! $d['matched'] || array_filter($d['rows'], fn ($r) => ! in_array($r['status'], ['matched', 'cancelled', 'memo'], true));
                        $dayClass = $dayHasProblem ? '' : 'rc-day-clean';
                    @endphp
                    <tr class="rc-day-head {{ $dayClass }}">
                        <td colspan="4">
                            {{ \Carbon\Carbon::parse($d['date'])->format('D, d M Y') }}
                            <span class="rc-day-totals">
                                @if($d['stmt_carried']) no bank transactions @else in {{ $money($d['stmt_in']) }} · out {{ $money($d['stmt_out']) }} @endif
                            </span>
                        </td>
                        <td class="rc-mid">
                            @if($d['diff_changed'])<span class="rc-pill rc-bad" title="The running difference between bank and ERP changed on this day">error here</span>@endif
                        </td>
                        <td colspan="4">
                            <span class="rc-day-totals" style="margin-left:0;">
                                @if($d['ledger_carried']) no ERP entries @else in {{ $money($d['ledger_in']) }} · out {{ $money($d['ledger_out']) }}@if($d['ledger_elsewhere']) · {{ $d['ledger_elsewhere'] }} shown under the bank's date @endif @endif
                            </span>
                        </td>
                    </tr>

                    @foreach($d['rows'] as $r)
                        @php
                            $st = $r['stmt']; $lg = $r['ledger']; $status = $r['status'];
                            // Short hint under the pill; the full sentence is the
                            // tooltip here and the line in "What to fix".
                            $hint = match ($status) {
                                'date_differs'   => 'ERP dated ' . \Carbon\Carbon::parse($lg['date'])->format('d M'),
                                'amount_differs' => 'bank ' . number_format($st['amount'], 2) . ' · ERP ' . number_format($lg['amount'], 2),
                                'charge_missing' => '+ ' . number_format($st['match']['fee'] ?? 0, 2) . ' charge',
                                'matched'        => ($lg && $lg['date'] !== $st['date']) ? 'bank booked ' . \Carbon\Carbon::parse($st['date'])->format('d M') : null,
                                'cancelled'      => 'and its reversal',
                                'memo'           => 'no amount',
                                default          => null,
                            };
                        @endphp
                        <tr class="rc-row-{{ $status }}">
                            @if($st)
                                <td class="rc-nowrap">{{ $st['time'] ?? '' }}</td>
                                <td class="rc-desc" title="{{ $st['particulars'] }}">{{ $st['particulars'] ?: '—' }}</td>
                                <td class="rc-num {{ $st['direction'] === 'in' ? 'rc-in' : 'rc-out' }} {{ $status === 'amount_differs' ? 'rc-amber' : '' }}">
                                    @if($st['amount'] !== null){{ $st['direction'] === 'in' ? '+' : '−' }}{{ number_format($st['amount'], 2) }}@endif
                                </td>
                                <td class="rc-num">{{ number_format($st['balance'], 2) }}</td>
                            @else
                                <td colspan="4" class="rc-empty">— nothing on the bank statement —</td>
                            @endif

                            <td class="rc-mid" title="{{ $r['note'] }}">
                                <span class="rc-pill {{ $statusClass[$status] ?? 'rc-muted' }}">{{ $statusLabel[$status] ?? $status }}</span>
                                @if($hint)<span class="rc-note">{{ $hint }}</span>@endif
                                @if($status === 'matched' && $r['delta'] !== null && abs($r['delta']) >= 0.005)
                                    <span class="rc-delta" title="Bank balance minus ERP balance after this row (inherited from earlier differences, or a different order within the day)">Δ {{ number_format($r['delta'], 2) }}</span>
                                @endif
                            </td>

                            @if($lg)
                                <td class="rc-nowrap">{{ $lg['txn_no'] }}</td>
                                <td class="rc-desc {{ $status === 'cancelled' ? 'rc-strike' : '' }}"
                                    title="{{ $lg['description'] }}{{ !empty($lg['beneficiary']) ? ' — ' . $lg['beneficiary'] : '' }}{{ $lg['time'] ? ' — ' . $lg['time'] : '' }}">
                                    @if($status === 'date_differs')<span class="rc-amber">{{ \Carbon\Carbon::parse($lg['date'])->format('d M') }}</span> · @endif{{ $lg['description'] ?: '—' }}
                                </td>
                                <td class="rc-num {{ $lg['direction'] === 'in' ? 'rc-in' : 'rc-out' }} {{ $status === 'amount_differs' ? 'rc-amber' : '' }}">
                                    {{ $lg['direction'] === 'in' ? '+' : '−' }}{{ number_format($lg['amount'], 2) }}
                                </td>
                                <td class="rc-num">{{ number_format($lg['balance'], 2) }}</td>
                            @else
                                <td colspan="4" class="rc-empty">— no ERP entry —</td>
                            @endif
                        </tr>
                    @endforeach

                    <tr class="rc-day-foot {{ $d['matched'] ? '' : 'rc-bad' }} {{ $dayClass }}">
                        <td colspan="3" style="text-align:right;">Closing balance{{ $d['stmt_carried'] ? ' (carried)' : '' }}</td>
                        <td class="rc-num">{{ $money($d['stmt_close']) }}</td>
                        <td class="rc-mid">
                            @if($d['diff'] === null)
                                <span class="rc-pill rc-muted">unknown</span>
                            @elseif($d['matched'] && abs($d['diff']) >= 0.005)
                                <span class="rc-pill rc-warn" title="The gap is exactly the money both sides agree on but book on different days (bank booking date vs ERP date). It closes by itself.">Timing: off by {{ number_format(abs($d['diff']), 2) }}</span>
                            @elseif($d['matched'])
                                <span class="rc-pill rc-ok">Balance matched</span>
                            @else
                                <span class="rc-pill rc-bad">Off by {{ number_format(abs($d['unexplained']), 2) }}</span>
                                @if(abs($d['timing']) >= 0.005)<span class="rc-note">+ {{ number_format(abs($d['timing']), 2) }} timing</span>@endif
                            @endif
                        </td>
                        <td colspan="3" style="text-align:right;">Closing balance{{ $d['ledger_carried'] ? ' (carried)' : '' }}</td>
                        <td class="rc-num">{{ $money($d['ledger_close']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">
            "In" and "Out" are from the account's point of view on both sides — a bank's Credit is money in, its Debit money out.
            "Error here" marks the first day the running difference between the two balances changed; later days only inherit it.
            A ledger entry that was reversed in the ERP is shown struck through and not counted, since the bank never saw either side of it.
        </p>
    @endif

    @if($parsed['skipped'])
        <details class="rc-details">
            <summary>{{ count($parsed['skipped']) }} line{{ count($parsed['skipped']) === 1 ? '' : 's' }} of the statement could not be read as a transaction</summary>
            <ul>
                @foreach($parsed['skipped'] as $sk)
                    <li>Line {{ $sk['line'] }} — {{ $sk['why'] }}: <code>{{ $sk['text'] }}</code></li>
                @endforeach
            </ul>
        </details>
    @endif

    <details class="rc-details">
        <summary>Extracted text ({{ $parsed['line_count'] }} lines)</summary>
        <pre>{{ $extracted }}</pre>
    </details>
</div>
@endsection

@section('js')
<script>
(function () {
    var box  = document.getElementById('rcOnlyProblems');
    var wrap = document.getElementById('rcWrap');
    if (!box || !wrap) return;
    box.addEventListener('change', function () {
        wrap.classList.toggle('rc-only-problems', box.checked);
    });
})();
</script>
@endsection
