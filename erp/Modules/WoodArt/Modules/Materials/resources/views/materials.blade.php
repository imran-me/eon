{{--
    Wood Art · Materials — extends the suite shell.

    Transcribed from the reference module's authored markup
    (companies/woodart/modules/materials/frontend/template.html, empty-state
    copy from view.js:599/757/899/947). Four screens: Stock is the register;
    Movements is the ledger explaining every number; Reorder is what to buy;
    Valuation is where the money is sitting.

    THREE OF THE FOUR READ REAL DATA from `wa_materials`. Movements is the
    exception and is honest about it: `wa_movements` is not built, so rather
    than showing zeros it says what it is waiting for. Every figure on the
    other three is derived on read — none is stored, so none can drift.
--}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; use Modules\WoodArt\Modules\Materials\Models\Material; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => 'orders']) }}">
        <i class="bi bi-cart-fill"></i> Procurement</a>
    <a class="wap-btn wap-btn-primary"
       href="{{ route('role.woodart.materials.create', ['role' => request()->route('role')]) }}">
        <i class="bi bi-plus-lg"></i> New Material</a>
@endsection

@section('wa-view')

    @if(session('wa_status'))
    <div class="wap-flash"><i class="bi bi-check-circle-fill"></i> {{ session('wa_status') }}</div>
    @endif

    @if($section === 'stock')
        {{-- SCREEN 1 · STOCK — the material register. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Items Tracked</span><span class="wap-kpi-ico"><i class="bi bi-boxes"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['items'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Stock Value</span><span class="wap-kpi-ico"><i class="bi bi-wallet2"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['value']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Below Reorder</span><span class="wap-kpi-ico"><i class="bi bi-exclamation-diamond"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['low'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Categories</span><span class="wap-kpi-ico"><i class="bi bi-tags"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['categories'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Suppliers</span><span class="wap-kpi-ico"><i class="bi bi-truck"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['suppliers'] }}</div>
            </div>
        </div>

        {{-- The reference shows this only when something is at or below its
             level (view.js:565). Same condition. --}}
        @if($stats['low'] > 0)
        <div class="wap-banner">
            <i class="bi bi-exclamation-diamond"></i>
            <div><strong>{{ $stats['low'] }}</strong>
                {{ \Illuminate\Support\Str::plural('item', $stats['low']) }}
                {{ $stats['low'] === 1 ? 'is' : 'are' }} at or below their reorder level.
                <a href="{{ route('role.woodart.materials', ['role' => request()->route('role'), 'section' => 'reorder']) }}">Open the reorder list</a>.</div>
        </div>
        @endif

        @include('woodart::partials.wa-search', ['action' => route('role.woodart.materials', ['role' => request()->route('role'), 'section' => 'stock']), 'q' => $q, 'placeholder' => 'Find by name, category, supplier or code…'])

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-list-columns-reverse"></i> Material Register</h3>
                <span class="wap-card-sub">Every item, live stock, reorder level and supplier</span>
            </div>
            <div class="wap-card-body">
                @if($materials->isEmpty())
                <div class="wap-empty">
                    <i class="bi bi-boxes"></i>
                    <div class="wap-empty-title">{{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No materials yet' }}</div>
                    <div class="wap-empty-sub">{{ $q !== ''
                        ? 'Searched name, category, supplier and code.'
                        : 'Add your first item to start tracking stock.' }}</div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Material</th><th>Category</th>
                                <th class="wap-t-num">In Stock</th><th class="wap-t-num">Reorder At</th>
                                <th class="wap-t-num">Unit Cost</th><th class="wap-t-num">Value</th>
                                <th>Supplier</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materials as $m)
                            <tr>
                                <td class="wap-t-strong">{{ $m->ext_id }}</td>
                                <td class="wap-t-strong">{{ $m->name }}</td>
                                <td><span class="wap-badge">{{ $m->category }}</span></td>
                                <td class="wap-t-num {{ $m->stock < 0 ? 'wap-t-bad' : '' }}">
                                    {{ Material::qty($m->stock) }} {{ $m->unit }}
                                    @if($m->is_low)<span class="wap-badge wap-badge-warn" style="margin-left:6px">low</span>@endif
                                </td>
                                <td class="wap-t-num">{{ $m->reorder > 0 ? Material::qty($m->reorder) : '—' }}</td>
                                <td class="wap-t-num">{{ Project::money($m->unit_cost) }}</td>
                                <td class="wap-t-num">{{ Project::money($m->value) }}</td>
                                <td>{{ $m->supplier ?: '—' }}</td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Edit {{ $m->ext_id }}" aria-label="Edit {{ $m->name }}"
                                           href="{{ route('role.woodart.materials.edit', ['role' => request()->route('role'), 'material' => $m]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $m->ext_id }}" aria-label="Remove {{ $m->name }}"
                                           href="{{ route('role.woodart.materials.delete', ['role' => request()->route('role'), 'material' => $m]) }}">
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

    @elseif($section === 'reorder')
        {{-- SCREEN 3 · REORDER — what to buy and what it will cost. --}}
        @php $low = $materials->filter->is_low->sortByDesc('short'); @endphp
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Items to Reorder</span><span class="wap-kpi-ico"><i class="bi bi-cart-plus"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['low'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Units Short</span><span class="wap-kpi-ico"><i class="bi bi-arrow-down-short"></i></span></div>
                <div class="wap-kpi-value">{{ Material::qty($stats['short']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Est. Refill Cost</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['refill']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Suppliers to Contact</span><span class="wap-kpi-ico"><i class="bi bi-truck"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['lowSuppliers'] }}</div>
            </div>
        </div>

        @if($low->isEmpty())
        {{-- STATE A · nothing to do (view.js:873). --}}
        <div class="wap-empty">
            <i class="bi bi-check2-circle"></i>
            <div class="wap-empty-title">Every item is above its reorder level</div>
            <div class="wap-empty-sub">Nothing needs buying today. Stock levels are healthy across all categories.</div>
        </div>
        @else
        <div class="wap-banner">
            <i class="bi bi-info-circle"></i>
            <div>Refill quantity is the gap back up to the reorder level. Estimated cost uses each
                item's current unit cost &mdash; raise the actual order in
                <a href="{{ route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => 'orders']) }}">Procurement</a>.</div>
        </div>
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-cart-plus"></i> Reorder List</h3>
                <span class="wap-card-sub">Sorted by how far below the line each item has fallen</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Material</th><th>Category</th>
                                <th class="wap-t-num">In Stock</th><th class="wap-t-num">Reorder At</th>
                                <th class="wap-t-num">Short By</th><th class="wap-t-num">Refill Cost</th>
                                <th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($low as $m)
                            <tr>
                                <td class="wap-t-strong">{{ $m->name }}</td>
                                <td><span class="wap-badge">{{ $m->category }}</span></td>
                                <td class="wap-t-num {{ $m->stock < 0 ? 'wap-t-bad' : '' }}">{{ Material::qty($m->stock) }} {{ $m->unit }}</td>
                                <td class="wap-t-num">{{ Material::qty($m->reorder) }}</td>
                                <td class="wap-t-num wap-t-bad">{{ Material::qty($m->short) }}</td>
                                <td class="wap-t-num">{{ Project::money($m->refill_cost) }}</td>
                                <td>{{ $m->supplier ?: '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

    @elseif($section === 'valuation')
        {{-- SCREEN 4 · VALUATION — where the money is sitting. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Stock Value</span><span class="wap-kpi-ico"><i class="bi bi-safe2"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['value']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Largest Category</span><span class="wap-kpi-ico"><i class="bi bi-tags"></i></span></div>
                <div class="wap-kpi-value" style="font-size:18px">{{ $categories[0]['label'] ?? '—' }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Avg Item Value</span><span class="wap-kpi-ico"><i class="bi bi-calculator"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['avg']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Dead Stock (nothing on the shelf)</span><span class="wap-kpi-ico"><i class="bi bi-slash-circle"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['dead'] }}</div>
            </div>
        </div>

        @if($materials->isEmpty())
        <div class="wap-empty">
            <i class="bi bi-safe2"></i>
            <div class="wap-empty-title">Nothing to value</div>
            <div class="wap-empty-sub">Add materials to see where stock value sits.</div>
        </div>
        @else
        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-bar-chart-fill"></i> Value by Category</h3>
                <span class="wap-card-sub">Share of total stock value</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-dist">
                    @foreach($categories as $c)
                    <div class="wap-dist-row">
                        <div class="wap-dist-main">
                            <div class="wap-dist-label"><span class="wap-dist-dot"></span>{{ $c['label'] }}
                                <span style="font-weight:400;color:var(--wap-text-mute)">
                                    &middot; {{ $c['items'] }} {{ \Illuminate\Support\Str::plural('item', $c['items']) }}</span>
                            </div>
                            <div class="wap-dist-track"><div class="wap-dist-bar" style="width:{{ $c['share'] }}%"></div></div>
                        </div>
                        <div class="wap-dist-figs">
                            <div class="wap-dist-value">{{ Project::money($c['value']) }}</div>
                            <div class="wap-dist-share">{{ $c['share'] }}% of value</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-table"></i> Valuation Register</h3>
                <span class="wap-card-sub">Stock &times; unit cost, highest value first</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Material</th><th>Category</th>
                                <th class="wap-t-num">In Stock</th><th class="wap-t-num">Unit Cost</th>
                                <th class="wap-t-num">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($materials->sortByDesc('value') as $m)
                            <tr>
                                <td class="wap-t-strong">{{ $m->name }}</td>
                                <td><span class="wap-badge">{{ $m->category }}</span></td>
                                <td class="wap-t-num {{ $m->stock < 0 ? 'wap-t-bad' : '' }}">{{ Material::qty($m->stock) }} {{ $m->unit }}</td>
                                <td class="wap-t-num">{{ Project::money($m->unit_cost) }}</td>
                                <td class="wap-t-num">{{ Project::money($m->value) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

    @else
        {{-- SCREEN 2 · MOVEMENTS — the stock ledger.

             Deliberately not faked. This screen exists to explain why every
             stock number is what it is, and there is no ledger yet: stock is
             currently set directly on the material. Showing zeros would imply
             nothing has moved, when the truth is nothing is being recorded. --}}
        <div class="wap-empty">
            <i class="bi bi-arrow-left-right"></i>
            <div class="wap-empty-title">The stock ledger is not built yet</div>
            <div class="wap-empty-sub">
                Every receipt, issue, adjustment and wastage will be recorded here, so a stock
                figure can always be explained by the movements behind it. Until then stock is
                set directly on each material, and this screen would have nothing truthful to
                show. Stock levels and valuation are live on the other three tabs.
            </div>
        </div>
    @endif

@endsection
