{{-- The Flight Dashboard's banner and card wall in the file module.
     TWO MODES (owner, 2026-10-25): "all will be file / passport wise, not agent
     wise" + "a button to see modes, agent wise or passport / file wise; by
     default file / passport wise". #cf-dashboard-overview[data-mode] shows one set:
       file  — one card per FILE, as the flight has one per flight, and it OPENS
               ON HOVER like the flight card ("like the flights, hovering should
               open details version"): the resting face is the destination flag,
               country · category, the applicant, the expected date and the file
               number; the open face adds the stage, the agent, passport → country,
               the countdown and a Docs / Client due / Vendor due strip. The
               flight's card-view button cycles hover · always compact · all open.
               A click opens the file record (a[data-file-sheet]).
       agent — one card per agent / client group, its due as the figure; a
               click opens that agent's file ledger below.
     The mode is not remembered (every load opens file wise); the card view is,
     per browser, as the flight's is. --}}
<div class="fd-banner">
 <div class="fd-summary-strip">
  <div class="fd-stats">
   @foreach([['Active Files',$summaryFiles->count(),null],['Applicants',$summaryFiles->pluck('passport_holder_id')->filter()->unique()->count(),null],['Countries',$summaryFiles->pluck('country_id')->filter()->unique()->count(),'file'],['Agent / Client Groups',$cardGroups->count(),'agent'],['Vendor Due','৳'.number_format($summaryFiles->sum('total_vendor_due')),null],['Vendors',$summaryFiles->pluck('vendor_id')->filter()->unique()->count(),null]] as [$label,$value,$mode])
   <button type="button" class="fd-stat fd-stat-link" data-cfd-scroll @if($mode) data-cfd-mode="{{ $mode }}" @endif aria-label="View {{ strtolower($label) }} in the table"><span class="val">{{ $value }}</span><span class="lbl">{{ $label }}</span></button>
   @endforeach
  </div>
  <div class="fd-summary-actions">
   <button type="button" id="cfdHoverBtn" class="fd-tool" data-cfd-mode="file" title="Change card view" aria-label="Change card view"><i class="fas fa-expand" id="cfdHoverIcon"></i></button>
   <span id="cfdTableCount"><span data-cfd-mode="file">{{ $summaryFiles->count() }} of {{ $dashboardFiles->count() }} files</span><span data-cfd-mode="agent">{{ $cardGroups->count() }} {{ $cardGroups->count() === 1 ? 'agent' : 'agents' }} · {{ $dashboardFiles->count() }} files</span></span>
   {{-- Count, then the switch beside Add File; Agent wise first (owner, 2026-10-25: "swipe place", "change place"). --}}
   <div class="cfd-mode-switch" role="group" aria-label="Show the dashboard by">
    <button type="button" data-cfd-mode-btn="agent" aria-pressed="false" title="One card and one row per agent / client"><i class="fas fa-user-tie" aria-hidden="true"></i> Agent wise</button>
    <button type="button" data-cfd-mode-btn="file" aria-pressed="true" title="One card and one row per file / passport"><i class="fas fa-passport" aria-hidden="true"></i> File wise</button>
   </div>
   @can('create contract file')<button type="button" class="fd-add-flight create-btn"><i class="fas fa-plus"></i> Add File</button>@endcan
  </div>
 </div>
 @include('contract-files.dashboard-filter')
</div>

{{-- File wise --}}
<div id="cfdGrid" class="cfd-grid" data-cfd-mode="file" aria-label="Active files">
@forelse($cardFiles as $file)
 @php
 $cardFlag = $file->country?->code ? asset('flags/'.strtolower($file->country->code).'.png') : null;
 $cardWhere = collect([$file->country?->name, $file->category?->name])->filter()->implode(' · ') ?: 'Country pending';
 $cardInitials = collect(preg_split('/\s+/', trim(preg_replace('/^(mr|mrs|ms|md)\.?\s+/i', '', (string) $file->applicant_name))))->filter()->take(2)->map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)))->implode('');
 $cardDone = in_array($file->status, ['approved','passport_ready','delivered','case_closed'], true);
 $cardLate = $file->expected_out_date && $file->expected_out_date->lt(now()->startOfDay()) && ! $cardDone;
 // Rail = the stage, as the flight card's rail is its status: amber processing, blue approved, red flagged.
 $cardInk = $file->exception_flag ? ['#dc2626','220, 38, 38'] : (in_array($file->status, ['approved','passport_ready'], true) ? ['#2563eb','37, 99, 235'] : ['#d97706','217, 119, 6']);
 $cardFlagLabel = $file->exception_flag ? (\App\Models\ContractFile::EXCEPTION_FLAGS[$file->exception_flag] ?? $file->exception_flag) : null;
 // The flight's countdown pill (depState), read against the EXPECTED date.
 $cardDays = $file->expected_out_date ? (int) now()->startOfDay()->diffInDays($file->expected_out_date->copy()->startOfDay(), false) : null;
 $cardFlow = match (true) {
  $cardDays === null => ['idle', 'no date'],
  $cardDays < 0 => [$cardDone ? 'idle' : 'late', (-$cardDays).'d '.($cardDone ? 'ago' : 'late')],
  $cardDays === 0 => ['today', 'today'],
  $cardDays === 1 => ['soon', 'tomorrow'],
  $cardDays <= 7 => ['soon', 'in '.$cardDays.'d'],
  default => ['idle', 'in '.$cardDays.'d'],
 };
 $cardShort = function ($v) { $n = abs((float) $v); return $n >= 1e7 ? number_format($n / 1e7, $n >= 1e8 ? 0 : 1).'Cr' : ($n >= 1e5 ? number_format($n / 1e5, $n >= 1e6 ? 0 : 1).'L' : number_format($n)); };
 $cardDocsDone = $file->documents_total > 0 && $file->documents_received >= $file->documents_total;
 $cardMark = '<span class="cfd-mark'.($cardFlag ? ' has-flag' : '').'">'.($cardFlag ? '<img src="'.e($cardFlag).'" alt="" loading="lazy" onerror="this.parentNode.classList.remove(\'has-flag\');this.remove()">' : '').'<b>'.e($cardInitials ?: '—').'</b></span>';
 @endphp
 <a class="cfd-card{{ $cardLate ? ' is-late' : '' }}" href="{{ route('role.contract-files.show',['role'=>$role,'contractFile'=>$file]) }}" data-file-sheet="{{ $file->id }}"
    style="--fdc-co:{{ $cardInk[0] }};--fdc-co-rgb:{{ $cardInk[1] }};"
    title="{{ $file->file_number }} · {{ $file->applicant_name }} · {{ $file->status_label }}{{ $cardFlagLabel ? ' · '.$cardFlagLabel : '' }} · {{ $cardWhere }} · agent {{ $file->client?->name ?? 'Direct' }} · open the file record">
  <span class="fdc-brief"><span class="fdc-brief-in"><span class="cfd-brief-row">
   {!! $cardMark !!}
   <span class="fb-id">
    <span class="fb-co">{{ $cardWhere }}</span>
    <span class="fb-name">{{ $file->applicant_name ?: 'Applicant pending' }}</span>
    <span class="fb-bal">@if($file->expected_out_date){{ $file->expected_out_date->format('j M') }}<span class="cur">{{ $file->expected_out_date->isCurrentYear() ? 'EXPECTED' : $file->expected_out_date->format('Y') }}</span>@else<span class="fb-none">No expected date</span>@endif</span>
   </span>
   <span class="fb-cf">{{ $file->file_number }}</span>
  </span></span></span>
  <span class="fdc-full"><span class="fdc-full-in">
   <span class="fdc-head">
    {!! $cardMark !!}
    <span class="fdc-id">
     <span class="name">@if($file->exception_flag || ! $cardDocsDone)<span class="fdc-dot" title="{{ $cardFlagLabel ?? 'Documents still missing' }}"></span>@endif{{ $file->applicant_name ?: 'Applicant pending' }}</span>
     <span class="sub"><span class="cf">{{ $file->file_number }}</span> · {{ $cardFlagLabel ?? $file->status_label }}</span>
     @if($file->client)<span class="agent" title="agent: {{ $file->client->name }}"><i class="fas fa-user-tie"></i>{{ $file->client->name }}</span>@else<span class="agent is-none" title="no agent on this file"><i class="fas fa-user-tie"></i>Direct — no agent</span>@endif
    </span>
   </span>
   <span class="fdc-route" title="Passport {{ $file->passport_no ?? 'not entered' }} → {{ $file->country?->name ?? 'country pending' }}">
    <span class="ap"><i class="fas fa-passport"></i>{{ $file->passport_no ?? 'No passport' }}</span>
    <span class="lead"></span><i class="fas fa-plane pl"></i><span class="lead"></span>
    <span class="ap">{{ $file->country?->code ?? '—' }}@if($cardFlag)<img class="fdc-flag" src="{{ $cardFlag }}" alt="" loading="lazy" onerror="this.remove()">@endif</span>
    @if($file->category)<span class="cls">{{ $file->category->name }}</span>@endif
   </span>
   <span class="fdc-dep" title="{{ $file->expected_out_date ? 'Expected '.$file->expected_out_date->format('l, d M Y') : 'No expected date set' }}{{ $file->submit_date ? ' · submitted '.$file->submit_date->format('d M Y') : '' }}">
    <span class="money"><span class="amt">{{ $file->expected_out_date ? $file->expected_out_date->format('j M') : '—' }}</span><span class="cur">{{ $file->expected_out_date ? ($file->expected_out_date->isCurrentYear() ? 'EXPECTED' : $file->expected_out_date->format('Y')) : 'NO DATE' }}</span></span>
    <span class="fdc-flow {{ $cardFlow[0] }}">{{ $cardFlow[1] }}</span>
   </span>
   <span class="fdc-strip">
    <span class="cell {{ $file->documents_total ? ($cardDocsDone ? 'ok' : 'warn') : 'is-zero' }}" title="{{ $file->documents_received }} of {{ $file->documents_total }} required documents received"><span class="cv">{{ $file->documents_received }}/{{ $file->documents_total }}</span><span class="cl">Docs</span></span>
    <span class="cell {{ $file->client_due > 0 ? 'bad' : 'is-zero' }}" title="{{ $file->client_due > 0 ? 'client still owes ৳'.number_format($file->client_due) : 'client has paid' }}"><span class="cv">৳{{ $cardShort($file->client_due) }}</span><span class="cl">Client due</span></span>
    <span class="cell {{ $file->total_vendor_due > 0 ? 'bad' : 'is-zero' }}" title="{{ $file->total_vendor_due > 0 ? 'vendor is owed ৳'.number_format($file->total_vendor_due) : 'nothing owed to the vendor' }}"><span class="cv">৳{{ $cardShort($file->total_vendor_due) }}</span><span class="cl">Vendor due</span></span>
   </span>
  </span></span>
 </a>
@empty<div class="fd-empty"><i class="fas fa-folder-open"></i>No active files match these filters.</div>@endforelse
</div>

{{-- Agent wise --}}
<div id="cfdGroupGrid" class="cfd-grid" data-cfd-mode="agent" aria-label="Agent and client file groups">
@forelse($cardGroups as $group)
 @php
 $groupKey=$group['client_id'] ?? 'unassigned';
 $cardUrl=$group['client_id'] ? route('role.contract-files.agent-files',['role'=>$role,'client'=>$group['client_id']]) : route('role.contract-files.index',['role'=>$role,'unassigned'=>1]);
 $cardCountries=$group['files']->pluck('country.name')->filter()->unique()->implode(' · ') ?: 'Country pending';
 $cardInitials=$group['client_id'] ? collect(preg_split('/\s+/', trim(preg_replace('/^(mr|mrs|ms|md)\.?\s+/i', '', $group['name']))))->filter()->take(2)->map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)))->implode('') : '';
 // Rail = amber while files are open, green once every file is approved.
 $cardInk=$group['approved']>=$group['total'] ? ['#059669','5, 150, 105'] : ['#d97706','217, 119, 6'];
 @endphp
 <a class="cfd-card{{ $group['due']>0 ? ' is-late' : '' }}" href="{{ $cardUrl }}" data-file-card="{{ $groupKey }}" aria-pressed="false"
    style="--fdc-co:{{ $cardInk[0] }};--fdc-co-rgb:{{ $cardInk[1] }};"
    title="{{ $group['name'] }} · {{ $group['total'] }} files · {{ $group['in_process'] }} in progress · {{ $group['approved'] }} approved · due ৳{{ number_format($group['due']) }} · {{ $cardCountries }}">
  <span class="cfd-brief-row">
   <span class="cfd-mark">@if($cardInitials !== ''){{ $cardInitials }}@else<i class="fas fa-user-tie" aria-hidden="true"></i>@endif</span>
   <span class="fb-id">
    <span class="fb-co">{{ $cardCountries }}</span>
    <span class="fb-name">{{ $group['name'] }}</span>
    <span class="fb-bal">৳{{ number_format($group['due']) }}<span class="cur">DUE</span></span>
   </span>
   <span class="fb-cf">{{ $group['total'] }} {{ $group['total'] === 1 ? 'file' : 'files' }}</span>
  </span>
 </a>
@empty<div class="fd-empty"><i class="fas fa-folder-open"></i>No agent or client groups match these filters.</div>@endforelse
</div>
<style>
/* One mode at a time. */
#cf-dashboard-overview[data-mode="file"] [data-cfd-mode="agent"],
#cf-dashboard-overview[data-mode="agent"] [data-cfd-mode="file"] { display: none !important; }
/* The switch: the flight's 26px summary tools, two halves; the mode in use is white. */
.cfd-mode-switch { display: inline-flex; height: 26px; border: 1px solid #ffffff40; border-radius: 7px; overflow: hidden; background: #ffffff18; }
.cfd-mode-switch button { display: inline-flex; align-items: center; gap: 5px; padding: 0 9px; border: 0; background: transparent; color: #fff; font-size: 11px; font-weight: 700; white-space: nowrap; cursor: pointer; transition: background .15s ease, color .15s ease; }
.cfd-mode-switch button + button { border-left: 1px solid #ffffff40; }
.cfd-mode-switch button i { font-size: 10px; }
.cfd-mode-switch button:hover { background: #ffffff30; }
.cfd-mode-switch button[aria-pressed="true"] { background: #fff; color: #4338ca; }
.cfd-mode-switch button:focus-visible { outline: 2px solid #fff; outline-offset: -2px; }

/* ── The card wall ─────────────────────────────────────────────────────
   #fdGrid and .fd-card from contract-flights/dashboard.blade.php: rows are a
   FIXED 85px — one resting card — and on hover a FILE card claims a second
   row (grid-row: span 2), the bottom row opening upwards into the cell above
   (.fdc-up, placed by markFileLastRow()). Agent cards stay one row. */
.cfd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(196px, 1fr)); gap: 0.5rem; align-items: start; grid-auto-rows: 85px; container-type: inline-size; }
#cfdGrid.all-expanded { grid-auto-rows: auto; }
/* The flight's five-across squeeze (grid 1012–1109px): tighter paddings and a 13px name. */
@container (min-width: 1012px) and (max-width: 1109px) {
    .cfd-grid .cfd-card { padding-left: 12px; padding-right: 6px; }
    #cfdGrid .fdc-full-in { margin: 0 -6px 0 -12px; padding: 0 6px 0 12px; }
    .cfd-grid .cfd-brief-row { gap: 8px; }
    .cfd-grid .fb-name { font-size: 13px; }
}
.cfd-card {
    --fdc-co: #64748b; --fdc-co-rgb: 100, 116, 139; --fdc-ease: cubic-bezier(.22, 1, .36, 1);
    position: relative; overflow: hidden; display: block; box-sizing: border-box; text-decoration: none;
    border: 1px solid rgba(15,23,42,0.20); border-radius: 14px;
    background: radial-gradient(135% 95% at 100% 0%, rgba(var(--fdc-co-rgb), 0.09), transparent 56%), #fff;
    padding: 5px 9px 0 15px;
    box-shadow: 0 1px 2px rgba(7,12,30,0.06), 0 3px 10px -5px rgba(7,12,30,0.10);
    cursor: pointer; transform-origin: center top;
    transition: transform .3s var(--fdc-ease), box-shadow .3s var(--fdc-ease), border-color .24s ease;
}
.cfd-card:hover { text-decoration: none; }
.cfd-card.is-late { border-color: rgba(190,18,60,0.35); }
.cfd-card[aria-pressed="true"] { outline: 2px solid #6366f1; outline-offset: 2px; }
.cfd-card:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
.cfd-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; z-index: 2;
    background: linear-gradient(180deg, var(--fdc-co) 62%, rgba(var(--fdc-co-rgb), 0.55)); transition: width .28s cubic-bezier(.2,.7,.2,1); }
.cfd-card:hover::before { width: 7px; }
/* Agent cards: one row, a lift on hover. */
#cfdGroupGrid .cfd-card { height: 85px; }
#cfdGroupGrid .cfd-card:hover { transform: translateY(-2px); border-color: rgba(var(--fdc-co-rgb), 0.45); box-shadow: 0 2px 4px rgba(7,12,30,0.06), 0 10px 22px -10px rgba(var(--fdc-co-rgb), 0.45); }

/* File cards: the flight card's open-on-hover, rule for rule. */
#cfdGrid:not(.no-hover-fx):not(.all-expanded) .cfd-card:hover {
    grid-row: span 2; z-index: 5; transform: scale(1.03);
    border-color: rgba(var(--fdc-co-rgb), 0.5);
    box-shadow: 0 20px 42px -24px rgba(var(--fdc-co-rgb), 0.5), 0 6px 16px -10px rgba(7,12,30,0.18);
}
#cfdGrid .cfd-card.fdc-up { align-self: end; transform-origin: center bottom; }
#cfdGrid:not(.no-hover-fx):not(.all-expanded) .cfd-card.fdc-up:hover { grid-column: var(--fdc-col); grid-row: var(--fdc-row) / span 2; }
/* One card at a time: while one is open its neighbours cannot take the hover. */
#cfdGrid:not(.no-hover-fx):not(.all-expanded):has(.cfd-card:hover) .cfd-card:not(:hover) { pointer-events: none; }
#cfdGrid.no-hover-fx .cfd-card:hover::before, #cfdGrid.all-expanded .cfd-card:hover::before { width: 5px; }
#cfdGrid.all-expanded .cfd-card .fdc-brief { grid-template-rows: 0fr; opacity: 0; }
#cfdGrid.all-expanded .cfd-card .fdc-full { grid-template-rows: 1fr; opacity: 1; }
#cfdGrid.all-expanded .cfd-card .fdc-full-in { transform: scale(1); }
#cfdGrid .fdc-brief, #cfdGrid .fdc-full { display: grid; grid-template-rows: 1fr; transition: grid-template-rows .26s var(--fdc-ease), opacity .18s ease; }
#cfdGrid .fdc-brief-in, #cfdGrid .fdc-full-in { display: block; overflow: hidden; min-height: 0; }
#cfdGrid .fdc-brief { opacity: 1; }
#cfdGrid:not(.no-hover-fx):not(.all-expanded) .cfd-card:hover .fdc-brief { grid-template-rows: 0fr; opacity: 0; transition-duration: .4s, .12s; }
#cfdGrid .fdc-full { grid-template-rows: 0fr; opacity: 0; }
#cfdGrid:not(.no-hover-fx):not(.all-expanded) .cfd-card:hover .fdc-full { grid-template-rows: 1fr; opacity: 1; transition-duration: .4s, .26s; transition-delay: 0s, .1s; }
#cfdGrid .fdc-full-in { transform: scale(.965); transform-origin: center top; transition: transform .4s var(--fdc-ease); margin: 0 -9px 0 -15px; padding: 0 9px 0 15px; }
#cfdGrid:not(.no-hover-fx):not(.all-expanded) .cfd-card:hover .fdc-full-in { transform: scale(1); }
/* The glaze that sweeps the face once on hover. */
#cfdGrid .cfd-card::after { content: ''; position: absolute; top: 0; left: -75%; width: 62%; height: 100%; z-index: 3; pointer-events: none; transform: skewX(-18deg);
    background: linear-gradient(105deg, transparent 12%, rgba(255,255,255,0.30) 42%, rgba(255,255,255,0.58) 50%, rgba(255,255,255,0.30) 58%, transparent 88%);
    transition: left .7s ease; }
#cfdGrid:not(.no-hover-fx):not(.all-expanded) .cfd-card:hover::after { left: 145%; }

/* The resting face. */
.cfd-brief-row { position: relative; display: flex; align-items: flex-start; gap: 10px; padding: 7px 0 5px; }
/* The mark: a file card's destination flag (where the flight card has the
   airline's logo), else the flight's monogram plate with initials. */
.cfd-mark { position: relative; flex: 0 0 44px; height: 44px; margin-top: 2px; display: flex; align-items: center; justify-content: center; border-radius: 11px;
    background: #6366f1; color: #fff; font-size: 15px; font-weight: 800; letter-spacing: 0.01em; }
.cfd-mark i { font-size: 17px; }
.cfd-mark.has-flag { background: transparent; }
.cfd-mark.has-flag b { display: none; }
.cfd-mark img { width: 42px; height: 28px; object-fit: cover; border-radius: 5px; box-shadow: 0 0 0 1px rgba(15,23,42,0.12), 0 2px 6px rgba(15,23,42,0.12); }
.cfd-card .fb-id { flex: 1 1 120px; min-width: 0; min-height: 66px; }
.cfd-card .fb-co { display: block; font-size: 9.5px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: #6b7686; line-height: 12px;
    white-space: normal; max-height: 24px; overflow: hidden; }
.cfd-card .fb-co::before { content: ''; float: right; width: 60px; height: 12px; }
.cfd-card .fb-name { display: block; font-size: 13.8px; font-weight: 700; letter-spacing: -0.015em; color: #1a2333; line-height: 17px; margin-top: 1px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cfd-card .fb-bal { display: block; font-size: 16px; font-weight: 700; letter-spacing: -0.025em; color: #111827; line-height: 20px; margin-top: 2px;
    font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: clip; }
.cfd-card .fb-bal .cur { font-size: 9px; font-weight: 700; letter-spacing: 0.05em; color: #8b95a5; margin-left: 5px; }
.cfd-card .fb-bal .fb-none { font-size: 12px; font-weight: 600; letter-spacing: 0; color: #94a3b8; }
.cfd-card.is-late .fb-bal { color: #dc2626; }
.cfd-card .fb-cf { position: absolute; top: 6px; right: 0; padding: 0 5px; border-radius: 5px;
    font: 700 10px/14px ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: 0;
    color: #2563eb; background: rgba(37, 99, 235, .08); white-space: nowrap; }

/* The open face — the flight card's four bands. */
#cfdGrid .fdc-head, #cfdGrid .fdc-route, #cfdGrid .fdc-dep, #cfdGrid .fdc-strip { position: relative; z-index: 1; }
#cfdGrid .fdc-head { display: flex; align-items: center; gap: 7px; padding-top: 7px; }
#cfdGrid .fdc-head .cfd-mark { margin-top: 0; }
#cfdGrid .fdc-id { display: block; flex: 1 1 115px; min-width: 0; }
#cfdGrid .cfd-card .name { display: block; font-size: 13.5px; font-weight: 700; color: #1a2333; line-height: 17px; letter-spacing: -0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#cfdGrid .cfd-card .sub { display: block; font-size: 11px; font-weight: 600; color: #5c6775; line-height: 13px; letter-spacing: 0.01em; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#cfdGrid .cfd-card .sub .cf { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #2563eb; letter-spacing: 0; }
#cfdGrid .cfd-card .agent { display: block; font-size: 11px; font-weight: 600; color: #334155; line-height: 13px; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#cfdGrid .cfd-card .agent i { font-size: 9px; color: #94a3b8; margin-right: 3px; }
#cfdGrid .cfd-card .agent.is-none { color: #94a3b8; font-weight: 500; }
#cfdGrid .fdc-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #ef4444; margin-right: 4px; vertical-align: 1px; }
#cfdGrid .fdc-route { display: flex; align-items: center; gap: 5px; margin-top: 8px; height: 13px; }
#cfdGrid .fdc-route .ap { display: inline-flex; align-items: center; gap: 4px; flex: 0 0 auto; line-height: 13px; font-size: 11px; font-weight: 700; color: #334155; letter-spacing: 0.04em; }
#cfdGrid .fdc-route .ap i { font-size: 9px; color: #94a3b8; }
#cfdGrid .fdc-route .lead { flex: 1 1 8px; min-width: 6px; height: 0; border-bottom: 1px dotted #c9d2df; }
#cfdGrid .fdc-route .pl { font-size: 9px; color: #94a3b8; flex: 0 0 auto; }
#cfdGrid .fdc-route .cls { font-size: 8px; font-weight: 700; letter-spacing: 0.08em; color: #b9c2cf; text-transform: uppercase; flex: 0 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60px; }
#cfdGrid .fdc-dep { display: flex; align-items: baseline; justify-content: space-between; gap: 6px; margin: 9px 0 10px; }
#cfdGrid .fdc-dep .money { flex: 0 0 auto; white-space: nowrap; }
#cfdGrid .fdc-dep .amt { font-size: 22.95px; font-weight: 700; line-height: 28.35px; color: #111827; letter-spacing: -0.03em; font-variant-numeric: tabular-nums; }
#cfdGrid .fdc-dep .cur { font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; color: #8b95a5; margin-left: 5px; }
#cfdGrid .cfd-card.is-late .fdc-dep .amt { color: #dc2626; }
#cfdGrid .fdc-flow { flex-shrink: 0; margin-left: auto; font-size: 8.5px; font-weight: 700; line-height: 14px; padding: 1px 6px; border-radius: 999px; white-space: nowrap; font-variant-numeric: tabular-nums; }
#cfdGrid .fdc-flow.today { background: #ecfdf5; color: #15803d; }
#cfdGrid .fdc-flow.soon  { background: #fffbeb; color: #b45309; }
#cfdGrid .fdc-flow.late  { background: #fef2f2; color: #b91c1c; }
#cfdGrid .fdc-flow.idle  { background: #f6f8fb; color: #7c8797; }
#cfdGrid .fdc-strip { display: grid; grid-template-columns: repeat(3, 1fr); margin: 0 -9px 0 -15px; border-top: 1px solid #eef1f6; background: linear-gradient(180deg, #fbfcfe, #f6f8fc); border-radius: 0 0 13px 13px; }
#cfdGrid .fdc-strip .cell { display: block; padding: 6px 5px 8px; text-align: center; border-left: 1px solid #eef1f6; min-width: 0; }
#cfdGrid .fdc-strip .cell:first-child { border-left: 0; text-align: left; padding-left: 15px; }
#cfdGrid .fdc-strip .cell:last-child { text-align: right; padding-right: 9px; }
#cfdGrid .fdc-strip .cv { display: block; font-size: 11px; font-weight: 700; line-height: 14px; color: #475569; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#cfdGrid .fdc-strip .cl { display: block; font-size: 7.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #6b7686; line-height: 11px; margin-top: 1px; }
#cfdGrid .fdc-strip .ok .cv { color: #15803d; }
#cfdGrid .fdc-strip .warn .cv { color: #b45309; }
#cfdGrid .fdc-strip .bad .cv { color: #b91c1c; }
#cfdGrid .fdc-strip .cell.is-zero .cv { color: #ccd3dd; }
.cfd-grid .fd-empty i { font-size: 1.8rem; }
@media (prefers-reduced-motion: reduce) {
    .cfd-card, .cfd-card::before, .cfd-card::after, #cfdGrid .fdc-brief, #cfdGrid .fdc-full { transition: none; }
    #cfdGrid:not(.no-hover-fx):not(.all-expanded) .cfd-card:hover, #cfdGroupGrid .cfd-card:hover { transform: none; }
    #cfdGrid:not(.no-hover-fx):not(.all-expanded) .cfd-card:hover::after { left: -75%; }
}
</style>
<script>
(() => {
 // The flight's card view (cycleFlightHoverMode): hover · off · all, remembered per browser.
 const grid = document.getElementById('cfdGrid'), btn = document.getElementById('cfdHoverBtn'), ico = document.getElementById('cfdHoverIcon');
 if (!grid || !btn) return;
 const MODES = ['hover', 'off', 'all'];
 const ICONS = { hover: 'fas fa-expand', off: 'fas fa-compress', all: 'fas fa-table-cells-large' };
 const LABELS = { hover: 'Cards expand on hover — click to keep them always compact', off: 'Cards always compact — click to expand every card at once', all: 'Every card expanded — click to go back to hover-to-expand' };
 let mode = 'hover';
 try { const saved = localStorage.getItem('fileCardHoverMode'); if (MODES.includes(saved)) mode = saved; } catch (e) {}
 // Which cards sit on the BOTTOM row and where — so a bottom card opens into the cell above
 // instead of inventing a row below (the flight's markLastRowCards, unchanged in substance).
 function markLastRow() {
  if (grid.classList.contains('all-expanded') || grid.offsetParent === null) return;
  const cards = Array.from(grid.querySelectorAll('.cfd-card'));
  if (!cards.length || grid.querySelector('.cfd-card:hover')) return;
  cards.forEach(c => c.classList.remove('fdc-up'));
  const uniq = v => [...new Set(v)].sort((a, b) => a - b);
  const rows = uniq(cards.map(c => c.offsetTop)), cols = uniq(cards.map(c => c.offsetLeft));
  if (rows.length < 2) return;
  const lastTop = rows[rows.length - 1];
  if (cards.filter(c => c.offsetTop === lastTop).length === cols.length) return;
  cards.forEach(c => {
   if (c.offsetTop !== lastTop) { c.style.removeProperty('--fdc-col'); c.style.removeProperty('--fdc-row'); return; }
   c.style.setProperty('--fdc-col', String(cols.indexOf(c.offsetLeft) + 1));
   c.style.setProperty('--fdc-row', String(rows.length - 1));
   c.classList.add('fdc-up');
  });
 }
 function paint() {
  ico.className = ICONS[mode];
  btn.classList.toggle('is-alt', mode !== 'hover');
  btn.title = LABELS[mode]; btn.setAttribute('aria-label', LABELS[mode]);
  grid.classList.toggle('no-hover-fx', mode === 'off');
  grid.classList.toggle('all-expanded', mode === 'all');
 }
 btn.addEventListener('click', () => { mode = MODES[(MODES.indexOf(mode) + 1) % MODES.length]; try { localStorage.setItem('fileCardHoverMode', mode); } catch (e) {} paint(); requestAnimationFrame(markLastRow); });
 paint();
 window.cfdMarkLastRow = markLastRow;   // the mode switch calls it when the file wall becomes visible
 const ready = () => requestAnimationFrame(markLastRow);
 document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', ready) : ready();
 window.addEventListener('load', ready);
 let timer; const later = () => { clearTimeout(timer); timer = setTimeout(markLastRow, 120); };
 window.addEventListener('resize', later);
 // The page can lay the wall out after load (the Travels loader holds the content back), and a
 // pass skipped under a hovering pointer must be made up: re-measure whenever the wall changes
 // size — including hidden → shown — and when the pointer leaves it.
 if (window.ResizeObserver) new ResizeObserver(later).observe(grid);
 grid.addEventListener('mouseleave', later);
})();
</script>
