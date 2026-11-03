{{--
    Project Accounts — the project summary: what the boss opens. One project, one screen:
      · what needs the accountant's attention
      · the money: contract, received, client due · cost, supplier dues, the site team's balance
      · the client's paid / due ledger (Dr / Cr, like the ERP's Party Statement)
      · each supplier: bought, paid, owed — with a link to his statement
      · the site team (admin / site engineer): spent, funds provided, balance — and the form that
        records funds provided to a person
      · cost by head
    Read-only except the funds form and its Post button. Anchors only; no <script>.
--}}
@extends('woodart::layouts.suite')

@php
    $PC   = \Modules\WoodArt\Modules\Accounts\Support\ProjectContext::class;
    $role = request()->route('role');
    $mny  = fn ($n) => '৳ ' . number_format((float) $n, 0);
    $post = fn ($suffix, $extra = []) => $PC::route($suffix, ['role' => $role, 'department' => $dept->id] + $extra);
    $sheetUrl = $PC::route('project', ['role' => $role, 'department' => $dept->id]);
    $contract = $st['contract'];
    $pct = ($contract && $contract > 0) ? min(100, round($st['received'] / $contract * 100)) : null;
    $vendorStatement = fn ($partyId) => \Illuminate\Support\Facades\Route::has('role.party-statement.vendors')
        ? route('role.party-statement.vendors', ['role' => $role, 'company' => $PC::id(), 'project' => $dept->id, 'party_id' => $partyId]) : null;
    $drcr = fn ($n) => abs($n) < 0.005 ? '0' : number_format(abs($n)) . ' ' . ($n < 0 ? 'Cr' : 'Dr');
    // every cost entry by head — confirmed or still waiting (nothing may look missing)
    $heads = $sheet['entries']->groupBy(fn ($e) => $e->head ?: 'Uncategorised')->map(fn ($g, $k) => [
        'name' => $k, 'total' => (float) $g->sum('amount'), 'waiting' => (float) $g->where('posted', false)->sum('amount'), 'n' => $g->count(),
    ])->sortByDesc('total')->values();
    $maxHead = max(1, (float) ($heads->max('total') ?? 1));

    // client ledger, oldest first for the running Dr balance, shown newest first
    $cl = collect();
    if ($contract !== null) {
        $cl->push((object) ['date' => null, 'what' => 'Contract value', 'sub' => null, 'dr' => (float) $contract, 'cr' => 0.0, 'books' => $st['books']['billed'] > 0 ? 'billed' : '']);
    }
    foreach ($st['installments'] as $i) {
        $cl->push((object) ['date' => $i->entry_date, 'what' => $i->label ?: 'Installment', 'sub' => $i->note, 'dr' => 0.0, 'cr' => (float) $i->amount,
            'books' => $i->reversal_journal_entry_id ? 'reversed' : ($i->journal_entry_id ? 'in books' : 'not in books')]);
    }
    $run = 0.0;
    $cl->each(function ($r) use (&$run) { $run += $r->dr - $r->cr; $r->bal = $run; });
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost" href="{{ $PC::route('', ['role' => $role, 'section' => 'projects']) }}"><i class="bi bi-arrow-left"></i> All projects</a>
    <details style="position:relative">
        <summary class="wap-btn wap-btn-ghost" style="list-style:none;cursor:pointer"><i class="bi bi-person-plus"></i> Add party <i class="bi bi-chevron-down"></i></summary>
        <div style="position:absolute;right:0;top:calc(100% + 7px);z-index:40;width:210px;background:#fff;border:1px solid #dbe3ea;border-radius:10px;padding:7px;box-shadow:0 12px 28px rgba(15,23,42,.16)">
            @can('create vendor')
            <a style="display:block;padding:9px 10px;border-radius:7px;color:#334155;text-decoration:none" href="{{ route('role.vendor.create', ['role' => $role, 'company' => $PC::id(), 'project' => $dept->id]) }}"><i class="bi bi-building-add" style="margin-right:7px"></i>Add vendor</a>
            <a style="display:block;padding:9px 10px;border-radius:7px;color:#334155;text-decoration:none" href="{{ route('role.vendor.create', ['role' => $role, 'company' => $PC::id(), 'project' => $dept->id, 'vendor_type' => 'material_supplier']) }}"><i class="bi bi-truck" style="margin-right:7px"></i>Add supplier</a>
            <a style="display:block;padding:9px 10px;border-radius:7px;color:#334155;text-decoration:none" href="{{ route('role.vendor.create', ['role' => $role, 'company' => $PC::id(), 'project' => $dept->id, 'vendor_type' => 'contractor']) }}"><i class="bi bi-hammer" style="margin-right:7px"></i>Add contractor</a>
            <a style="display:block;padding:9px 10px;border-radius:7px;color:#334155;text-decoration:none" href="{{ route('role.agent.create', ['role' => $role, 'company' => $PC::id(), 'project' => $dept->id]) }}"><i class="bi bi-person-badge" style="margin-right:7px"></i>Add agent</a>
            @endcan
            @can('create customer')
            <a style="display:block;padding:9px 10px;border-radius:7px;color:#334155;text-decoration:none" href="{{ route('role.customer.create', ['role' => $role, 'company' => $PC::id(), 'project' => $dept->id]) }}"><i class="bi bi-person" style="margin-right:7px"></i>Add client</a>
            @endcan
        </div>
    </details>
    @if(\Illuminate\Support\Facades\Route::has($PC::routeName('bulk')))
    <a class="wap-btn wap-btn-primary" href="{{ $PC::route('bulk', ['role' => $role, 'project' => $dept->id]) }}"><i class="bi bi-plus-lg"></i> New Entry</a>
    @endif
@endsection

@section('wa-view')

    @if(session('success'))<div class="wap-banner" style="margin-bottom:14px"><i class="bi bi-check2-circle" style="color:#16955c"></i><div>{{ session('success') }}</div></div>@endif
    @if(session('error'))<div class="wap-banner" style="background: rgba(194,41,69,.08); border-color: rgba(194,41,69,.35); margin-bottom:14px"><i class="bi bi-exclamation-octagon" style="color:#c22945"></i><div>{{ session('error') }}</div></div>@endif

    @include('woodart::project-nav', ['navActive' => 'summary'])

    <section style="display:flex;justify-content:space-between;gap:18px;align-items:center;flex-wrap:wrap;margin-bottom:14px;padding:14px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:11px">
        <div><span class="wap-hint" style="text-transform:uppercase;letter-spacing:.06em">Project financial workspace</span><div style="margin-top:4px;font-size:15px;font-weight:800;color:#0f172a">{{ $dept->name }}</div><div class="wap-hint">{{ $st['client']?->name ?: 'Client not assigned' }} · {{ $st['contract'] !== null ? 'Contract '.$mny($st['contract']) : 'Contract value not set' }}</div></div>
        <span class="wap-badge">{{ $st['contract'] !== null ? 'Active' : 'Setup needed' }}</span>
    </section>

    @if(! $st['ready'])
    <div class="wap-banner"><i class="bi bi-info-circle"></i><div>The client and supplier sheets are not installed on this server yet.</div></div>
    @else

    {{-- ── needs attention ─────────────────────────────────────────────── --}}
    @php $att = []; @endphp
    @if($unfunded->isNotEmpty())
        @php $att[] = ['icon' => 'exclamation-triangle', 'tone' => '#b45309',
            'text' => 'Funds provided are not recorded for ' . $unfunded->pluck('name')->implode(', ') . '. Until they are, confirming their costs shows the whole amount as owed to them.',
            'link' => '#wa-team', 'cta' => 'Record funds']; @endphp
    @endif
    @if($pendingN > 0)
        @php $att[] = ['icon' => 'hourglass-split', 'tone' => '#0f766e', 'text' => $pendingN . ' cost ' . ($pendingN === 1 ? 'entry is' : 'entries are') . ' waiting for the accountant to confirm (' . $mny($sheet['pending']) . ').', 'link' => $sheetUrl . '#wa-ledger', 'cta' => 'Open entries']; @endphp
    @endif
    @if($unbooked > 0)
        @php $att[] = ['icon' => 'journal-x', 'tone' => '#475569', 'text' => $unbooked . ' client / supplier / funds ' . ($unbooked === 1 ? 'line is' : 'lines are') . ' in the statement but not in the books yet.', 'link' => $sheetUrl, 'cta' => 'Open posting']; @endphp
    @endif
    @if($unpostedFunds > 0)
        @php $att[] = ['icon' => 'journal-check', 'tone' => '#475569', 'text' => $unpostedFunds . ' funds ' . ($unpostedFunds === 1 ? 'entry is' : 'entries are') . ' recorded for the team but not posted to the books yet.', 'link' => '#wa-team', 'cta' => 'Post them']; @endphp
    @endif
    @if(! $st['client'])
        @php $att[] = ['icon' => 'person-plus', 'tone' => '#475569', 'text' => 'No client is set for this project, so the client\'s own statement page is not available yet.', 'link' => $sheetUrl . '#wa-client-set', 'cta' => 'Set client']; @endphp
    @endif

    @if($att)
    <div class="wap-card">
        <div class="wap-card-head"><h3><i class="bi bi-bell"></i> Needs attention</h3><span class="wap-card-sub">{{ count($att) }} {{ count($att) === 1 ? 'thing' : 'things' }}</span></div>
        <div class="wap-card-body" style="display:grid; gap:10px">
            @foreach($att as $a)
            <div style="display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap">
                <div style="display:flex; gap:10px; align-items:center"><i class="bi bi-{{ $a['icon'] }}" style="color: {{ $a['tone'] }}; font-size:18px"></i><span>{{ $a['text'] }}</span></div>
                <a class="wap-btn wap-btn-ghost" href="{{ $a['link'] }}">{{ $a['cta'] }}</a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── post everything to the books, in one go ─────────────────────── --}}
    @php
        $hasWork = $plan['billDue'] > 0.004 || $plan['inst']->isNotEmpty() || $plan['funds']->isNotEmpty() || $plan['suppliers']->isNotEmpty() || $plan['pendingIds'];
        $costsLocked = $unfunded->isNotEmpty();
    @endphp
    @if($canEdit && $hasWork)
    <div class="wap-card" id="wa-post-all" style="scroll-margin-top:80px">
        <div class="wap-card-head">
            <h3><i class="bi bi-journal-check"></i> Post to the books — all at once</h3>
            <span class="wap-card-sub">No bank balance is touched. The run checks every bank account before and after, and cancels itself if one would move.</span>
        </div>
        <div class="wap-card-body">
            <form method="POST" action="{{ $post('project.post-all') }}">
                @csrf
                <div style="display:grid; gap:12px">
                    @if($plan['billDue'] > 0.004)
                    <label style="display:flex; gap:12px; align-items:flex-start"><input type="checkbox" name="do_bill" value="1" checked style="margin-top:4px">
                        <span><b>Bill the client for the contract</b> — {{ $mny($plan['billDue']) }}<br><span class="wap-hint">Dr Client receivable / Cr Contract revenue. Needed before installments can be posted.</span></span></label>
                    @endif
                    @if($plan['inst']->isNotEmpty())
                    <label style="display:flex; gap:12px; align-items:flex-start"><input type="checkbox" name="do_inst" value="1" checked @disabled(! $plan['noBank']) style="margin-top:4px">
                        <span><b>{{ $plan['inst']->count() }} client installment{{ $plan['inst']->count() === 1 ? '' : 's' }}</b> — {{ $mny($plan['instSum']) }}<br><span class="wap-hint">Received into another company's account: Dr Inter-company / Cr Client receivable.</span></span></label>
                    @endif
                    @if($plan['funds']->isNotEmpty())
                    <label style="display:flex; gap:12px; align-items:flex-start"><input type="checkbox" name="do_funds" value="1" checked style="margin-top:4px">
                        <span><b>{{ $plan['funds']->count() }} funds {{ $plan['funds']->count() === 1 ? 'entry' : 'entries' }} provided to the team</b> — {{ $mny($plan['fundsSum']) }}<br><span class="wap-hint">Dr the person's account (Employee payable) / Cr Inter-company.</span></span></label>
                    @endif
                    @if($plan['suppliers']->isNotEmpty())
                    <label style="display:flex; gap:12px; align-items:flex-start"><input type="checkbox" name="do_suppliers" value="1" checked style="margin-top:4px">
                        <span><b>Supplier balances still owed</b> — {{ $mny($plan['suppliersSum']) }}<br>
                            <span class="wap-hint">Only the net balance is booked (Dr cost head / Cr Accounts payable): the part already paid is a cost entry, so booking every bill would count it twice.</span>
                            @foreach($plan['suppliers'] as $sp)<br><span class="wap-hint">· {{ $sp->party }} {{ $mny($sp->amount) }} → {{ $sp->head ?: 'no cost head set — post it on Entries & posting' }}</span>@endforeach</span></label>
                    @endif
                    @if($plan['pendingIds'])
                    <label style="display:flex; gap:12px; align-items:flex-start"><input type="checkbox" name="do_costs" value="1" @checked(! $costsLocked) @disabled($costsLocked) style="margin-top:4px">
                        <span><b>Confirm {{ count($plan['pendingIds']) }} cost {{ count($plan['pendingIds']) === 1 ? 'entry' : 'entries' }}</b> — {{ $mny($plan['pendingSum']) }}<br>
                            <span class="wap-hint">Dr cost / Cr the person who spent it (the company owes him, until funds provided are recorded against it).</span>
                            @if($costsLocked)<br><span class="wap-hint" style="color:#b45309">Locked: record the funds provided to {{ $unfunded->pluck('name')->implode(', ') }} first (Site team below), or their whole spend would show as owed.</span>@endif</span></label>
                    @endif
                </div>
                <button type="submit" class="wap-btn wap-btn-primary" style="margin-top:14px" onclick="return confirm('Post the ticked items to the books? Nothing is deleted; every posting can be reversed. No bank balance will change.')"><i class="bi bi-journal-check"></i> Post the ticked items</button>
            </form>
        </div>
    </div>
    @endif

    {{-- ── the money ───────────────────────────────────────────────────── --}}
    <div class="wap-caption">Client money</div>
    <div class="wap-kpi-grid" style="--kpi-cols:3">
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Contract value</span><span class="wap-kpi-ico"><i class="bi bi-file-earmark-text"></i></span></div>
            <div class="wap-kpi-value">{{ $contract !== null ? $mny($contract) : '—' }}</div>
            <div class="wap-kpi-foot">{{ $st['client'] ? $st['client']->name : 'client not set' }}</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Received from client</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($st['received']) }}</div>
            <div class="wap-kpi-foot">{{ $st['installments']->count() }} installments{{ $pct !== null ? ' · ' . $pct . '% of the contract' : '' }}</div>
            @if($pct !== null)<div style="height:6px; border-radius:6px; background:#e2e8f0; margin-top:8px; overflow:hidden"><div style="height:100%; width: {{ $pct }}%; background:#0f766e"></div></div>@endif
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Client still owes</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
            <div class="wap-kpi-value {{ ($st['clientDue'] ?? 0) > 0 ? 'wap-t-bad' : '' }}">{{ $st['clientDue'] !== null ? $mny($st['clientDue']) : '—' }}</div>
            <div class="wap-kpi-foot">still to come</div>
        </div>
    </div>

    <div class="wap-caption">Cost &amp; who is owed</div>
    <div class="wap-kpi-grid" style="--kpi-cols:5">
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Approved project cost</span><span class="wap-kpi-ico"><i class="bi bi-briefcase"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($sheet['total']) }}</div>
            <div class="wap-kpi-foot">approved ERP expenses only</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Pending approvals</span><span class="wap-kpi-ico"><i class="bi bi-hourglass"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($sheet['pending']) }}</div>
            <div class="wap-kpi-foot">not included in approved cost</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Suppliers are owed</span><span class="wap-kpi-ico"><i class="bi bi-truck"></i></span></div>
            <div class="wap-kpi-value {{ $st['supplierDue'] > 0 ? 'wap-t-bad' : '' }}">{{ $mny($st['supplierDue']) }}</div>
            <div class="wap-kpi-foot">{{ $st['suppliers']->where('due', '>', 0.004)->count() }} of {{ $st['suppliers']->count() }} suppliers</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Company owes the team</span><span class="wap-kpi-ico"><i class="bi bi-people"></i></span></div>
            <div class="wap-kpi-value {{ $staffOwed > 0 ? 'wap-t-bad' : '' }}">{{ $mny($staffOwed) }}</div>
            <div class="wap-kpi-foot">spent by them, not yet provided back</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Team holds company money</span><span class="wap-kpi-ico"><i class="bi bi-wallet2"></i></span></div>
            <div class="wap-kpi-value">{{ $mny($staffHolds) }}</div>
            <div class="wap-kpi-foot">provided more than they spent</div>
        </div>
    </div>

    <details class="wap-card"><summary style="padding:14px;cursor:pointer;font-weight:700">Client, suppliers &amp; site team details</summary>
    {{-- ── client ledger ───────────────────────────────────────────────── --}}
    <div class="wap-card" id="wa-client" style="scroll-margin-top:80px">
        <div class="wap-card-head">
            <h3><i class="bi bi-person-check"></i> Client ledger{{ $st['client'] ? ' — ' . $st['client']->name : '' }}</h3>
            <span class="wap-card-sub">Debit = what he agreed to pay · Credit = what he paid · newest first</span>
        </div>
        <div class="wap-card-body">
            @if($cl->isEmpty())
                <div class="wap-empty"><i class="bi bi-inbox"></i><div class="wap-empty-title">No contract value or installments yet</div><div class="wap-empty-sub">Set them on <a href="{{ $sheetUrl }}">Entries &amp; posting</a>.</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Date</th><th>Entry</th><th class="wap-t-num">Debit</th><th class="wap-t-num">Credit</th><th class="wap-t-num">Balance (he owes)</th><th>Books</th></tr></thead>
                <tbody>
                @foreach($cl->reverse()->values() as $r)
                <tr>
                    <td style="white-space:nowrap">{{ $r->date ? \Carbon\Carbon::parse($r->date)->format('d/m/Y') : '—' }}</td>
                    <td><span class="wap-t-strong">{{ $r->what }}</span>@if($r->sub) <span class="wap-hint">· {{ $r->sub }}</span>@endif</td>
                    <td class="wap-t-num">@if($r->dr > 0)<span style="color:#dc2626; font-weight:600">{{ number_format($r->dr) }}</span>@else<span style="color:#cbd5e1">—</span>@endif</td>
                    <td class="wap-t-num">@if($r->cr > 0)<span style="color:#16a34a; font-weight:600">{{ number_format($r->cr) }}</span>@else<span style="color:#cbd5e1">—</span>@endif</td>
                    <td class="wap-t-num wap-t-strong" style="color: {{ $r->bal > 0.004 ? '#1d4ed8' : '#16a34a' }}">{{ $drcr($r->bal) }}</td>
                    <td>@if($r->books === 'in books' || $r->books === 'billed')<span class="wap-badge">In books</span>@elseif($r->books === 'reversed')<span class="wap-badge wap-badge-warn">Reversed</span>@elseif($r->books !== '')<span class="wap-badge wap-badge-warn">Not in books</span>@endif</td>
                </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td colspan="2" class="wap-t-strong" style="text-align:right">Total · he still owes</td>
                    <td class="wap-t-num wap-t-strong" style="color:#dc2626">{{ number_format($cl->sum('dr')) }}</td>
                    <td class="wap-t-num wap-t-strong" style="color:#16a34a">{{ number_format($cl->sum('cr')) }}</td>
                    <td class="wap-t-num wap-t-strong {{ ($st['clientDue'] ?? 0) > 0 ? 'wap-t-bad' : '' }}">{{ $st['clientDue'] !== null ? $mny($st['clientDue']) : '—' }}</td><td></td>
                </tr></tfoot>
            </table></div>
            <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap">
                <a class="wap-btn wap-btn-ghost" href="{{ $sheetUrl }}"><i class="bi bi-plus-lg"></i> Add installment / post</a>
                @if($st['client'])<a class="wap-btn wap-btn-ghost" href="{{ $PC::route('party', ['party' => $st['client']->id]) }}"><i class="bi bi-journal-text"></i> Client statement</a>@endif
            </div>
            @endif
        </div>
    </div>

    {{-- ── suppliers ───────────────────────────────────────────────────── --}}
    <div class="wap-card" id="wa-suppliers" style="scroll-margin-top:80px">
        <div class="wap-card-head">
            <h3><i class="bi bi-truck"></i> Suppliers &amp; contractors</h3>
            <span class="wap-card-sub">Bought · paid · still owed — open one for his full statement</span>
        </div>
        <div class="wap-card-body">
            @if($st['suppliers']->isEmpty())
                <div class="wap-empty"><i class="bi bi-truck"></i><div class="wap-empty-title">No supplier entries yet</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Supplier</th><th class="wap-t-num">Bought</th><th class="wap-t-num">Paid</th><th class="wap-t-num">Still owed</th><th>Last entry</th><th></th></tr></thead>
                <tbody>
                @foreach($st['suppliers'] as $sp)
                <tr>
                    <td class="wap-t-strong">{{ $sp['party'] }}</td>
                    <td class="wap-t-num">{{ $mny($sp['bill']) }}</td>
                    <td class="wap-t-num">{{ $mny($sp['paid']) }}</td>
                    <td class="wap-t-num wap-t-strong {{ $sp['due'] > 0.004 ? 'wap-t-bad' : '' }}">{{ $sp['due'] > 0.004 ? $mny($sp['due']) : 'Settled' }}</td>
                    <td>{{ $sp['last'] ? \Carbon\Carbon::parse($sp['last'])->format('d M Y') : '—' }}</td>
                    <td>@if($sp['party_id'] && $vendorStatement($sp['party_id']))<a class="wap-proj-act" href="{{ $vendorStatement($sp['party_id']) }}" aria-label="Open {{ $sp['party'] }}'s statement" title="Open statement"><i class="bi bi-arrow-right"></i></a>@endif</td>
                </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <td class="wap-t-strong" style="text-align:right">Total</td>
                    <td class="wap-t-num wap-t-strong">{{ $mny($st['suppliers']->sum('bill')) }}</td>
                    <td class="wap-t-num wap-t-strong">{{ $mny($st['suppliers']->sum('paid')) }}</td>
                    <td class="wap-t-num wap-t-strong {{ $st['supplierDue'] > 0 ? 'wap-t-bad' : '' }}">{{ $mny($st['supplierDue']) }}</td><td colspan="2"></td>
                </tr></tfoot>
            </table></div>
            <div class="wap-hint" style="margin-top:8px">Contractors are listed transfer by transfer; each transfer is entered as the work paid for at that moment, so their balance stays at zero.</div>
            @endif
        </div>
    </div>

    {{-- ── site team ───────────────────────────────────────────────────── --}}
    <div class="wap-card" id="wa-team" style="scroll-margin-top:80px">
        <div class="wap-card-head">
            <h3><i class="bi bi-people"></i> Site team — admin &amp; site engineers</h3>
            <span class="wap-card-sub">Spent = cost entries filed in his name · Provided = funds sent to him, or paid straight to a supplier for him · Balance = spent − provided</span>
        </div>
        <div class="wap-card-body">
            @if($team->isEmpty() && $direct < 0.5)
                <div class="wap-empty"><i class="bi bi-people"></i><div class="wap-empty-title">No cost entries yet</div></div>
            @else
            <div class="wap-table-wrap"><table class="wap-table">
                <thead><tr><th>Person</th><th class="wap-t-num">Entries</th><th class="wap-t-num">Spent</th><th class="wap-t-num">Provided</th><th class="wap-t-num">Balance</th><th>What it means</th><th></th></tr></thead>
                <tbody>
                @foreach($team as $p)
                <tr>
                    <td class="wap-t-strong"><a href="{{ $PC::route('project.person', ['role' => $role, 'department' => $dept->id, 'user' => $p->user_id]) }}">{{ $p->name }}</a></td>
                    <td class="wap-t-num">{{ $p->n }}</td>
                    <td class="wap-t-num">{{ $mny($p->spent) }}@if($p->pending > 0.004)<div class="wap-hint" style="margin:0">{{ $mny($p->pending) }} waiting</div>@endif</td>
                    <td class="wap-t-num">{{ $p->provided > 0.004 ? $mny($p->provided) : '—' }}</td>
                    <td class="wap-t-num wap-t-strong {{ $p->balance > 0.004 ? 'wap-t-bad' : '' }}">{{ $mny(abs($p->balance)) }}</td>
                    <td>@if($p->balance > 0.004)<span class="wap-badge wap-badge-warn">Company owes him</span>@elseif($p->balance < -0.004)<span class="wap-badge">Holds company money</span>@else<span class="wap-badge">Settled</span>@endif</td>
                    <td><a class="wap-proj-act" href="{{ $PC::route('project.person', ['role' => $role, 'department' => $dept->id, 'user' => $p->user_id]) }}" aria-label="Open {{ $p->name }}'s account"><i class="bi bi-arrow-right"></i></a></td>
                </tr>
                @endforeach
                @if($direct >= 0.5)
                <tr><td class="wap-t-strong">Paid directly by the company</td><td class="wap-t-num">—</td><td class="wap-t-num">{{ $mny($direct) }}</td><td class="wap-t-num">—</td><td class="wap-t-num">—</td><td><span class="wap-badge">From company bank / cash</span></td><td></td></tr>
                @endif
                </tbody>
                <tfoot><tr>
                    <td class="wap-t-strong" style="text-align:right" colspan="2">Total</td>
                    <td class="wap-t-num wap-t-strong">{{ $mny($team->sum('spent') + $direct) }}</td>
                    <td class="wap-t-num wap-t-strong">{{ $mny($team->sum('provided')) }}</td>
                    <td colspan="3"></td>
                </tr></tfoot>
            </table></div>
            @endif

            @if($canEdit && $team->isNotEmpty())
            <div style="margin-top:16px; padding-top:14px; border-top:1px solid #e2e8f0">
                @if($team->where('balance', '>', 0.004)->isNotEmpty())
                <form method="POST" action="{{ $post('project.fund.cover') }}" style="margin-bottom:16px; padding:12px 14px; background:#f0fdfa; border:1px solid #99f6e4; border-radius:12px; display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap">
                    @csrf
                    <div><b>The company already gave them the money?</b><br><span class="wap-hint" style="margin:0">One click records, for each person, funds provided = what he spent ({{ $mny($team->where('balance', '>', 0.004)->sum('balance')) }}), so every balance becomes zero. Correct any person later with an ordinary entry.</span></div>
                    <button type="submit" class="wap-btn wap-btn-primary" onclick="return confirm('Record funds provided = what each person spent? You can still correct or reverse any of them.')"><i class="bi bi-magic"></i> Mark the team as fully funded</button>
                </form>
                @endif
                <div class="wap-t-strong" style="margin-bottom:8px">Record funds provided to a person</div>
                <form method="POST" action="{{ $post('project.fund') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end">
                    @csrf
                    <div class="wap-field"><label class="wap-label" for="wa-f-who">Person</label>
                        <select id="wa-f-who" name="person_id" required class="wap-input">@foreach($team as $p)<option value="{{ $p->user_id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <div class="wap-field"><label class="wap-label" for="wa-f-date">Date</label><input id="wa-f-date" name="entry_date" type="date" required class="wap-input"></div>
                    <div class="wap-field"><label class="wap-label" for="wa-f-amt">Amount (৳)</label><input id="wa-f-amt" name="amount" type="number" step="1" min="1" required class="wap-input"></div>
                    <div class="wap-field" style="min-width:230px"><label class="wap-label" for="wa-f-from">Provided by</label><input id="wa-f-from" name="label" maxlength="160" class="wap-input" placeholder="e.g. Epal Travels IBBL"></div>
                    <div class="wap-field" style="min-width:230px"><label class="wap-label" for="wa-f-note">Note</label><input id="wa-f-note" name="note" maxlength="500" class="wap-input" placeholder="e.g. paid to Haji's account for him"></div>
                    <button type="submit" class="wap-btn wap-btn-primary"><i class="bi bi-plus-lg"></i> Add</button>
                </form>
                <form method="POST" action="{{ $post('project.fund') }}" style="margin-top:12px">
                    @csrf
                    <label class="wap-label" for="wa-f-bulk">…or paste many lines from Excel: date · person · amount · provided by · note</label>
                    <textarea id="wa-f-bulk" name="bulk" rows="4" class="wap-input" style="width:100%; box-sizing:border-box" placeholder="05.04.2026&#9;Rony&#9;14000&#9;EMI IBBL&#9;to Rony's IBBL account"></textarea>
                    <button type="submit" class="wap-btn wap-btn-ghost" style="margin-top:8px"><i class="bi bi-clipboard-plus"></i> Add pasted lines</button>
                </form>
                @if($unpostedFunds > 0)
                <form method="POST" action="{{ $post('project.fund.post') }}" style="margin-top:12px">
                    @csrf
                    <button type="submit" class="wap-btn wap-btn-primary" title="Dr Employee payable (his account) / Cr Inter-company — no bank is touched"><i class="bi bi-journal-check"></i> Post {{ $unpostedFunds }} funds {{ $unpostedFunds === 1 ? 'entry' : 'entries' }} to the books</button>
                    <span class="wap-hint">Reduces what the company owes him; no bank moves.</span>
                </form>
                @endif
            </div>
            @endif
        </div>
    </div>

    </details>
    <details class="wap-card"><summary style="padding:14px;cursor:pointer;font-weight:700">Cost breakdown</summary>
    {{-- ── cost by head ────────────────────────────────────────────────── --}}
    <span id="wa-budget" style="scroll-margin-top:80px"></span>
    @if($heads->isNotEmpty())
    <div class="wap-card">
        <div class="wap-card-head"><h3><i class="bi bi-pie-chart"></i> Cost by head</h3><span class="wap-card-sub">every entry, confirmed or waiting · the full sheet is on Entries &amp; posting</span></div>
        <div class="wap-card-body" style="display:grid; gap:9px">
            @foreach($heads as $h)
            <div style="display:grid; grid-template-columns: minmax(130px, 220px) 1fr 110px; gap:12px; align-items:center">
                <span>{{ $h['name'] }}</span>
                <div style="height:10px; background:#e2e8f0; border-radius:6px; overflow:hidden"><div style="height:100%; width: {{ round($h['total'] / $maxHead * 100) }}%; background:#0f766e"></div></div>
                <span class="wap-t-num wap-t-strong" style="text-align:right">{{ $mny($h['total']) }}@if($h['waiting'] > 0.004)<span class="wap-hint" style="display:block; margin:0">{{ $mny($h['waiting']) }} waiting</span>@endif</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    </details>
    @endif
@endsection
