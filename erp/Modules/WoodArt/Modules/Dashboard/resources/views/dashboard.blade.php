{{--
    Wood Art · Dashboard — extends the suite shell.

    Reads across the whole suite and owns none of it. Every figure links to the
    module that owns it, so there is exactly one place to fix a wrong number.

    NO REVENUE, PROFIT OR MARGIN. Those need the books, and where a Wood Art
    invoice is recorded has not been decided. Quoted and planned values are
    shown and labelled as quotes — never as income.

    NO <script> ANYWHERE IN THIS FILE.
--}}
@extends('woodart::layouts.suite')

@php
    use Modules\WoodArt\Modules\Projects\Models\Project;

    $waRole = request()->route('role');
    $waLink = fn (string $name, string $sec) => route($name, ['role' => $waRole, 'section' => $sec]);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.scope', 'phases') }}">
        <i class="bi bi-diagram-3-fill"></i> Phase Board</a>
    <a class="wap-btn wap-btn-primary" href="{{ route('role.woodart.projects.create', ['role' => $waRole]) }}">
        <i class="bi bi-plus-lg"></i> New Project</a>
@endsection

@section('wa-view')

    {{-- ── WHAT NEEDS SOMEBODY TODAY ─────────────────────────────────────── --}}
    @if(! empty($alerts))
    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-bell"></i> Needs Attention</h3>
            <span class="wap-card-sub">Only things a person can act on &mdash; each opens where it lives</span>
        </div>
        <div class="wap-card-body">
            <div class="wap-dist">
                @foreach($alerts as $a)
                <div class="wap-dist-row">
                    <div class="wap-dist-main">
                        <span class="wap-dist-dot"></span>
                        <span class="wap-dist-label">
                            <i class="bi bi-{{ $a['icon'] }}"></i>
                            <strong class="{{ $a['tone'] === 'bad' ? 'wap-t-bad' : '' }}">{{ number_format($a['count']) }}</strong>
                            {{ $a['text'] }}
                        </span>
                        <span class="wap-dist-value">
                            <a class="wap-btn wap-btn-ghost" href="{{ $waLink($a['route'], $a['section']) }}">Open</a>
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @else
    <div class="wap-banner">
        <i class="bi bi-check2-circle"></i>
        <div>Nothing is blocked, late or waiting on a client. Every open job is simply in progress.</div>
    </div>
    @endif

    {{-- ── THE SUITE AT A GLANCE ─────────────────────────────────────────── --}}
    <div class="wap-kpi-grid">
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Projects</span><span class="wap-kpi-ico"><i class="bi bi-easel2-fill"></i></span></div>
            <div class="wap-kpi-value">{{ number_format($cards['projects']['total']) }}</div>
            <div class="wap-kpi-foot">{{ number_format($cards['projects']['active']) }} not yet completed</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Project Value</span><span class="wap-kpi-ico"><i class="bi bi-cash-stack"></i></span></div>
            <div class="wap-kpi-value">{{ Project::money($cards['projects']['value']) }}</div>
            <div class="wap-kpi-foot">Contracted, not invoiced</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Quote Pipeline</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
            <div class="wap-kpi-value">{{ Project::money($cards['estimates']['pipeline']) }}</div>
            <div class="wap-kpi-foot">Quoted, awaiting an answer</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Open Jobs</span><span class="wap-kpi-ico"><i class="bi bi-hammer"></i></span></div>
            <div class="wap-kpi-value">{{ number_format($cards['production']['open']) }}</div>
            <div class="wap-kpi-foot">{{ number_format($cards['production']['running']) }} running, {{ number_format($cards['production']['blocked']) }} blocked</div>
        </div>
        <div class="wap-kpi-card">
            <div class="wap-kpi-top"><span class="wap-kpi-label">Stock Value</span><span class="wap-kpi-ico"><i class="bi bi-boxes"></i></span></div>
            <div class="wap-kpi-value">{{ Project::money($cards['materials']['value']) }}</div>
            <div class="wap-kpi-foot">{{ number_format($cards['materials']['items']) }} items in the store</div>
        </div>
    </div>

    {{-- ── EVERY PROJECT, ACROSS EVERY MODULE ────────────────────────────── --}}
    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-list-columns-reverse"></i> Projects Across the Suite</h3>
            <span class="wap-card-sub">What each module knows about each job</span>
        </div>
        <div class="wap-card-body">
            @if(empty($projects))
            <div class="wap-empty">
                <i class="bi bi-easel2"></i>
                <div class="wap-empty-title">No projects yet</div>
                <div class="wap-empty-sub">Register a project and every module lines up behind it here.</div>
            </div>
            @else
            <div class="wap-table-wrap">
                <table class="wap-table">
                    <thead>
                        <tr>
                            <th>Project</th><th>Stage</th>
                            <th style="min-width:140px">Progress</th>
                            <th class="wap-t-num">Value</th><th class="wap-t-num">Drawings</th>
                            <th class="wap-t-num">Phases</th><th class="wap-t-num">Jobs</th>
                            <th class="wap-t-num">Snags</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $row)
                        @php $p = $row['project']; @endphp
                        <tr>
                            <td class="wap-t-strong">
                                <a href="{{ route('role.woodart.projects.edit', ['role' => $waRole, 'project' => $p]) }}">{{ $p->name }}</a>
                                <span class="wap-caption" style="display:block">{{ $p->ext_id }}{{ $p->client ? ' · ' . $p->client : '' }}</span>
                            </td>
                            <td><span class="wap-badge">{{ $p->stage }}</span></td>
                            <td>
                                <div class="wap-proj-prog" style="margin:0">
                                    <div class="wap-progress"><div class="wap-progress-bar" style="width:{{ max(0, min(100, (int) $p->progress)) }}%"></div></div>
                                    <span class="wap-proj-pct">{{ max(0, min(100, (int) $p->progress)) }}%</span>
                                </div>
                            </td>
                            <td class="wap-t-num">{{ Project::money($p->value) }}</td>
                            <td class="wap-t-num">
                                {{ $row['drawingsTotal'] ? $row['drawingsOpen'] . ' / ' . $row['drawingsTotal'] : '—' }}
                            </td>
                            <td class="wap-t-num">
                                {{ $row['phasesTotal'] ? $row['phasesDone'] . ' / ' . $row['phasesTotal'] : '—' }}
                            </td>
                            <td class="wap-t-num">
                                {{ $row['jobsTotal'] ? $row['jobsOpen'] . ' / ' . $row['jobsTotal'] : '—' }}
                            </td>
                            <td class="wap-t-num {{ $row['snags'] ? 'wap-t-bad' : '' }}">{{ $row['snags'] ?: '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <span class="wap-hint">Drawings, phases and jobs read <strong>open / total</strong>.</span>
            @endif
        </div>
    </div>

    {{-- ── MODULE CARDS ──────────────────────────────────────────────────── --}}
    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-grid"></i> Every Desk</h3>
            <span class="wap-card-sub">The headline figure each module owns</span>
        </div>
        <div class="wap-card-body">
            <div class="wap-table-wrap">
                <table class="wap-table">
                    <thead>
                        <tr><th>Module</th><th>Holds</th><th>Right now</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="wap-t-strong"><i class="bi bi-diagram-3-fill"></i> Spaces &amp; Phases</td>
                            <td>{{ number_format($cards['scope']['phases']) }} phases</td>
                            <td>{{ number_format($cards['scope']['active']) }} active, {{ number_format($cards['scope']['complete']) }} complete</td>
                            <td><a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.scope', 'phases') }}">Open</a></td>
                        </tr>
                        <tr>
                            <td class="wap-t-strong"><i class="bi bi-vector-pen"></i> Design &amp; 3D</td>
                            <td>{{ number_format($cards['design']['total']) }} deliverables</td>
                            <td>{{ number_format($cards['design']['waiting']) }} with the client</td>
                            <td><a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.design', 'register') }}">Open</a></td>
                        </tr>
                        <tr>
                            <td class="wap-t-strong"><i class="bi bi-calculator-fill"></i> Estimates &amp; BOQ</td>
                            <td>{{ number_format($cards['estimates']['total']) }} quotations</td>
                            <td>{{ Project::money($cards['estimates']['approved']) }} approved</td>
                            <td><a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.estimates', 'quotations') }}">Open</a></td>
                        </tr>
                        <tr>
                            <td class="wap-t-strong"><i class="bi bi-hammer"></i> Workshop</td>
                            <td>{{ number_format($cards['production']['total']) }} jobs</td>
                            <td>{{ number_format($cards['production']['running']) }} running, {{ number_format($cards['production']['overdue']) }} late</td>
                            <td><a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.production', 'board') }}">Open</a></td>
                        </tr>
                        <tr>
                            <td class="wap-t-strong"><i class="bi bi-truck"></i> Site &amp; Install</td>
                            <td>{{ number_format($cards['installation']['total']) }} visits</td>
                            <td>{{ number_format($cards['installation']['open']) }} open, {{ number_format($cards['installation']['snags']) }} snags</td>
                            <td><a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.installation', 'schedule') }}">Open</a></td>
                        </tr>
                        <tr>
                            <td class="wap-t-strong"><i class="bi bi-boxes"></i> Materials</td>
                            <td>{{ number_format($cards['materials']['items']) }} items</td>
                            <td>{{ number_format($cards['materials']['low']) }} at reorder level</td>
                            <td><a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.materials', 'stock') }}">Open</a></td>
                        </tr>
                        <tr>
                            <td class="wap-t-strong"><i class="bi bi-cart-fill"></i> Procurement</td>
                            <td>{{ number_format($cards['procurement']['orders']) }} orders</td>
                            <td>{{ number_format($cards['procurement']['open']) }} open, {{ Project::money($cards['procurement']['value']) }} ordered</td>
                            <td><a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.procurement', 'orders') }}">Open</a></td>
                        </tr>
                        <tr>
                            <td class="wap-t-strong"><i class="bi bi-person-hearts"></i> Clients</td>
                            <td>{{ number_format($cards['clients']['total']) }} on the books</td>
                            <td>&mdash;</td>
                            <td><a class="wap-btn wap-btn-ghost" href="{{ $waLink('role.woodart.clients', 'directory') }}">Open</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="wap-banner">
        <i class="bi bi-info-circle"></i>
        <div>This screen shows <strong>no revenue, profit or margin</strong>. Those come from the books, and
            where a Wood Art invoice is recorded has not been decided yet &mdash; the figures above are
            contracted and quoted values from Wood Art's own records, never income.</div>
    </div>

@endsection
