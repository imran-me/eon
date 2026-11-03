{{--
    Wood Art · Accounts — one project's cost sheet. READ-ONLY, straight from the
    ERP Expense desk (company 6). Only APPROVED expenses are cost; filed-but-
    pending ones are listed (marked) and counted separately, never inside the
    total. No <script> in here.
--}}
@extends('woodart::layouts.suite')

@php
    $role = request()->route('role');
    $mny  = fn ($n) => '৳ ' . number_format((float) $n, 0);
    $base = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route('project', ['role' => $role, 'department' => $dept->id]);
    $isConstruction = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::key() === 'construction';
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost" href="{{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route('', ['role' => $role, 'section' => 'projects']) }}">
        <i class="bi bi-arrow-left"></i> All projects</a>
    @if(\Modules\WoodArt\Modules\Accounts\Support\ProjectContext::key() !== 'construction' && \Illuminate\Support\Facades\Route::has(\Modules\WoodArt\Modules\Accounts\Support\ProjectContext::routeName('bulk')))
    <a class="wap-btn wap-btn-primary" href="{{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route('bulk', ['role' => $role, 'project' => $dept->id]) }}">
        <i class="bi bi-plus-lg"></i> New Entry</a>
    @endif
@endsection

@section('wa-view')

    @if(session('success'))
    <div class="wap-banner" style="margin-bottom:16px"><i class="bi bi-check2-circle" style="color:#16955c"></i><div>{{ session('success') }}</div></div>
    @endif

    @include('woodart::project-nav', ['navActive' => $workspace])

    <style>
        .wa-entry-modal{width:min(760px,calc(100vw - 28px));border:0;border-radius:18px;padding:0;box-shadow:0 24px 70px rgba(15,23,42,.28);color:#0f172a}
        .wa-entry-modal::backdrop{background:rgba(15,23,42,.55);backdrop-filter:blur(2px)}
        .wa-collection-modal{margin:auto;width:min(680px,calc(100vw - 32px));max-height:calc(100vh - 40px);overflow:auto;border-radius:14px}.wa-collection-modal h2{margin:0;font-size:19px}.wa-collection-modal p{margin:5px 0 0;font-size:12px;color:#ccfbf1}.wa-collection-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:end}.wa-collection-form>div{min-width:0}.wa-collection-form .wap-input{width:100%;max-width:none!important;box-sizing:border-box;min-height:40px;border-radius:8px}.wa-collection-form .wap-label{display:block;margin-bottom:6px}.wa-collection-wide{grid-column:1/-1}.wa-collection-divider{padding-top:12px;border-top:1px solid #e2e8f0;color:#64748b;font-size:11px}.wa-collection-footer{display:flex;justify-content:flex-end;padding-top:14px;border-top:1px solid #e2e8f0}@media(max-width:580px){.wa-collection-form{grid-template-columns:1fr}}
        .wa-entry-modal-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;padding:20px 22px;background:linear-gradient(135deg,#147d74,#22998d);color:#fff}
        .wa-entry-modal-body{padding:22px}.wa-entry-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.wa-entry-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.wa-entry-modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid #e2e8f0;background:#f8fafc}
        .wa-entry-action{display:flex;gap:12px;align-items:flex-start;padding:16px;border:1px solid #dbe3ea;border-radius:13px;text-decoration:none;color:#0f172a;background:#fff;text-align:left}
        .wa-entry-action:hover{border-color:#2b9187;background:#f0fdfa}.wa-entry-action i{font-size:20px;color:#167c73}
        @media(max-width:720px){.wa-entry-actions,.wa-entry-grid{grid-template-columns:1fr}}
    </style>

    @if(in_array($workspace, ['expenses', 'reports'], true) && ! ($isConstruction && $workspace === 'expenses'))
    <div class="wap-kpi-grid" style="grid-template-columns:repeat({{ $workspace === 'expenses' ? 2 : 4 }},minmax(0,1fr))">
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Total Cost</span><span class="wap-kpi-ico"><i class="bi bi-briefcase"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($sheet['total']) }}</div>
            <div class="wap-kpi-foot">Approved expenses across the filtered period</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Awaiting Approval</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($sheet['pending']) }}</div>
            <div class="wap-kpi-foot">not in the total yet</div>
        </div>
        @if($workspace === 'reports')
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Biggest Head</span><span class="wap-kpi-ico"><i class="bi bi-fire"></i></span></div>
            <div class="wap-kpi-value" style="font-size:18px">{{ $sheet['byHead']->first()['name'] ?? '—' }}</div>
            @if($sheet['byHead']->first())<div class="wap-kpi-foot">{{ $mny($sheet['byHead']->first()['total']) }}</div>@endif
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Owed to staff</span><span class="wap-kpi-ico"><i class="bi bi-person-wallet2"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($sheet['owed']->sum('owed')) }}</div>
            <div class="wap-kpi-foot">across all {{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::name() }}</div>
        </div>
        @endif
    </div>

    @endif
    {{-- ── CLIENT DUE SHEET + SUPPLIER DUES: statement rows, each either IN THE BOOKS
         (a real journal) or not yet. Posting is explicit and per row; reversing
         writes an opposite journal — nothing posted is ever edited or deleted. ── --}}
    @php
        $st   = $statement;
        $post = fn ($suffix, $extra = []) => \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route($suffix, ['role' => $role, 'department' => $dept->id] + $extra);
        $kindLabel = ['client_installment' => 'Installment', 'supplier_bill' => 'Due', 'supplier_payment' => 'Paid', 'client_bill' => 'Billed', 'supplier_balance' => 'Balance carried'];
        $inBooks  = fn ($l) => $l->journal_entry_id && ! $l->reversal_journal_entry_id;
        $reversed = fn ($l) => (bool) $l->reversal_journal_entry_id;
        $canBank  = $canEdit && auth()->user()?->can('pay expense from bank');
    @endphp

    @if(session('error'))
    <div class="wap-banner" style="background: rgba(194,41,69,.08); border-color: rgba(194,41,69,.35); margin-bottom:16px">
        <i class="bi bi-exclamation-octagon" style="color:#c22945"></i><div>{{ session('error') }}</div>
    </div>
    @endif

    @if(in_array($workspace, ['collections', 'payables'], true))
    @if(! $st['ready'])
    <div class="wap-banner" style="margin-bottom:16px">
        <i class="bi bi-info-circle"></i>
        <div>The client installment and supplier due sheets are not installed on this server yet.</div>
    </div>
    @else
    @if($isConstruction)
    <div class="wa-cpm wa-cpm-kpis">
        @if($workspace === 'collections')
        <div class="wa-cpm-kpi is-blue"><span class="wa-cpm-icon"><i class="bi bi-file-earmark-text"></i></span><span><span class="wa-cpm-label">Contract Value</span><strong class="wa-cpm-value">{{ $st['contract'] !== null ? $mny($st['contract']) : 'Not set' }}</strong><span class="wa-cpm-note">Recorded project contract</span></span></div>
        <div class="wa-cpm-kpi"><span class="wa-cpm-icon"><i class="bi bi-cash-coin"></i></span><span><span class="wa-cpm-label">Total Received</span><strong class="wa-cpm-value">{{ $mny($st['received']) }}</strong><span class="wa-cpm-note">Recorded client collections</span></span></div>
        <div class="wa-cpm-kpi is-red"><span class="wa-cpm-icon"><i class="bi bi-file-earmark-minus"></i></span><span><span class="wa-cpm-label">Outstanding</span><strong class="wa-cpm-value">{{ $st['clientDue'] !== null ? $mny($st['clientDue']) : 'Not set' }}</strong><span class="wa-cpm-note">Contract less collections</span></span></div>
        @else
        <div class="wa-cpm-kpi is-blue"><span class="wa-cpm-icon"><i class="bi bi-file-earmark-text"></i></span><span><span class="wa-cpm-label">Total Billed</span><strong class="wa-cpm-value">{{ $mny($st['suppliers']->sum('bill')) }}</strong><span class="wa-cpm-note">Supplier dues recorded</span></span></div>
        <div class="wa-cpm-kpi"><span class="wa-cpm-icon"><i class="bi bi-cash-coin"></i></span><span><span class="wa-cpm-label">Total Paid</span><strong class="wa-cpm-value">{{ $mny($st['suppliers']->sum('paid')) }}</strong><span class="wa-cpm-note">Supplier payments recorded</span></span></div>
        <div class="wa-cpm-kpi is-red"><span class="wa-cpm-icon"><i class="bi bi-file-earmark-minus"></i></span><span><span class="wa-cpm-label">Outstanding</span><strong class="wa-cpm-value">{{ $mny($st['supplierDue']) }}</strong><span class="wa-cpm-note">Still owed to suppliers</span></span></div>
        @endif
    </div>
    @else
    <div class="wap-kpi-grid">
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Contract value</span><span class="wap-kpi-ico"><i class="bi bi-file-earmark-text"></i></span></div>
            <div class="wap-kpi-value">{{ $st['contract'] !== null ? $mny($st['contract']) : '—' }}</div>
            <div class="wap-kpi-foot">billed in the books: {{ $mny($st['books']['billed']) }}</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Received from client</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($st['received']) }}</div>
            <div class="wap-kpi-foot">in the books: {{ $mny($st['books']['received']) }}</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Client still owes</span><span class="wap-kpi-ico"><i class="bi bi-person-check"></i></span></div>
            <div class="wap-kpi-value {{ ($st['clientDue'] ?? 0) > 0 ? 'wap-t-bad' : '' }}">{{ $st['clientDue'] !== null ? $mny($st['clientDue']) : '—' }}</div>
            <div class="wap-kpi-foot">Receivable (1315) in the books: {{ $mny($st['books']['due']) }}</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Suppliers are owed</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
            <div class="wap-kpi-value {{ $st['supplierDue'] > 0 ? 'wap-t-bad' : '' }}">{{ $mny($st['supplierDue']) }}</div>
            <div class="wap-kpi-foot">in the books: {{ $mny($st['supplierBooks']->sum()) }}</div>
        </div>
    </div>
    @endif

    @if($workspace === 'collections')
    <div class="wap-card {{ $isConstruction ? 'wa-cpm wa-cpm-register' : '' }}">
        <div class="wap-card-head">
            <h3><i class="bi bi-person-check"></i> Client Collections</h3>
            @if($canEdit)<div style="display:flex;gap:8px;flex-wrap:wrap">
                <button type="button" class="wap-btn wap-btn-ghost" onclick="document.getElementById('wa-client-modal').showModal()"><i class="bi bi-person-plus"></i> {{ $st['client'] ? 'Change Client' : 'Add Client' }}</button>
                <button type="button" class="wap-btn wap-btn-primary" onclick="document.getElementById('wa-installment-modal').showModal()"><i class="bi bi-plus-lg"></i> Add Collection</button>
            </div>@endif
        </div>
        <div class="wap-card-body">
                @if($canEdit)
                <dialog id="wa-client-modal" class="wa-entry-modal wa-collection-modal">
                <div class="wa-entry-modal-head"><div><h2>Project Client</h2><p>Select an existing client or enter a new client's details.</p></div><button type="button" class="wap-btn wap-btn-ghost" onclick="this.closest('dialog').close()" aria-label="Close">×</button></div>
                <form method="POST" action="{{ $post('project.client') }}" class="wa-entry-modal-body wa-collection-form">
                    @csrf
                    <input type="hidden" name="workspace" value="collections">
                    <div class="wa-collection-wide"><label class="wap-label">Existing client</label>
                    <select name="client_id" class="wap-input" style="max-width:220px" aria-label="Client">
                        <option value="">{{ $st['client'] ? 'Change client…' : 'Choose a client…' }}</option>
                        @foreach($st['clients'] as $cl)<option value="{{ $cl->id }}" @selected($st['client'] && $st['client']->id === $cl->id)>{{ $cl->name }}</option>@endforeach
                    </select>
                    </div><div class="wa-collection-wide wa-collection-divider">New client · complete this section if the client is not listed</div>
                    <div><label class="wap-label">Client name</label>
                    <input name="client_name" class="wap-input" style="max-width:200px" maxlength="160" placeholder="…or a new client's name" aria-label="New client name">
                    </div><div><label class="wap-label">Phone</label><input name="phone" class="wap-input" maxlength="40" aria-label="Phone"></div>
                    <div class="wa-collection-wide"><label class="wap-label" for="wa-client-email">Email</label><input id="wa-client-email" type="email" name="email" class="wap-input" maxlength="255"></div>
                    <div class="wa-collection-wide"><label class="wap-label" for="wa-client-address">Address</label><textarea id="wa-client-address" name="address" class="wap-input" rows="2" maxlength="500"></textarea></div>
                    <div class="wa-collection-wide wa-collection-divider"><label class="wap-label" for="wa-client-contract">Project contract value (৳)</label><input id="wa-client-contract" name="contract_value" type="number" min="0" max="99999999999" step="0.01" class="wap-input" value="{{ old('contract_value', $st['contract']) }}"></div>
                    <div class="wa-collection-wide wa-collection-footer"><button type="submit" class="wap-btn wap-btn-primary">Save Client &amp; Contract</button></div>
                </form>
                </dialog>
                @endif
            @if($st['contract'] === null && $st['installments']->isEmpty())
                <div class="wap-empty"><i class="bi bi-inbox"></i><div class="wap-empty-title">No contract value or installments yet</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                @if($isConstruction)<thead><tr><th>#</th><th>Date</th><th>Method</th><th>Reference</th><th class="wap-t-num">Amount</th><th>Status</th>@if($canEdit)<th>Action</th>@endif</tr></thead>@else<thead><tr><th>#</th><th>Date</th><th>Description</th><th class="wap-t-num">Received</th><th class="wap-t-num">Due after</th><th>Books</th>@if($canEdit)<th></th>@endif</tr></thead>@endif
                <tbody>
                @unless($isConstruction)<tr>
                    <td></td><td></td><td class="wap-t-strong">Contract value</td><td class="wap-t-num"></td>
                    <td class="wap-t-num wap-t-strong">{{ $st['contract'] !== null ? $mny($st['contract']) : '—' }}</td>
                    <td>@if($st['books']['billed'] > 0)<span class="wap-badge">Billed {{ $mny($st['books']['billed']) }}</span>@else<span class="wap-badge wap-badge-warn">Not billed</span>@endif</td>
                    @if($canEdit)<td>
                        @if($st['contract'] !== null && $st['books']['billed'] + 0.004 < $st['contract'])
                        <form method="POST" action="{{ $post('project.bill') }}" style="display:flex; gap:6px; align-items:center">
                            @csrf
                            <input type="date" name="entry_date" value="{{ now()->toDateString() }}" required class="wap-input" style="max-width:150px" aria-label="Billing date">
                            <button type="submit" class="wap-btn wap-btn-ghost" title="Dr Receivable 1315 / Cr Contract Revenue 4410">Bill the client {{ $mny($st['contract'] - $st['books']['billed']) }}</button>
                        </form>
                        @endif
                    </td>@endif
                </tr>@endunless
                @foreach($st['installments'] as $i => $l)
                <tr @if($reversed($l)) style="opacity:.55" @endif>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $l->entry_date ? \Carbon\Carbon::parse($l->entry_date)->format('d M Y') : '—' }}</td>
                    @if($isConstruction)
                    <td>{{ $l->counter === 'bank' ? 'Bank' : ($l->counter === 'intercompany' ? 'Inter-company' : 'Recorded') }}</td>
                    <td>{{ $l->label ?: '#'.$l->id }}@if($l->note)<span class="wap-hint"> · {{ $l->note }}</span>@endif</td>
                    <td class="wap-t-num wap-t-good">{{ $mny($l->amount) }}</td>
                    <td>@if($reversed($l))<span class="wap-badge wap-badge-warn">Reversed</span>@elseif($inBooks($l))<span class="wap-badge">Posted</span>@else<span class="wap-badge wap-badge-warn">Unposted</span>@endif</td>
                    @else
                    <td>{{ $l->label ?: 'Installment' }}@if($l->note) <span class="wap-hint">· {{ $l->note }}</span>@endif</td>
                    <td class="wap-t-num wap-t-good">{{ $mny($l->amount) }}</td>
                    <td class="wap-t-num">{{ $l->due_after !== null ? $mny($l->due_after) : '—' }}</td>
                    <td>
                        @if($reversed($l))<span class="wap-badge wap-badge-warn">Reversed</span>
                        @elseif($inBooks($l))<span class="wap-badge">In books{{ $l->counter === 'intercompany' ? ' · inter-company' : '' }}</span>
                        @else<span class="wap-badge wap-badge-warn">Not in books</span>@endif
                    </td>
                    @endif
                    @if($canEdit)<td>
                        @if(! $inBooks($l) && ! $reversed($l))
                        <form method="POST" action="{{ $post('project.statement.post', ['line' => $l->id]) }}" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap">
                            @csrf
                            @if($st['banks']->isNotEmpty())
                            <select name="counter" class="wap-input" style="max-width:230px" aria-label="Where the money went" @disabled(! $canBank)>
                                <option value="bank">Received into {{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::name() }}&rsquo;s bank</option>
                                <option value="intercompany">Received into another company&rsquo;s account</option>
                            </select>
                            <select name="bank_id" class="wap-input" style="max-width:200px" aria-label="Bank" @disabled(! $canBank)>
                                @foreach($st['banks'] as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                            </select>
                            @else
                            {{-- This company owns no bank in the system, so there is only one honest answer. --}}
                            <input type="hidden" name="counter" value="intercompany">
                            <span class="wap-hint" style="margin:0">Received into another company&rsquo;s account (inter-company)</span>
                            @endif
                            <button type="submit" class="wap-btn wap-btn-primary" @disabled($st['banks']->isNotEmpty() && ! $canBank) title="Dr bank (or Inter-Company Receivable) / Cr Receivable"><i class="bi bi-journal-check"></i> Post to books</button>
                        </form>
                        <form method="POST" action="{{ $post('project.statement.void', ['line' => $l->id]) }}" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="wap-proj-act wap-proj-act-bad" title="Remove this installment from the statement" aria-label="Remove installment"><i class="bi bi-x-lg"></i></button>
                        </form>
                        @elseif($inBooks($l))
                        <form method="POST" action="{{ $post('project.statement.reverse', ['line' => $l->id]) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="wap-btn wap-btn-ghost" title="Write an opposite journal; the original stays">Reverse</button>
                        </form>
                        @endif
                    </td>@endif
                </tr>
                @endforeach
                </tbody>
                @if($isConstruction)<tfoot><tr><td colspan="4" class="wap-t-strong" style="text-align:right">Total received</td><td class="wap-t-num wap-t-strong">{{ $mny($st['received']) }}</td><td></td>@if($canEdit)<td></td>@endif</tr></tfoot>@else<tfoot><tr>
                    <td colspan="3" class="wap-t-strong" style="text-align:right">Received / still due</td>
                    <td class="wap-t-num wap-t-strong">{{ $mny($st['received']) }}</td>
                    <td class="wap-t-num wap-t-strong {{ ($st['clientDue'] ?? 0) > 0 ? 'wap-t-bad' : '' }}">{{ $st['clientDue'] !== null ? $mny($st['clientDue']) : '—' }}</td><td></td>@if($canEdit)<td></td>@endif
                </tr></tfoot>@endif
            </table></div>
            @if(! $canBank && $canEdit)<div class="wap-hint" style="margin-top:8px">Posting through a bank needs the &ldquo;pay expense from bank&rdquo; permission.</div>@endif
            @endif

            @if($canEdit)
            <dialog id="wa-installment-modal" class="wa-entry-modal wa-collection-modal">
            <div class="wa-entry-modal-head"><div><h2>Add Client Collection</h2><p>{{ $dept->name }} · {{ $st['client']?->name ?: 'Client not assigned' }}</p></div><button type="button" class="wap-btn wap-btn-ghost" onclick="this.closest('dialog').close()" aria-label="Close">×</button></div>
            <form id="wa-installment-form" method="POST" action="{{ $post('project.statement') }}" class="wa-entry-modal-body wa-collection-form">
                @csrf
                <input type="hidden" name="workspace" value="collections">
                <input type="hidden" name="kind" value="client_installment">
                <div class="wap-field"><label class="wap-label" for="wa-i-date">Date received</label><input id="wa-i-date" name="entry_date" type="date" value="{{ now()->toDateString() }}" required class="wap-input"></div>
                <div class="wap-field"><label class="wap-label" for="wa-i-amt">Amount (৳)</label><input id="wa-i-amt" name="amount" type="number" step="1" min="1" required class="wap-input"></div>
                <div class="wap-field" style="min-width:200px"><label class="wap-label" for="wa-i-lbl">Description</label><input id="wa-i-lbl" name="label" type="text" maxlength="160" class="wap-input" placeholder="e.g. 5th installment"></div>
                @if($canBank || $st['banks']->isEmpty())
                <div class="wap-field" style="min-width:240px"><label class="wap-label" for="wa-i-bank">Received into</label>
                    @if($st['banks']->isNotEmpty())
                    <div style="display:flex; gap:6px">
                        <select name="counter" class="wap-input" aria-label="Where the money went">
                            <option value="bank">{{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::name() }}&rsquo;s bank</option>
                            <option value="intercompany">Another company&rsquo;s account</option>
                        </select>
                        <select id="wa-i-bank" name="bank_id" class="wap-input" aria-label="Bank">@foreach($st['banks'] as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    </div>
                    @else
                    {{-- This company owns no bank in the system: there is only one honest answer, so it is stated, not asked. --}}
                    <input type="hidden" name="counter" value="intercompany">
                    <input id="wa-i-bank" type="text" class="wap-input" value="Another company&rsquo;s account (inter-company)" readonly>
                    @endif
                </div>
                <label style="display:flex; gap:6px; align-items:center; font-size:13px"><input type="checkbox" name="post_now" value="1"> Post to the books now</label>
                @endif
                <div class="wa-collection-wide wa-collection-footer"><button type="submit" class="wap-btn wap-btn-primary"><i class="bi bi-check2"></i> Save Collection</button></div>
            </form>
            </dialog>
            @endif
        </div>
    </div>

    @endif
    @if($workspace === 'payables')
    <div class="wap-card {{ $isConstruction ? 'wa-cpm wa-cpm-register' : '' }}" id="wa-supplier-dues" style="scroll-margin-top:90px">
        <div class="wap-card-head">
            <h3><i class="bi bi-truck"></i> Supplier dues</h3>
            <span class="wap-card-sub">What each supplier or contractor is still owed &mdash; due noted minus paid since</span>
        </div>
        <div class="wap-card-body">
            @if($st['suppliers']->isEmpty())
                <div class="wap-empty"><i class="bi bi-check2-circle"></i><div class="wap-empty-title">No supplier dues noted</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Supplier / contractor</th><th class="wap-t-num">Due noted</th><th class="wap-t-num">Paid since</th><th class="wap-t-num">Still owed</th><th class="wap-t-num">Owed in books</th><th>Latest entry</th></tr></thead>
                <tbody>
                @foreach($st['suppliers'] as $sp)
                <tr>
                    <td class="wap-t-strong">@if($sp['party_id'] ?? null)<a href="{{ route('role.party-statement.vendors', ['role' => $role, 'company' => \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::id(), 'project' => $dept->id, 'party_id' => $sp['party_id']]) }}">{{ $sp['party'] }} <span class="wap-hint">EP-VN-{{ str_pad((string) $sp['party_id'], 3, '0', STR_PAD_LEFT) }}</span></a>@else{{ $sp['party'] }}@endif</td>
                    <td class="wap-t-num">{{ $mny($sp['bill']) }}</td>
                    <td class="wap-t-num">{{ $sp['paid'] > 0 ? $mny($sp['paid']) : '—' }}</td>
                    <td class="wap-t-num wap-t-strong {{ $sp['due'] > 0 ? 'wap-t-bad' : '' }}">{{ $mny($sp['due']) }}</td>
                    <td class="wap-t-num">{{ ($st['supplierBooks'][$sp['party']] ?? 0) > 0 ? $mny($st['supplierBooks'][$sp['party']]) : '—' }}</td>
                    <td>{{ $sp['last'] ? \Carbon\Carbon::parse($sp['last'])->format('d M Y') : '—' }}</td>
                </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td class="wap-t-strong">Total</td>
                    <td class="wap-t-num">{{ $mny($st['suppliers']->sum('bill')) }}</td>
                    <td class="wap-t-num">{{ $mny($st['suppliers']->sum('paid')) }}</td>
                    <td class="wap-t-num wap-t-strong wap-t-bad">{{ $mny($st['supplierDue']) }}</td>
                    <td class="wap-t-num wap-t-strong">{{ $mny($st['supplierBooks']->sum()) }}</td><td></td>
                </tr></tfoot>
            </table></div>
            @endif

            @if($st['lines']->whereIn('kind', ['supplier_bill', 'supplier_payment', 'supplier_balance'])->isNotEmpty())
            <div class="wap-table-wrap" style="margin-top:14px"><table class="wap-table">
                <thead><tr><th>Date</th><th>Supplier</th><th>Entry</th><th class="wap-t-num">Amount</th><th>Books</th>@if($canEdit)<th></th>@endif</tr></thead>
                <tbody>
                @foreach($st['lines']->whereIn('kind', ['supplier_bill', 'supplier_payment', 'supplier_balance']) as $l)
                <tr @if($reversed($l)) style="opacity:.55" @endif>
                    <td>{{ $l->entry_date ? \Carbon\Carbon::parse($l->entry_date)->format('d M Y') : '—' }}</td>
                    <td class="wap-t-strong">{{ $l->party }}</td>
                    <td><span class="wap-badge {{ $l->kind === 'supplier_payment' ? '' : 'wap-badge-warn' }}">{{ $kindLabel[$l->kind] }}</span> {{ $l->label }}@if($l->note) <span class="wap-hint">· {{ $l->note }}</span>@endif</td>
                    <td class="wap-t-num">{{ $mny($l->amount) }}</td>
                    <td>
                        @if($reversed($l))<span class="wap-badge wap-badge-warn">Reversed</span>
                        @elseif($inBooks($l))<span class="wap-badge">In books</span>
                        @elseif($l->covered_by_balance ?? false)<span class="wap-badge">{{ $l->history_label ?? 'Included in net balance' }}</span>
                        @else<span class="wap-badge wap-badge-warn">Not in books</span>@endif
                    </td>
                    @if($canEdit)<td>
                        @if(! $inBooks($l) && ! $reversed($l) && ! ($l->covered_by_balance ?? false))
                        <form method="POST" action="{{ $post('project.statement.post', ['line' => $l->id]) }}" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap">
                            @csrf
                            @if($l->kind === 'supplier_bill')
                            <select name="cost_sub_category_id" required class="wap-input" style="max-width:200px" aria-label="Cost head">
                                <option value="">Cost head…</option>
                                @foreach($st['heads'] as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                            </select>
                            <select name="payable_kind" class="wap-input" style="max-width:150px" aria-label="Payable kind">
                                <option value="supplier">Supplier (2111)</option><option value="contractor">Contractor (2112)</option>
                            </select>
                            @elseif($st['banks']->isNotEmpty())
                            <select name="bank_id" class="wap-input" style="max-width:220px" aria-label="Pay from" @disabled(! $canBank)>
                                @foreach($st['banks'] as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                            </select>
                            @else
                            <span class="wap-hint" style="max-width:230px">{{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::name() }} has no bank of its own — paid from another company&rsquo;s account, so this stays in the statement.</span>
                            @endif
                            <button type="submit" class="wap-btn wap-btn-primary" @disabled($l->kind === 'supplier_payment' && (! $canBank || $st['banks']->isEmpty()))><i class="bi bi-journal-check"></i> Post to books</button>
                        </form>
                        <form method="POST" action="{{ $post('project.statement.void', ['line' => $l->id]) }}" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="wap-proj-act wap-proj-act-bad" title="Remove from the statement" aria-label="Remove entry"><i class="bi bi-x-lg"></i></button>
                        </form>
                        @elseif($inBooks($l))
                        <form method="POST" action="{{ $post('project.statement.reverse', ['line' => $l->id]) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="wap-btn wap-btn-ghost" title="Write an opposite journal; the original stays">Reverse</button>
                        </form>
                        @elseif($l->covered_by_balance ?? false)
                        <span class="wap-hint">{{ ! empty($l->history_label) && str_starts_with($l->history_label, 'Settled') ? 'Historical detail only; nothing is owed, so nothing to post.' : (! empty($l->history_label) && str_starts_with($l->history_label, 'History') ? 'Historical detail only; its net balance is posted once from the summary page.' : 'Historical detail only; its net due is already journaled.') }}</span>
                        @endif
                    </td>@endif
                </tr>
                @endforeach
                </tbody>
            </table></div>
            @endif

            @if($canEdit)
            <button type="button" class="wap-btn wap-btn-primary" style="margin-top:14px" onclick="document.getElementById('wa-supplier-modal').showModal()"><i class="bi bi-plus-lg"></i> Record supplier activity</button>
            <dialog id="wa-supplier-modal" class="wa-entry-modal">
            <div class="wa-entry-modal-head"><div><div style="font-size:12px;color:#ccfbf1">Supplier ledger</div><h2 style="margin:3px 0 0;font-size:21px">Record due or payment</h2></div><button type="button" class="wap-btn wap-btn-ghost" onclick="this.closest('dialog').close()">Close</button></div>
            <form id="wa-supplier-form" method="POST" action="{{ $post('project.statement') }}" class="wa-entry-modal-body" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end">
                @csrf
                <div class="wap-field"><label class="wap-label" for="wa-s-kind">Entry</label>
                    <select id="wa-s-kind" name="kind" class="wap-input"><option value="supplier_bill">A new due</option><option value="supplier_payment">A payment made</option></select></div>
                <div class="wap-field" style="min-width:200px"><label class="wap-label" for="wa-s-party">Supplier / contractor</label><input id="wa-s-party" name="party" type="text" required maxlength="160" class="wap-input" list="wa-s-parties">
                    <datalist id="wa-s-parties">@foreach($st['suppliers'] as $sp)<option value="{{ $sp['party'] }}"></option>@endforeach</datalist></div>
                <div class="wap-field"><label class="wap-label" for="wa-s-date">Date</label><input id="wa-s-date" name="entry_date" type="date" value="{{ now()->toDateString() }}" required class="wap-input"></div>
                <div class="wap-field"><label class="wap-label" for="wa-s-amt">Amount (৳)</label><input id="wa-s-amt" name="amount" type="number" step="1" min="1" required class="wap-input"></div>
                <div class="wap-field" style="min-width:200px"><label class="wap-label" for="wa-s-lbl">Description</label><input id="wa-s-lbl" name="label" type="text" maxlength="160" class="wap-input"></div>
                <button type="submit" class="wap-btn wap-btn-primary"><i class="bi bi-plus-lg"></i> Save entry</button>
            </form>
            </dialog>
            <div class="wap-hint" style="margin-top:8px">New entries start in the statement. Use <strong>Post to books</strong> on a row to make it a real journal: a due posts to Supplier / Subcontractor Payable against the cost head you choose; a payment goes out of {{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::name() }}&rsquo;s own bank only. Posted entries are reversed, never edited or deleted.</div>
            @endif
        </div>
    </div>
    @endif

    @endif
    @endif
    @if(in_array($workspace, ['expenses', 'reports'], true))
    <style>
    .wa-exp-filter{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px;margin:14px 0 10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}.wa-exp-filter .wap-input{height:34px;font-size:11px;border-radius:7px}.wa-exp-filter input[type=search]{flex:1;min-width:180px}.wa-exp-filter .wa-exp-quick{display:inline-flex;border:1px solid #dbe3ee;border-radius:7px;overflow:hidden;background:#fff}.wa-exp-quick a{padding:8px 10px;border-right:1px solid #e2e8f0;color:#64748b;font-size:10px;font-weight:700;text-decoration:none}.wa-exp-quick a.is-on{background:#ecfdf5;color:#0f766e}.wa-exp-print-dialog{width:min(480px,calc(100vw - 32px));padding:22px;border:1px solid #e2e8f0;border-radius:12px;color:#334155}.wa-exp-print-dialog::backdrop{background:rgba(15,23,42,.45)}
    </style>
    @if(\Modules\WoodArt\Modules\Accounts\Support\ProjectContext::key() === 'construction')
        @include('woodart::construction.filters')
    @else
    <form method="GET" class="wa-exp-filter" action="{{ $base }}">
        <input type="hidden" name="view" value="{{ $workspace }}">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $q }}" class="wap-input" placeholder="Find by entry, person or head…" aria-label="Search entries">
        <div class="wa-exp-quick" aria-label="Quick date filters">
        @foreach(['Today' => [now()->toDateString(), now()->toDateString()], 'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()], 'Last Week' => [now()->subDays(6)->toDateString(), now()->toDateString()], 'All' => [null,null]] as $label => [$rangeFrom,$rangeTo])
            <a class="{{ $from === $rangeFrom && $to === $rangeTo ? 'is-on' : '' }}" href="{{ $base.'?'.http_build_query(['view' => $workspace, 'q' => $q, 'from' => $rangeFrom, 'to' => $rangeTo]) }}">{{ $label }}</a>
        @endforeach
        </div>
        <input type="date" name="from" value="{{ $from }}" class="wap-input" style="max-width:160px" aria-label="From">
        <input type="date" name="to" value="{{ $to }}" class="wap-input" style="max-width:160px" aria-label="To">
        <button type="submit" class="wap-btn wap-btn-primary">Apply</button>
        @if($workspace === 'expenses')<button type="button" class="wap-btn wap-btn-ghost" data-wa-project-print="{{ $base }}"><i class="bi bi-printer"></i> Print</button>@endif
        @if($q !== '' || $from || $to)<a class="wap-btn wap-btn-ghost" href="{{ $base }}">Clear</a>@endif
    </form>
    @endif

    @if($workspace === 'reports')
    <a class="wap-btn wap-btn-ghost" href="{{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::route('project.journals', ['department' => $dept->id]) }}">View Journals</a>
    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-pie-chart"></i> Cost by head</h3>
            <span class="wap-card-sub">What the money went on</span>
        </div>
        <div class="wap-card-body">
            @if($sheet['byHead']->isEmpty())
                <div class="wap-empty"><i class="bi bi-inbox"></i><div class="wap-empty-title">No posted cost yet</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Head</th><th class="wap-t-num">Entries</th><th class="wap-t-num">Share</th><th class="wap-t-num">Cost</th></tr></thead>
                <tbody>
                @foreach($sheet['byHead'] as $h)
                <tr>
                    <td class="wap-t-strong">{{ $h['name'] }}</td>
                    <td class="wap-t-num">{{ $h['n'] }}</td>
                    <td class="wap-t-num">{{ $sheet['total'] > 0 ? number_format($h['total'] / $sheet['total'] * 100, 1) : 0 }}%</td>
                    <td class="wap-t-num wap-t-strong">{{ $mny($h['total']) }}</td>
                </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td class="wap-t-strong">Total</td><td class="wap-t-num">{{ $sheet['byHead']->sum('n') }}</td><td></td><td class="wap-t-num wap-t-strong">{{ $mny($sheet['total']) }}</td></tr></tfoot>
            </table></div>
            @endif
        </div>
    </div>

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-person-lines-fill"></i> Who paid</h3>
            <span class="wap-card-sub">By the person whose hand the money went through</span>
        </div>
        <div class="wap-card-body">
            @if($sheet['byPayer']->isEmpty())
                <div class="wap-empty"><i class="bi bi-inbox"></i><div class="wap-empty-title">Nobody yet</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Person</th><th class="wap-t-num">Entries</th><th class="wap-t-num">Spent here</th><th class="wap-t-num">Of it, own pocket</th><th class="wap-t-num">Company still owes them*</th></tr></thead>
                <tbody>
                @foreach($sheet['byPayer'] as $p)
                @php $o = $sheet['owed']->firstWhere('name', $p['name']); @endphp
                <tr>
                    <td class="wap-t-strong">{{ $p['name'] }}</td>
                    <td class="wap-t-num">{{ $p['n'] }}</td>
                    <td class="wap-t-num">{{ $mny($p['total']) }}</td>
                    <td class="wap-t-num">{{ $p['pocket'] > 0 ? $mny($p['pocket']) : '—' }}</td>
                    <td class="wap-t-num {{ $o && $o['owed'] > 0 ? 'wap-t-bad' : '' }}">{{ $o ? $mny($o['owed']) : '—' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="wap-hint" style="margin-top:8px">* A person&rsquo;s balance is for ALL {{ \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::name() }}, not only this project &mdash; it is the Reimbursement desk&rsquo;s figure.</div>
            @endif
        </div>
    </div>

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-calendar3"></i> Cost by month</h3>
            <span class="wap-card-sub">Posted entries</span>
        </div>
        <div class="wap-card-body">
            @if($sheet['byMonth']->isEmpty())
                <div class="wap-empty"><i class="bi bi-inbox"></i><div class="wap-empty-title">No posted cost yet</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Month</th><th class="wap-t-num">Entries</th><th class="wap-t-num">Cost</th></tr></thead>
                <tbody>
                @foreach($sheet['byMonth'] as $m)
                <tr>
                    <td class="wap-t-strong">{{ \Carbon\Carbon::createFromFormat('Y-m', $m['month'])->format('M Y') }}</td>
                    <td class="wap-t-num">{{ $m['n'] }}</td>
                    <td class="wap-t-num">{{ $mny($m['total']) }}</td>
                </tr>
                @endforeach
                </tbody>
            </table></div>
            @endif
        </div>
    </div>

    @endif
    @if($workspace === 'expenses')
    <div class="wap-card {{ $isConstruction ? 'wa-cpm wa-cpm-register' : '' }}" id="wa-ledger" style="scroll-margin-top:80px">
        <div class="wap-card-head">
            <h3><i class="bi bi-receipt"></i> {{ $isConstruction ? 'Project Expense Register' : 'Expense List' }}</h3>
            <span class="wap-card-sub">{{ $isConstruction ? 'Approved and pending project expenses' : 'Every entry in date order, with the running total of posted cost' }}</span>
        </div>
        <div class="wap-card-body">
            @if($sheet['entries']->isEmpty())
                <div class="wap-empty"><i class="bi bi-journal-text"></i><div class="wap-empty-title">No entries</div>
                    <div class="wap-empty-sub">{{ ($q !== '' || $from || $to) ? 'Nothing matches this filter.' : 'Use New Entry to add this site\'s costs.' }}</div></div>
            @else
            @php
                $pendingRows = $sheet['entries']->where('posted', false);
                $nPending    = $pendingRows->count();
                $canConfirm  = $canEdit && $nPending > 0;
            @endphp

            @if($nPending > 0)
            <div class="wap-banner" style="margin-bottom:12px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-hourglass-split" style="color:#9a6b00"></i>
                <div><strong>{{ $nPending }}</strong> {{ $nPending === 1 ? 'entry is' : 'entries are' }} waiting for confirmation
                    (<strong>{{ $mny($pendingRows->sum('amount')) }}</strong>). A pending entry is <strong>not in the books yet</strong>:
                    check who paid and from where, then <strong>Confirm</strong> &mdash; that posts its journal through the Expense desk&rsquo;s own Approve.
                    To change who paid first, open the entry in Expense &rarr; All Expenses (search its reference, e.g. <em>WA-MV-001</em>).</div>
            </div>
            @endif

            @if($canConfirm)<form method="POST" action="{{ $post('project.confirm') }}">@csrf
                <input type="hidden" name="q" value="{{ $q }}"><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px">
                    <button type="submit" name="selected" value="1" class="wap-btn wap-btn-ghost"><i class="bi bi-check2-square"></i> Confirm ticked entries</button>
                    <button type="submit" name="selected" value="1" class="wap-btn wap-btn-primary" onclick="this.form.querySelectorAll('input[name=\"ids[]\"]').forEach(input => input.checked = true)"
                            title="Every pending entry the sheet is showing right now (respects the search and dates above)"
                            style="background:#16955c; border-color:#16955c"><i class="bi bi-check2-all"></i> Confirm all {{ $nPending }} pending</button>
                </div>
            @endif
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr>
                    @if($canConfirm)<th style="width:34px"></th>@endif
                    @if($isConstruction)
                    <th>#</th><th>Date</th><th>Head</th><th>Description</th><th>Paid by</th><th>Payment source</th><th class="wap-t-num">Amount</th><th>Status</th><th>Action</th>
                    @else
                    <th>#</th><th>Date</th><th>Entry</th><th>Head</th><th>Paid by</th><th>Money was</th>
                    <th class="wap-t-num">Amount</th><th class="wap-t-num">Running total</th>@if($canConfirm)<th></th>@endif
                    @endif
                </tr></thead>
                <tbody>
                @foreach($sheet['entries'] as $i => $e)
                <tr @if(! $e->posted) style="opacity:.8" @endif>
                    @if($canConfirm)<td>@if(! $e->posted)<input type="checkbox" name="ids[]" value="{{ $e->id }}" aria-label="Tick {{ $e->reference ?: '#'.$e->id }}">@endif</td>@endif
                    @if($isConstruction)
                    <td>{{ $sheet['entries']->firstItem() + $i }}</td>
                    <td>{{ \Carbon\Carbon::parse($e->expense_date)->format('d M Y') }}</td>
                    <td>{{ $e->head ?: '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($e->title, 55) }}@if($e->reference)<span class="wap-hint">{{ $e->reference }}</span>@endif</td>
                    <td>{{ $e->reference_user_name ?: '—' }}</td>
                    <td>{{ $e->source }}</td>
                    <td class="wap-t-num">{{ $mny($e->amount) }}</td>
                    <td><span class="wap-badge {{ $e->posted ? '' : 'wap-badge-warn' }}">{{ $e->posted ? 'Posted' : 'Pending' }}</span></td>
                    <td><div class="wa-cpm-register-actions">@if($canConfirm && ! $e->posted)<button type="submit" name="only" value="{{ $e->id }}" class="wap-btn wap-btn-ghost" title="Post this entry's journal now"><i class="bi bi-check2"></i></button>@endif @can('view expense')<a class="wap-btn wap-btn-ghost" data-no-prefetch target="_blank" rel="noopener" href="{{ route('role.expenses.slip', ['role' => $role, 'id' => $e->id]) }}" aria-label="View expense {{ $e->reference ?: '#'.$e->id }}"><i class="bi bi-file-earmark-text"></i></a>@endcan @can('edit expense')<a class="wap-btn wap-btn-ghost" href="{{ route('role.expenses.edit', ['role' => $role, 'expense' => $e->id]) }}" aria-label="Edit expense {{ $e->reference ?: '#'.$e->id }}"><i class="bi bi-pencil"></i></a>@endcan</div></td>
                    @else
                    <td>{{ ($workspace === 'expenses' ? $sheet['entries']->firstItem() : 1) + $i }}</td>
                    <td>{{ \Carbon\Carbon::parse($e->expense_date)->format('d M Y') }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($e->title, 70) }}
                        @if(! $e->posted)<span class="wap-badge wap-badge-warn">Pending</span>@endif
                        @if($e->reference)<div class="wap-hint" style="margin:2px 0 0">{{ $e->reference }}</div>@endif</td>
                    <td>{{ $e->head ?: '—' }}</td>
                    <td>{{ $e->reference_user_name ?: '—' }}</td>
                    <td>{{ $e->source }}</td>
                    <td class="wap-t-num">{{ $mny($e->amount) }}</td>
                    <td class="wap-t-num wap-t-strong">{{ $e->posted ? $mny($e->running) : '—' }}</td>
                    @if($canConfirm)<td>@if(! $e->posted)<button type="submit" name="only" value="{{ $e->id }}" class="wap-btn wap-btn-ghost" title="Post this entry's journal now"><i class="bi bi-check2"></i> Confirm</button>@endif</td>@endif
                    @endif
                </tr>
                @endforeach
                </tbody>
            </table></div>
            @if($canConfirm)</form>@endif
            <div style="margin-top:14px">{{ $sheet['entries']->links() }}</div>
            @endif
        </div>
    </div>

    @endif
    @endif
@endsection
