{{--
    Project Accounts — one member of the site team on one project: what he spent (Credit — the
    company owes him), the funds provided to him (Debit — settles it), and the balance. Same
    Dr / Cr layout as the ERP's Party Statement, newest first. A funds line can be posted to the
    books, removed (while it is not posted) or reversed (once it is). No <script>.
--}}
@extends('woodart::layouts.suite')

@php
    $PC   = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    $role = request()->route('role');
    $mny  = fn ($n) => number_format(abs((float) $n), 2);
    $bal  = (float) $ledger['balance'];
    $label = abs($bal) < 0.005 ? 'Settled' : ($bal > 0 ? 'Company owes him' : 'He holds company money');
    $tone = fn ($n) => $n > 0.004 ? '#dc2626' : '#1d4ed8';
    $post = fn ($suffix, $extra = []) => $PC::route($suffix, ['role' => $role, 'department' => $dept->id] + $extra);
    $backTo = 'person-' . $person->id;
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost" href="{{ $PC::route('project.summary', ['role' => $role, 'department' => $dept->id]) }}#wa-team"><i class="bi bi-arrow-left"></i> Summary</a>
    <a class="wap-btn wap-btn-ghost" target="_blank" href="{{ $PC::route('project.person.print', ['role' => $role, 'department' => $dept->id, 'user' => $person->id]) }}"><i class="bi bi-printer"></i> Print</a>
@endsection

@section('wa-view')

    @if(session('success'))<div class="wap-banner" style="margin-bottom:14px"><i class="bi bi-check2-circle" style="color:#16955c"></i><div>{{ session('success') }}</div></div>@endif
    @if(session('error'))<div class="wap-banner" style="background: rgba(194,41,69,.08); border-color: rgba(194,41,69,.35); margin-bottom:14px"><i class="bi bi-exclamation-octagon" style="color:#c22945"></i><div>{{ session('error') }}</div></div>@endif

    @include('woodart::project-nav', ['navActive' => 'person'])

    <div class="wap-card">
        <div class="wap-card-body" style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center">
            <div>
                <div style="font-size:18px; font-weight:700">{{ $person->name }} <span class="wap-badge">Site team</span></div>
                <div class="wap-hint">{{ $person->phone ?: 'No phone' }} · {{ $dept->name }}</div>
            </div>
            <div style="text-align:right">
                <div class="wap-hint">{{ $label }}</div>
                <div style="font-size:22px; font-weight:700; color: {{ $tone($bal) }}">৳ {{ $mny($bal) }}</div>
            </div>
        </div>
    </div>

    <div class="wap-kpi-grid" style="--kpi-cols:3">
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Total spent</span><span class="wap-kpi-ico"><i class="bi bi-receipt"></i></span></div>
            <div class="wap-kpi-value" style="color:#16a34a">৳ {{ $mny($ledger['spent']) }}</div>
            <div class="wap-kpi-foot">Credit · cost entries in his name</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Funds provided</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
            <div class="wap-kpi-value" style="color:#dc2626">৳ {{ $mny($ledger['provided']) }}</div>
            <div class="wap-kpi-foot">Debit · sent to him, or paid for him</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">{{ $label }}</span><span class="wap-kpi-ico"><i class="bi bi-flag"></i></span></div>
            <div class="wap-kpi-value" style="color: {{ $tone($bal) }}">৳ {{ $mny($bal) }}</div>
            <div class="wap-kpi-foot">spent − provided</div>
        </div>
    </div>

    <div class="wap-card">
        <div class="wap-card-head"><h3><i class="bi bi-journal-text"></i> Account — {{ $person->name }}</h3><span class="wap-card-sub">{{ $ledger['rows']->count() }} entries · newest first · running balance</span></div>
        <div class="wap-card-body">
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Date</th><th>Entry</th><th class="wap-t-num">Debit (provided)</th><th class="wap-t-num">Credit (spent)</th><th class="wap-t-num">Balance</th><th>Books</th>@if($canEdit)<th></th>@endif</tr></thead>
                <tbody>
                @foreach($ledger['rows']->reverse()->values() as $r)
                <tr @if($r->status === 'reversed') style="opacity:.55" @endif>
                    <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
                    <td><div class="wap-t-strong">{{ $r->particulars }}</div>
                        @if($r->sub)<div class="wap-hint" style="margin:0">{{ $r->sub }}</div>@endif
                        @if($r->ref)<div class="wap-hint" style="margin:0">{{ $r->ref }}</div>@endif</td>
                    <td class="wap-t-num">@if($r->dr > 0)<span style="color:#dc2626; font-weight:600">{{ $mny($r->dr) }}</span>@else<span style="color:#cbd5e1">—</span>@endif</td>
                    <td class="wap-t-num">@if($r->cr > 0)<span style="color:#16a34a; font-weight:600">{{ $mny($r->cr) }}</span>@else<span style="color:#cbd5e1">—</span>@endif</td>
                    <td class="wap-t-num wap-t-strong" style="color: {{ $tone($r->balance) }}">{{ $mny($r->balance) }} {{ abs($r->balance) < 0.005 ? '' : ($r->balance > 0 ? 'Cr' : 'Dr') }}</td>
                    <td>@if($r->status === 'in books')<span class="wap-badge">In books</span>@elseif($r->status === 'reversed')<span class="wap-badge wap-badge-warn">Reversed</span>@else<span class="wap-badge wap-badge-warn">{{ ucfirst($r->status) }}</span>@endif</td>
                    @if($canEdit)<td>
                        @if($r->kind === 'fund' && $r->line)
                            @if(! $r->line->journal_entry_id && ! $r->line->reversal_journal_entry_id)
                            <form method="POST" action="{{ $post('project.statement.post', ['line' => $r->line->id]) }}" style="display:inline">@csrf <input type="hidden" name="back" value="{{ $backTo }}">
                                <button type="submit" class="wap-btn wap-btn-primary" title="Dr his account (Employee payable) / Cr Inter-company — no bank"><i class="bi bi-journal-check"></i> Post</button></form>
                            <form method="POST" action="{{ $post('project.statement.void', ['line' => $r->line->id]) }}" style="display:inline">@csrf @method('DELETE') <input type="hidden" name="back" value="{{ $backTo }}">
                                <button type="submit" class="wap-proj-act wap-proj-act-bad" title="Remove this funds entry" aria-label="Remove"><i class="bi bi-x-lg"></i></button></form>
                            @elseif($r->line->journal_entry_id && ! $r->line->reversal_journal_entry_id)
                            <form method="POST" action="{{ $post('project.statement.reverse', ['line' => $r->line->id]) }}" style="display:inline">@csrf <input type="hidden" name="back" value="{{ $backTo }}">
                                <button type="submit" class="wap-btn wap-btn-ghost" title="Write an opposite journal; the original stays">Reverse</button></form>
                            @endif
                        @endif
                    </td>@endif
                </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="2" class="wap-t-strong" style="text-align:right">Total · {{ $label }}</td>
                    <td class="wap-t-num wap-t-strong" style="color:#dc2626">{{ $mny($ledger['provided']) }}</td>
                    <td class="wap-t-num wap-t-strong" style="color:#16a34a">{{ $mny($ledger['spent']) }}</td>
                    <td class="wap-t-num wap-t-strong" style="color: {{ $tone($bal) }}">{{ $mny($bal) }} {{ abs($bal) < 0.005 ? '' : ($bal > 0 ? 'Cr' : 'Dr') }}</td><td colspan="{{ $canEdit ? 2 : 1 }}"></td>
                </tr></tfoot>
            </table></div>
            <div class="wap-hint" style="margin-top:8px">Credit balance (Cr) = the company owes him. Debit (Dr) = he holds company money. Funds not yet posted are listed but are not in the books.</div>
        </div>
    </div>

    @if($canEdit)
    <div class="wap-card">
        <div class="wap-card-head"><h3><i class="bi bi-cash-coin"></i> Funds provided to {{ $person->name }}</h3><span class="wap-card-sub">Sent to him, or paid straight to a supplier for him — by another Epal company</span></div>
        <div class="wap-card-body">
            <form method="POST" action="{{ $post('project.fund') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end">
                @csrf <input type="hidden" name="person_id" value="{{ $person->id }}"> <input type="hidden" name="person" value="{{ $person->id }}">
                <div class="wap-field"><label class="wap-label" for="wa-pf-date">Date</label><input id="wa-pf-date" name="entry_date" type="date" required class="wap-input"></div>
                <div class="wap-field"><label class="wap-label" for="wa-pf-amt">Amount (৳)</label><input id="wa-pf-amt" name="amount" type="number" step="1" min="1" required class="wap-input"></div>
                <div class="wap-field" style="min-width:230px"><label class="wap-label" for="wa-pf-from">Provided by</label><input id="wa-pf-from" name="label" maxlength="160" class="wap-input" placeholder="e.g. Epal Travels IBBL"></div>
                <div class="wap-field" style="min-width:230px"><label class="wap-label" for="wa-pf-note">Note</label><input id="wa-pf-note" name="note" maxlength="500" class="wap-input"></div>
                <button type="submit" class="wap-btn wap-btn-primary"><i class="bi bi-plus-lg"></i> Add</button>
            </form>
        </div>
    </div>
    @endif

@endsection
