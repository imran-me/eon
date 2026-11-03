{{--
    Project Accounts — this project's journals, inside Project Accounts: every posted double entry
    behind its cost entries and statement rows, each with its Dr / Cr lines. Read-only. A bank
    counter shows at a glance whether any of them touched a bank account. No <script>.
--}}
@extends('woodart::layouts.suite')

@php
    $PC   = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    $role = request()->route('role');
    $mny  = fn ($n) => number_format((float) $n, 2);
    $url  = $PC::route('project.journals', ['role' => $role, 'department' => $dept->id]);
    $sourceLabel = ['expense' => 'Cost entry', 'project_statement' => 'Statement'];
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost" href="{{ $PC::route('', ['role' => $role, 'section' => $PC::key() === 'construction' ? 'projects' : 'overview']) }}"><i class="bi bi-arrow-left"></i> All projects</a>
@endsection

@section('wa-view')

    @include('woodart::project-nav', ['navActive' => 'journals'])

    <div class="wap-kpi-grid" style="--kpi-cols:3">
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Journal entries</span><span class="wap-kpi-ico"><i class="bi bi-journals"></i></span></div>
            <div class="wap-kpi-value">{{ $totalCount }}</div>
            <div class="wap-kpi-foot">posted for this project</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Total debit = credit</span><span class="wap-kpi-ico"><i class="bi bi-arrow-left-right"></i></span></div>
            <div class="wap-kpi-value">৳ {{ number_format($totalDebit) }}</div>
            <div class="wap-kpi-foot">every entry balances</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Touching a bank account</span><span class="wap-kpi-ico"><i class="bi bi-bank"></i></span></div>
            <div class="wap-kpi-value {{ $touchesBank > 0 ? 'wap-t-bad' : '' }}">{{ $touchesBank }}</div>
            <div class="wap-kpi-foot">{{ $touchesBank === 0 ? 'no bank balance is affected' : 'these entries move a bank balance' }}</div>
        </div>
    </div>

    <form method="GET" class="wap-search" action="{{ $url }}">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $q }}" class="wap-input" placeholder="Reference, description or name…" aria-label="Search">
        <input type="date" name="date_from" value="{{ $from }}" class="wap-input" style="max-width:160px" aria-label="From date">
        <input type="date" name="date_to" value="{{ $to }}" class="wap-input" style="max-width:160px" aria-label="To date">
        <button type="submit" class="wap-btn wap-btn-ghost">Filter</button>
        @if($q !== '' || $from || $to)<a class="wap-btn wap-btn-ghost" href="{{ $url }}">Clear</a>@endif
    </form>

    <div class="wap-card">
        <div class="wap-card-head"><h3><i class="bi bi-journals"></i> Journals</h3><span class="wap-card-sub">{{ $entries->total() }} {{ $entries->total() === 1 ? 'entry' : 'entries' }} · newest first · pending cost entries have no journal yet</span></div>
        <div class="wap-card-body">
            @if($entries->isEmpty())
                <div class="wap-empty"><i class="bi bi-journals"></i><div class="wap-empty-title">No journals yet</div><div class="wap-empty-sub">A cost entry gets a journal when it is confirmed; client, supplier and funds rows when they are posted. Do it from the project summary.</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Date</th><th>Reference</th><th>Entry</th><th>Lines (Dr / Cr)</th><th class="wap-t-num">Amount</th></tr></thead>
                <tbody>
                @foreach($entries as $j)
                @php $dr = (float) $j->items->sum('debit'); $cr = (float) $j->items->sum('credit'); @endphp
                <tr>
                    <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($j->date)->format('d/m/Y') }}</td>
                    <td><span class="wap-t-strong">{{ $j->reference }}</span><div class="wap-hint" style="margin:0">{{ $sourceLabel[$j->source] ?? $j->source }}</div></td>
                    <td>{{ \Illuminate\Support\Str::limit((string) $j->description, 90) }}@if($j->reversed_journal_entry_id) <span class="wap-badge wap-badge-warn">Reversal</span>@endif</td>
                    <td>
                        @foreach($j->items as $it)
                        <div style="display:flex; gap:8px; justify-content:space-between; font-size:12px; white-space:nowrap">
                            <span>{{ $it->account->code ?? '' }} {{ \Illuminate\Support\Str::limit((string) ($it->account->name ?? ('#' . $it->account_id)), 34) }}</span>
                            <span style="color: {{ $it->debit > 0 ? '#dc2626' : '#16a34a' }}">{{ $it->debit > 0 ? 'Dr ' . $mny($it->debit) : 'Cr ' . $mny($it->credit) }}</span>
                        </div>
                        @endforeach
                    </td>
                    <td class="wap-t-num wap-t-strong">৳ {{ $mny($dr) }}@if(abs($dr - $cr) > 0.004)<div class="wap-hint" style="margin:0; color:#dc2626">does not balance</div>@endif</td>
                </tr>
                @endforeach
                </tbody>
            </table></div>
            <div style="display:flex; gap:8px; justify-content:space-between; margin-top:12px; align-items:center">
                <span class="wap-hint" style="margin:0">Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>
                <span style="display:flex; gap:8px">
                    @if($entries->previousPageUrl())<a class="wap-btn wap-btn-ghost" href="{{ $entries->previousPageUrl() }}"><i class="bi bi-chevron-left"></i> Newer</a>@endif
                    @if($entries->nextPageUrl())<a class="wap-btn wap-btn-ghost" href="{{ $entries->nextPageUrl() }}">Older <i class="bi bi-chevron-right"></i></a>@endif
                </span>
            </div>
            @endif
        </div>
    </div>

@endsection
