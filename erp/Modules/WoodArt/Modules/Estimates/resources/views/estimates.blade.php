{{--
    Wood Art · Estimates & BOQ — extends the suite shell.

    Three screens: Quotations is what was quoted and what the client said;
    Bill of Materials sets every quoted line against TODAY's register price;
    Costing is which quotations actually make money.

    THE DRIFT COLUMNS are the point of this module. A quote written months ago
    against plywood that has since gone up is the commonest way an interiors job
    loses its margin before a sheet is cut. Where an item is not in the register
    at all the cell reads "not stocked", NOT zero — those are different facts.

    NOTHING HERE BILLS. An approved estimate is still a quotation; turning one
    into money belongs to Projects.

    NO <script> ANYWHERE IN THIS FILE.
--}}
@extends('woodart::layouts.suite')

@php
    use Modules\WoodArt\Modules\Projects\Models\Project;

    $waRole = request()->route('role');
    $waHere = route('role.woodart.estimates', ['role' => $waRole, 'section' => $section]);

    $waStatusClass = fn (string $s) => match ($s) {
        'Approved' => 'wap-badge-good',
        'Rejected' => 'wap-badge-bad',
        'Sent'     => 'wap-badge-warn',
        default    => '',
    };
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.projects', ['role' => $waRole, 'section' => 'active']) }}">
        <i class="bi bi-easel2-fill"></i> Projects</a>
    <a class="wap-btn wap-btn-primary" href="{{ route('role.woodart.estimates.create', ['role' => $waRole]) }}">
        <i class="bi bi-plus-lg"></i> New Estimate</a>
@endsection

@section('wa-view')

    @if($section === 'quotations')
        {{-- SCREEN 1 · QUOTATIONS — the register, richest first. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Estimates</span><span class="wap-kpi-ico"><i class="bi bi-calculator"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['count']) }}</div>
                @if($stats['lines'])<div class="wap-kpi-foot">{{ number_format($stats['lines']) }} quoted lines</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Pipeline</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['pipeline']) }}</div>
                <div class="wap-kpi-foot">Quoted, not yet answered</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Approved</span><span class="wap-kpi-ico"><i class="bi bi-check2-circle"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['approved']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Win Rate</span><span class="wap-kpi-ico"><i class="bi bi-trophy"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['winRate'] }}%</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Expired</span><span class="wap-kpi-ico"><i class="bi bi-calendar-x"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['expired']) }}</div>
                @if($stats['expired'])<div class="wap-kpi-foot">Past validity, still unanswered</div>@endif
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-file-earmark-text"></i> Quotations</h3>
                <span class="wap-card-sub">Richest first &mdash; an estimate is its value</span>
            </div>
            <div class="wap-card-body">

                @include('woodart::partials.wa-search', [
                    'action'      => $waHere,
                    'q'           => $q,
                    'placeholder' => 'Search title, client, project or any quoted line…',
                ])

                @if($estimates->isEmpty())
                <div class="wap-empty">
                    <i class="bi bi-{{ $q !== '' ? 'search' : 'calculator' }}"></i>
                    <div class="wap-empty-title">
                        {{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No estimates yet' }}
                    </div>
                    <div class="wap-empty-sub">
                        {{ $q !== ''
                            ? 'Searched title, code, client, project and every quoted line.'
                            : 'Quote a job and its bill of quantities lands here.' }}
                    </div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Title</th><th>Client</th>
                                <th class="wap-t-num">Lines</th><th class="wap-t-num">Cost</th>
                                <th class="wap-t-num">Sale</th><th class="wap-t-num">Margin</th>
                                <th>Valid Till</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($estimates as $e)
                            <tr>
                                <td class="wap-t-strong">{{ $e->ext_id }}</td>
                                <td class="wap-t-strong">{{ $e->title }}
                                    @if($e->project_ext)
                                    <span class="wap-caption" style="display:block">
                                        {{ $e->project_ext }}
                                        {{-- Flagged, not hidden, exactly as every other register does:
                                             a quotation still pointing at a removed project is a
                                             problem you want to SEE. --}}
                                        @unless(\array_key_exists($e->project_ext, $projectNames ?? []))
                                        <span class="wap-badge wap-badge-bad" style="margin-left:4px"
                                              title="This project no longer exists">orphan</span>
                                        @endunless
                                    </span>
                                    @endif
                                </td>
                                <td>{{ $e->client ?: '—' }}</td>
                                <td class="wap-t-num">{{ number_format($e->line_count) }}</td>
                                <td class="wap-t-num">{{ Project::money($e->cost) }}</td>
                                <td class="wap-t-num">{{ Project::money($e->sale) }}</td>
                                <td class="wap-t-num {{ $e->margin < 0 ? 'wap-t-bad' : 'wap-t-good' }}">
                                    {{ Project::money($e->margin) }}
                                    <span class="wap-caption" style="display:block">{{ $e->margin_pct }}%</span>
                                </td>
                                <td class="{{ $e->is_expired ? 'wap-t-bad' : '' }}">
                                    {{ $e->valid_till?->format('d M Y') ?: '—' }}
                                    @if($e->is_expired)
                                    <span class="wap-caption" style="display:block">expired</span>
                                    @endif
                                </td>
                                <td><span class="wap-badge {{ $waStatusClass($e->status) }}">{{ $e->status }}</span></td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Edit {{ $e->ext_id }}" aria-label="Edit {{ $e->title }}"
                                           href="{{ route('role.woodart.estimates.edit', ['role' => $waRole, 'estimate' => $e]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $e->ext_id }}" aria-label="Remove {{ $e->title }}"
                                           href="{{ route('role.woodart.estimates.delete', ['role' => $waRole, 'estimate' => $e]) }}">
                                            <i class="bi bi-trash3"></i></a>
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

    @elseif($section === 'boq')
        {{-- SCREEN 2 · BILL OF MATERIALS — every quoted line vs today's price. --}}
        @php
            $waDrifted = collect($lines)->filter(fn ($l) => $l['drift'] !== null && $l['drift'] > 0);
            $waExposure = (int) $waDrifted->sum(fn ($l) => $l['drift'] * $l['qty']);
            $waUnknown = collect($lines)->reject->known->count();
        @endphp

        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Quoted Lines</span><span class="wap-kpi-ico"><i class="bi bi-list-ul"></i></span></div>
                <div class="wap-kpi-value">{{ number_format(count($lines)) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Quoted Cost</span><span class="wap-kpi-ico"><i class="bi bi-cash-stack"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['cost']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Lines Gone Up</span><span class="wap-kpi-ico"><i class="bi bi-graph-up-arrow"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($waDrifted->count()) }}</div>
                @if($waUnknown)<div class="wap-kpi-foot">{{ number_format($waUnknown) }} not in the register</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Exposure</span><span class="wap-kpi-ico"><i class="bi bi-exclamation-diamond"></i></span></div>
                <div class="wap-kpi-value {{ $waExposure ? 'wap-t-bad' : '' }}">{{ Project::money($waExposure) }}</div>
                <div class="wap-kpi-foot">Extra cost at today's prices</div>
            </div>
        </div>

        @if(empty($lines))
        <div class="wap-empty">
            <i class="bi bi-list-ul"></i>
            <div class="wap-empty-title">No quoted lines</div>
            <div class="wap-empty-sub">Add a bill of quantities to an estimate and every line appears here.</div>
        </div>
        @else
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-list-ul"></i> Every Quoted Line</h3>
                <span class="wap-card-sub">Dearest first &mdash; quoted cost against today's register price</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Item</th><th>Trade</th><th>Estimate</th>
                                <th class="wap-t-num">Qty</th><th class="wap-t-num">Quoted Cost</th>
                                <th class="wap-t-num">Today</th><th class="wap-t-num">Drift</th>
                                <th class="wap-t-num">Line Sale</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $l)
                            <tr>
                                <td class="wap-t-strong">{{ $l['item'] }}
                                    <span class="wap-caption" style="display:block">{{ $l['kind'] }}</span></td>
                                <td>{{ $l['code'] ?: '—' }}</td>
                                <td>{{ $l['estimate'] }}</td>
                                <td class="wap-t-num">{{ rtrim(rtrim(number_format($l['qty'], 2), '0'), '.') }} {{ $l['unit'] }}</td>
                                <td class="wap-t-num">{{ Project::money($l['unitCost']) }}</td>
                                <td class="wap-t-num">
                                    @if($l['known']) {{ Project::money($l['live']) }}
                                    @else <span class="wap-caption">not stocked</span> @endif
                                </td>
                                <td class="wap-t-num {{ $l['drift'] !== null && $l['drift'] > 0 ? 'wap-t-bad' : ($l['drift'] !== null && $l['drift'] < 0 ? 'wap-t-good' : '') }}">
                                    @if($l['drift'] === null) &mdash;
                                    @elseif($l['drift'] === 0) same
                                    @else {{ $l['drift'] > 0 ? '+' : '' }}{{ Project::money($l['drift']) }}
                                    @endif
                                </td>
                                <td class="wap-t-num">{{ Project::money($l['lineSale']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if(! empty($demand))
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-basket"></i> Material Demand</h3>
                <span class="wap-card-sub">What the live pipeline will consume &mdash; rejected quotes excluded</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Material</th><th class="wap-t-num">Quantity</th>
                                <th class="wap-t-num">At Quoted Cost</th><th class="wap-t-num">Estimates</th>
                                <th>In Register</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($demand as $d)
                            <tr>
                                <td class="wap-t-strong">{{ $d['item'] }}</td>
                                <td class="wap-t-num">{{ rtrim(rtrim(number_format($d['qty'], 2), '0'), '.') }} {{ $d['unit'] }}</td>
                                <td class="wap-t-num">{{ Project::money($d['cost']) }}</td>
                                <td class="wap-t-num">{{ number_format($d['estimates']) }}</td>
                                <td>
                                    @if($d['known'])
                                        <span class="wap-badge wap-badge-good">yes</span>
                                    @else
                                        <span class="wap-badge">not stocked</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
        @endif

    @else
        {{-- SCREEN 3 · COSTING — which quotations actually make money. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Quoted Sale</span><span class="wap-kpi-ico"><i class="bi bi-cash-stack"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['sale']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Quoted Cost</span><span class="wap-kpi-ico"><i class="bi bi-wallet2"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['cost']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Margin</span><span class="wap-kpi-ico"><i class="bi bi-graph-up"></i></span></div>
                <div class="wap-kpi-value {{ $stats['margin'] < 0 ? 'wap-t-bad' : '' }}">{{ Project::money($stats['margin']) }}</div>
                @if($stats['sale'])<div class="wap-kpi-foot">{{ (int) round($stats['margin'] / $stats['sale'] * 100) }}% of sale</div>@endif
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Estimates</span><span class="wap-kpi-ico"><i class="bi bi-calculator"></i></span></div>
                <div class="wap-kpi-value">{{ number_format($stats['count']) }}</div>
            </div>
        </div>

        @if(empty($costing))
        <div class="wap-empty">
            <i class="bi bi-graph-up"></i>
            <div class="wap-empty-title">Nothing to cost yet</div>
            <div class="wap-empty-sub">Quote a job and its margin appears here.</div>
        </div>
        @else
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-graph-up"></i> Margin by Estimate</h3>
                <span class="wap-card-sub">Worst first &mdash; and what the margin becomes at today's prices</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Estimate</th><th>Client</th><th>Status</th>
                                <th class="wap-t-num">Cost</th><th class="wap-t-num">Sale</th>
                                <th class="wap-t-num">Margin</th><th class="wap-t-num">Risen Lines</th>
                                <th class="wap-t-num">Margin Today</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($costing as $c)
                            <tr>
                                <td class="wap-t-strong">{{ $c['title'] }}
                                    <span class="wap-caption" style="display:block">{{ $c['ext_id'] }}{{ $c['project'] ? ' · ' . $c['project'] : '' }}</span></td>
                                <td>{{ $c['client'] ?: '—' }}</td>
                                <td><span class="wap-badge {{ $waStatusClass($c['status']) }}">{{ $c['status'] }}</span></td>
                                <td class="wap-t-num">{{ Project::money($c['cost']) }}</td>
                                <td class="wap-t-num">{{ Project::money($c['sale']) }}</td>
                                <td class="wap-t-num {{ $c['margin'] < 0 ? 'wap-t-bad' : 'wap-t-good' }}">
                                    {{ Project::money($c['margin']) }}
                                    <span class="wap-caption" style="display:block">{{ $c['marginPct'] }}%</span>
                                </td>
                                <td class="wap-t-num {{ $c['drifted'] ? 'wap-t-bad' : '' }}">
                                    {{ $c['drifted'] ?: '—' }}
                                    @if($c['driftValue'])
                                    <span class="wap-caption" style="display:block">+{{ Project::money($c['driftValue']) }}</span>
                                    @endif
                                </td>
                                <td class="wap-t-num {{ $c['marginToday'] < 0 ? 'wap-t-bad' : '' }}">
                                    {{ Project::money($c['marginToday']) }}
                                    <span class="wap-caption" style="display:block">{{ $c['pctToday'] }}%</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="wap-banner" style="margin-top:16px">
                    <i class="bi bi-info-circle"></i>
                    <div><strong>Margin Today</strong> re-prices only the lines that have gone UP in the
                        material register. A line that got cheaper is a windfall and is not banked here.
                        Items not in the register are left at their quoted cost.</div>
                </div>
            </div>
        </div>
        @endif
    @endif

@endsection
