{{--
    Wood Art · Projects — extends the suite shell (layouts/suite.blade.php).

    Copy is verbatim from the reference module
    (companies/woodart/modules/projects/view.js). Unlike the other nine modules
    this one has no authored template.html — the reference still ships it as the
    legacy 1,238-line view.js — so the Active screen is transcribed from the
    code that builds it: KPIs at view.js:648-659, project cards at 686-706, and
    the empty state at 664-666.

    THIS IS THE FIRST SCREEN WITH REAL DATA. It reads `wa_projects`, a table no
    other company touches. When that table is absent — a server that has not run
    this module's migration — the controller hands back an empty collection and
    the screen falls back to the empty state below rather than erroring.

    Three of the four sections read real rows — Active Projects (the portfolio),
    Design Studio (the stage board) and Gallery (the portfolio wall). Milestones
    & Billing still shows a placeholder: it needs invoices and ledger entries,
    which this module does not hold.
--}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.estimates', ['role' => request()->route('role'), 'section' => 'quotations']) }}">
        <i class="bi bi-calculator-fill"></i> Estimates</a>
    <a class="wap-btn wap-btn-primary"
       href="{{ route('role.woodart.projects.create', ['role' => request()->route('role')]) }}">
        <i class="bi bi-plus-lg"></i> New Project</a>
@endsection

@section('wa-view')

    @if($section === 'active')
        @if(session('wa_status'))
        <div class="wap-flash"><i class="bi bi-check-circle-fill"></i> {{ session('wa_status') }}</div>
        @endif

        {{-- KPIs — the five the reference computes (view.js:648-659). Money runs
             through the reference's own compact formatter, so a 70-lakh contract
             reads "৳ 70L" rather than "7M". --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Portfolio Value</span><span class="wap-kpi-ico"><i class="bi bi-easel2"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['value']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Committed Cost</span><span class="wap-kpi-ico"><i class="bi bi-wallet2"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['cost']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Portfolio Margin</span><span class="wap-kpi-ico"><i class="bi bi-graph-up-arrow"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['margin']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Live Projects</span><span class="wap-kpi-ico"><i class="bi bi-hammer"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['live'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Deadline Risk</span><span class="wap-kpi-ico"><i class="bi bi-alarm-fill"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['risk'] }}</div>
            </div>
        </div>

        @if($projects->isNotEmpty())
        <div class="wap-proj-grid">
            @foreach($projects as $p)
            @php
                // Deadline countdown + tone, mirroring the reference's dueLabel:
                // overdue is bad, inside a fortnight is warn, otherwise plain.
                $d = $p->days_left;
                $dueLabel = $d === null ? 'No deadline'
                          : ($d < 0 ? abs($d) . 'd overdue' : ($d === 0 ? 'Due today' : $d . 'd left'));
                $dueTone  = $d === null ? '' : ($d < 0 ? ' wap-badge-bad' : ($d < 14 ? ' wap-badge-warn' : ''));
                // Progress colour follows the same three-band logic.
                $pct = max(0, min(100, (int) $p->progress));
            @endphp
            <div class="wap-proj-card">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $p->name }}</div>
                        <div class="wap-proj-ext">{{ $p->ext_id }} &middot; {{ $p->client ?: 'No client' }}</div>
                    </div>
                    <span class="wap-badge">{{ $p->stage }}</span>
                </div>

                <div class="wap-proj-prog">
                    <div class="wap-progress"><div class="wap-progress-bar" style="width:{{ $pct }}%"></div></div>
                    <span class="wap-proj-pct">{{ $pct }}%</span>
                </div>

                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Value</div>
                        <div class="wap-proj-stat-value">{{ Project::money($p->value) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Cost</div>
                        <div class="wap-proj-stat-value">{{ Project::money($p->cost) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Margin</div>
                        <div class="wap-proj-stat-value">{{ Project::money($p->margin) }}@if($p->margin_pct !== null) &middot; {{ $p->margin_pct }}%@endif</div>
                    </div>
                </div>

                <div class="wap-proj-foot">
                    <span class="wap-proj-designer"><i class="bi bi-person-badge"></i> {{ $p->designer ?: '—' }}</span>
                    <span class="wap-badge{{ $dueTone }}">{{ $dueLabel }}</span>
                    {{-- Both are plain links: the delete one opens a confirmation
                         page, so no inline JS confirm() is needed inside the
                         swapped region. --}}
                    <span class="wap-proj-acts">
                        <a class="wap-proj-act" title="Edit {{ $p->ext_id }}" aria-label="Edit {{ $p->ext_id }}"
                           href="{{ route('role.woodart.projects.edit', ['role' => request()->route('role'), 'project' => $p]) }}">
                            <i class="bi bi-pencil"></i></a>
                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $p->ext_id }}" aria-label="Remove {{ $p->ext_id }}"
                           href="{{ route('role.woodart.projects.delete', ['role' => request()->route('role'), 'project' => $p]) }}">
                            <i class="bi bi-trash3"></i></a>
                    </span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        {{-- view.js:664-666 — the reference's own wording for an empty portfolio. --}}
        <div class="wap-empty">
            <i class="bi bi-easel2"></i>
            <div class="wap-empty-title">No projects yet</div>
            <div class="wap-empty-sub">Create your first interior project.</div>
        </div>
        @endif

    @elseif($section === 'design')
        {{-- SCREEN 2 · DESIGN STUDIO — the stage board.

             The reference moves a card by dragging it (view.js:733-746). Drag
             needs JavaScript inside [data-wa-view], which CLAUDE.md forbids, so
             each card carries two posted arrows instead. Same outcome, no
             script, and the move lands in browser history so Back undoes it. --}}
        @if(session('wa_status'))
        <div class="wap-flash"><i class="bi bi-check-circle-fill"></i> {{ session('wa_status') }}</div>
        @endif

        {{-- Search — a GET form, so the result is bookmarkable and survives a
             reload, and no script is needed inside the swapped region. --}}
        <form method="GET" class="wap-search"
              action="{{ route('role.woodart.projects', ['role' => request()->route('role'), 'section' => 'design']) }}">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $q }}" class="wap-input"
                   placeholder="Find by name, client, designer or code…"
                   aria-label="Search projects">
            <button type="submit" class="wap-btn wap-btn-ghost">Search</button>
            @if($q !== '')
            <a class="wap-btn wap-btn-ghost"
               href="{{ route('role.woodart.projects', ['role' => request()->route('role'), 'section' => 'design']) }}">Clear</a>
            @endif
        </form>

        @if($projects->isEmpty())
        <div class="wap-empty">
            <i class="bi bi-{{ $q !== '' ? 'search' : 'easel2' }}"></i>
            <div class="wap-empty-title">
                {{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No projects yet' }}
            </div>
            <div class="wap-empty-sub">
                {{ $q !== ''
                    ? 'Searched name, client, designer and project code.'
                    : 'Register a project and it appears on the board at Design.' }}
            </div>
        </div>
        @else
        {{-- data-wa-board is the ONLY thing woodart-board.js will act on. That
             script lives outside [data-wa-view] (loaded by the suite shell), so
             it survives no-reload navigation; it finds the board by this
             attribute and ignores every other element on the page. --}}
        <div class="wap-kanban" data-wa-board
             data-wa-stage-url="{{ route('role.woodart.projects.stage', ['role' => request()->route('role'), 'project' => '__ID__']) }}">
            @foreach($board as $stage => $inStage)
            @php
                $i = array_search($stage, $stages, true);
                // array_search returns FALSE for a retired stage, and in PHP
                // `false < 4` is true — so the arrows must be gated on this
                // flag, never on $i alone, or an unknown column would offer a
                // nonsensical "advance".
                $known = $i !== false;
            @endphp
            <div class="wap-kb-col" style="--wap-kb: {{ $stageColors[$stage] ?? '#c22945' }}">
                <div class="wap-kb-col-head">
                    <span class="wap-kb-col-dot"></span>
                    <span class="wap-kb-col-title">{{ $stage }}</span>
                    @unless($known)
                    <span class="wap-badge wap-badge-bad" title="This stage is no longer part of the workflow">retired</span>
                    @endunless
                    <span class="wap-kb-count" data-wa-count>{{ $inStage->count() }}</span>
                </div>
                <div class="wap-kb-list" data-wa-drop="{{ $stage }}">
                    @forelse($inStage as $p)
                    {{-- Only cards on a live stage are draggable; a retired one
                         must be moved with its recovery button so the choice is
                         deliberate. --}}
                    <div class="wap-kb-card" data-wa-card="{{ $p->ext_id }}" @if($known) draggable="true" @endif>
                        <div class="wap-kb-card-title">{{ $p->name }}</div>
                        <div class="wap-kb-card-sub">{{ $p->ext_id }} &middot; {{ $p->client ?: 'No client' }}</div>

                        <div class="wap-proj-prog" style="margin:10px 0 0">
                            <div class="wap-progress"><div class="wap-progress-bar" data-wa-bar style="width:{{ max(0, min(100, (int) $p->progress)) }}%"></div></div>
                            <span class="wap-proj-pct" data-wa-pct>{{ max(0, min(100, (int) $p->progress)) }}%</span>
                        </div>

                        <div class="wap-kb-card-foot">
                            <span>{{ \Modules\WoodArt\Modules\Projects\Models\Project::money($p->value) }}</span>
                            <span class="wap-kb-move">
                                @unless($known)
                                {{-- A project on a retired stage gets one clear
                                     way back onto the workflow. ROOT-MAP §2.1
                                     notes the reference's Design deliberately
                                     covers brief, concept, revisions AND client
                                     approval, so Design is where such a job
                                     belongs. --}}
                                <form method="POST" action="{{ route('role.woodart.projects.stage', ['role' => request()->route('role'), 'project' => $p]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="stage" value="{{ $stages[0] }}">
                                    <button type="submit" title="Put {{ $p->ext_id }} back on the workflow at {{ $stages[0] }}"
                                            aria-label="Move {{ $p->ext_id }} to {{ $stages[0] }}">
                                        <i class="bi bi-arrow-return-left"></i></button>
                                </form>
                                @endunless
                                @if($known && $i > 0)
                                <form method="POST" action="{{ route('role.woodart.projects.stage', ['role' => request()->route('role'), 'project' => $p]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="stage" value="{{ $stages[$i - 1] }}">
                                    <button type="submit" title="Move back to {{ $stages[$i - 1] }}"
                                            aria-label="Move {{ $p->ext_id }} back to {{ $stages[$i - 1] }}">
                                        <i class="bi bi-chevron-left"></i></button>
                                </form>
                                @endif
                                @if($known && $i < count($stages) - 1)
                                <form method="POST" action="{{ route('role.woodart.projects.stage', ['role' => request()->route('role'), 'project' => $p]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="stage" value="{{ $stages[$i + 1] }}">
                                    <button type="submit" title="Advance to {{ $stages[$i + 1] }}"
                                            aria-label="Advance {{ $p->ext_id }} to {{ $stages[$i + 1] }}">
                                        <i class="bi bi-chevron-right"></i></button>
                                </form>
                                @endif
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="wap-empty">
                        <i class="bi bi-inbox"></i>
                        <div class="wap-empty-sub">Nothing at {{ strtolower($stage) }}</div>
                    </div>
                    @endforelse
                </div>
            </div>
            @endforeach
        </div>
        @endif

    @elseif($section === 'gallery')
        {{-- SCREEN 4 · GALLERY — the portfolio wall (view.js:849-881).

             Read-only by design; the reference offers no actions here. The
             thumbnails are generated gradients, not photographs — see the
             .wap-gal-thumb note in the suite shell for why. --}}
        @if($projects->isEmpty())
        <div class="wap-empty">
            <i class="bi bi-images"></i>
            <div class="wap-empty-title">Nothing in the portfolio yet</div>
            <div class="wap-empty-sub">Every registered fit-out appears here.</div>
        </div>
        @else
        <div class="wap-gal-grid">
            @foreach($projects as $p)
            @php
                $from = $typeColors[$p->type]   ?? '#6f9c1c';
                $to   = $stageColors[$p->stage] ?? '#1A43BF';
                // The reference falls back to the code when there is no client.
                $sub  = $p->client ?: $p->ext_id;
                if ($p->area) { $sub .= ' · ' . number_format($p->area) . ' sft'; }
            @endphp
            <a class="wap-gal-card"
               href="{{ route('role.woodart.projects.edit', ['role' => request()->route('role'), 'project' => $p]) }}">
                <div class="wap-gal-thumb" style="background: linear-gradient(135deg, {{ $from }}, {{ $to }})">
                    <i class="bi bi-easel2-fill"></i>
                    <span class="wap-badge wap-gal-type">{{ $p->type }}</span>
                </div>
                <div class="wap-gal-body">
                    <div class="wap-gal-name">{{ $p->name }}</div>
                    <div class="wap-gal-sub">{{ $sub }}</div>
                    <div class="wap-gal-foot">
                        <span class="wap-badge">{{ $p->stage }}</span>
                        <span>{{ \Modules\WoodArt\Modules\Projects\Models\Project::money($p->value) }}</span>
                    </div>
                    <div class="wap-gal-sub">{{ max(0, min(100, (int) $p->progress)) }}% complete</div>
                </div>
            </a>
            @endforeach
        </div>
        @endif

    @else
        {{-- SCREEN 3 · MILESTONES & BILLING (view.js:775-846).

             Half of this screen reads data this module does not hold — posted
             sales, workshop jobs, site visits. Those KPIs show a dash naming
             the desk that will supply them, NOT a zero: "0 jobs running" with
             no workshop is a false statement, not an empty state. What is
             answerable from our own rows is answered properly. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card wap-kpi-unknown">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Revenue Billed</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
                <div class="wap-kpi-value">&mdash;</div>
                <div class="wap-kpi-foot">Needs the billing ledger</div>
            </div>
            <div class="wap-kpi-card wap-kpi-unknown">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Jobs Running</span><span class="wap-kpi-ico"><i class="bi bi-gear-wide-connected"></i></span></div>
                <div class="wap-kpi-value">&mdash;</div>
                <div class="wap-kpi-foot">Comes from Workshop</div>
            </div>
            <div class="wap-kpi-card wap-kpi-unknown">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Sites Snagging</span><span class="wap-kpi-ico"><i class="bi bi-exclamation-diamond"></i></span></div>
                <div class="wap-kpi-value">&mdash;</div>
                <div class="wap-kpi-foot">Comes from Site &amp; Install</div>
            </div>
            {{-- The one this module can answer on its own. --}}
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Awaiting Billing</span><span class="wap-kpi-ico"><i class="bi bi-receipt"></i></span></div>
                <div class="wap-kpi-value">{{ $milestones['awaitingBilling'] }}</div>
                <div class="wap-kpi-foot">At handover, not yet invoiced</div>
            </div>
        </div>

        @if($projects->isEmpty())
        <div class="wap-empty">
            <i class="bi bi-receipt-cutoff"></i>
            <div class="wap-empty-title">Nothing to report yet</div>
            <div class="wap-empty-sub">Register a project and its stage and value appear here.</div>
        </div>
        @else
        <div class="wap-caption">WHERE THE PORTFOLIO SITS</div>
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-pie-chart-fill"></i> Projects by Stage</h3>
                <span class="wap-card-sub">How the {{ $projects->count() }}
                    {{ \Illuminate\Support\Str::plural('job', $projects->count()) }} are spread</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-dist">
                    @foreach($milestones['byStage'] as $row)
                    <div class="wap-dist-row" style="--wap-dist: {{ $row['color'] }}">
                        <div class="wap-dist-main">
                            <div class="wap-dist-label"><span class="wap-dist-dot"></span>{{ $row['label'] }}</div>
                            <div class="wap-dist-track"><div class="wap-dist-bar" style="width:{{ $row['share'] }}%"></div></div>
                        </div>
                        <div class="wap-dist-figs">
                            <div class="wap-dist-value">{{ $row['count'] }}</div>
                            <div class="wap-dist-share">{{ $row['share'] }}%</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-bar-chart-fill"></i> Portfolio Value by Type</h3>
                <span class="wap-card-sub">Contract value, share of the whole book</span>
            </div>
            <div class="wap-card-body">
                @if(empty($milestones['byType']))
                <div class="wap-empty">
                    <i class="bi bi-bar-chart"></i>
                    <div class="wap-empty-sub">No contract values entered yet.</div>
                </div>
                @else
                <div class="wap-dist">
                    @foreach($milestones['byType'] as $row)
                    <div class="wap-dist-row" style="--wap-dist: {{ $row['color'] }}">
                        <div class="wap-dist-main">
                            <div class="wap-dist-label"><span class="wap-dist-dot"></span>{{ $row['label'] }}</div>
                            <div class="wap-dist-track"><div class="wap-dist-bar" style="width:{{ $row['share'] }}%"></div></div>
                        </div>
                        <div class="wap-dist-figs">
                            <div class="wap-dist-value">{{ Project::money($row['value']) }}</div>
                            <div class="wap-dist-share">{{ $row['share'] }}%</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endif

        <div class="wap-caption">CLIENT BILLING LEDGER</div>
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-receipt-cutoff"></i> Invoices &amp; Receipts</h3>
                <span class="wap-card-sub">Every invoice raised against a project, and what came back</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-empty">
                    <i class="bi bi-receipt-cutoff"></i>
                    <div class="wap-empty-title">Billing is not wired up yet</div>
                    <div class="wap-empty-sub">
                        Raising an invoice is the one action that moves money, so where Wood Art's
                        sales are recorded has to be settled first &mdash; in its own books, never in
                        another company's. Until then the projects at handover are counted above,
                        but nothing is invoiced.
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection
