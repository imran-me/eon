{{--
    Wood Art · Procurement — extends the suite shell.

    Transcribed from the reference module's authored markup
    (companies/woodart/modules/procurement/frontend/template.html, empty-state
    copy from view.js:691/731). Three screens: Purchase Orders is what has been
    ordered and what is still owed; Vendors is who Woodart buys from; Spend is
    where the money goes.

    ALL THREE READ REAL DATA from `wa_purchases` and `wa_vendors`.

    ⚠ ORDERS JOIN TO VENDORS BY NAME, not by id — the reference's own design.
    An order naming a supplier who is not in the register still counts towards
    total spend, and is shown as "Unregistered" rather than dropped, because a
    missing roll-up is how a spend total quietly goes wrong.
--}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.materials', ['role' => request()->route('role'), 'section' => 'stock']) }}">
        <i class="bi bi-boxes"></i> Materials</a>
    @if($section === 'vendors')
    <a class="wap-btn wap-btn-primary"
       href="{{ route('role.woodart.procurement.vendors.create', ['role' => request()->route('role')]) }}">
        <i class="bi bi-plus-lg"></i> New Vendor</a>
    @else
    <a class="wap-btn wap-btn-primary"
       href="{{ route('role.woodart.procurement.orders.create', ['role' => request()->route('role')]) }}">
        <i class="bi bi-plus-lg"></i> New Purchase Order</a>
    @endif
@endsection

@section('wa-view')

    @if(session('wa_status'))
    <div class="wap-flash"><i class="bi bi-check-circle-fill"></i> {{ session('wa_status') }}</div>
    @endif

    @php
        $searchUrl = route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => $section]);
    @endphp

    @if($section === 'orders')
        {{-- SCREEN 1 · PURCHASE ORDERS. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Purchase Orders</span><span class="wap-kpi-ico"><i class="bi bi-receipt"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['orders'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Ordered Value</span><span class="wap-kpi-ico"><i class="bi bi-cash-stack"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['value']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Received</span><span class="wap-kpi-ico"><i class="bi bi-box-seam"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['received']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Awaiting Delivery</span><span class="wap-kpi-ico"><i class="bi bi-truck"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['open'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Vendors Used</span><span class="wap-kpi-ico"><i class="bi bi-shop"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['vendorsUsed'] }}</div>
            </div>
        </div>

        @if($stats['open'] > 0)
        <div class="wap-banner">
            <i class="bi bi-truck"></i>
            <div><strong>{{ $stats['open'] }}</strong>
                {{ \Illuminate\Support\Str::plural('order', $stats['open']) }} worth
                <strong>{{ Project::money($stats['outstanding']) }}</strong>
                {{ $stats['open'] === 1 ? 'has' : 'have' }} not been fully received.</div>
        </div>
        @endif

        @include('woodart::partials.wa-search', ['action' => $searchUrl, 'q' => $q, 'placeholder' => 'Find by code, supplier, project or status…'])

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-receipt-cutoff"></i> Purchase Order Register</h3>
                <span class="wap-card-sub">Every order raised on a vendor, newest first</span>
            </div>
            <div class="wap-card-body">
                @if($orders->isEmpty())
                <div class="wap-empty">
                    <i class="bi bi-receipt"></i>
                    <div class="wap-empty-title">{{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No purchase orders yet' }}</div>
                    <div class="wap-empty-sub">{{ $q !== '' ? 'Searched code, supplier, project and status.' : 'Raise the first order on a vendor.' }}</div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Supplier</th><th>For</th>
                                <th class="wap-t-num">Items</th><th class="wap-t-num">Amount</th>
                                <th>Status</th><th>Date</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $o)
                            <tr>
                                <td class="wap-t-strong">{{ $o->ext_id }}</td>
                                <td class="wap-t-strong">{{ $o->supplier }}</td>
                                {{-- A null project means general stock, never "unknown". --}}
                                <td>{{ $o->project ?: 'General stock' }}</td>
                                <td class="wap-t-num">{{ number_format($o->items) }}</td>
                                <td class="wap-t-num">{{ Project::money($o->amount) }}</td>
                                <td>
                                    <span class="wap-badge {{ $o->status === 'Received' ? 'wap-badge-good' : ($o->status === 'Partial' ? 'wap-badge-warn' : '') }}">
                                        {{ $o->status }}</span>
                                </td>
                                <td>{{ $o->date?->format('d M Y') ?: '—' }}</td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Edit {{ $o->ext_id }}" aria-label="Edit {{ $o->ext_id }}"
                                           href="{{ route('role.woodart.procurement.orders.edit', ['role' => request()->route('role'), 'order' => $o]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $o->ext_id }}" aria-label="Remove {{ $o->ext_id }}"
                                           href="{{ route('role.woodart.procurement.orders.delete', ['role' => request()->route('role'), 'order' => $o]) }}">
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

    @elseif($section === 'vendors')
        {{-- SCREEN 2 · VENDORS. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Vendors</span><span class="wap-kpi-ico"><i class="bi bi-shop"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['vendors'] }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Spend</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['value']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Top Vendor</span><span class="wap-kpi-ico"><i class="bi bi-trophy"></i></span></div>
                <div class="wap-kpi-value" style="font-size:18px">{{ $stats['top'] ?: '—' }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Never Ordered From</span><span class="wap-kpi-ico"><i class="bi bi-slash-circle"></i></span></div>
                <div class="wap-kpi-value">{{ $stats['idle'] }}</div>
            </div>
        </div>

        @include('woodart::partials.wa-search', ['action' => $searchUrl, 'q' => $q, 'placeholder' => 'Find by code, name, category or area…'])

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-shop-window"></i> Vendor Directory</h3>
                <span class="wap-card-sub">Suppliers of board, laminate, hardware, finishes and fabric</span>
            </div>
            <div class="wap-card-body">
                @if($vendors->isEmpty())
                <div class="wap-empty">
                    <i class="bi bi-shop"></i>
                    <div class="wap-empty-title">{{ $q !== '' ? 'Nothing matches “' . $q . '”' : 'No vendors yet' }}</div>
                    <div class="wap-empty-sub">{{ $q !== '' ? 'Searched code, name, category and area.' : 'Add the suppliers Woodart buys from.' }}</div>
                </div>
                @else
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Code</th><th>Vendor</th><th>Category</th><th>Contact</th>
                                <th>Phone</th><th>Area</th><th>Terms</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vendors as $v)
                            <tr>
                                <td class="wap-t-strong">{{ $v->ext_id }}</td>
                                <td class="wap-t-strong">{{ $v->name }}</td>
                                <td><span class="wap-badge">{{ $v->category }}</span></td>
                                <td>{{ $v->contact ?: '—' }}</td>
                                <td>{{ $v->phone ?: '—' }}</td>
                                <td>{{ $v->area ?: '—' }}</td>
                                <td>{{ $v->terms ?: '—' }}</td>
                                <td>
                                    <span class="wap-t-acts">
                                        <a class="wap-proj-act" title="Edit {{ $v->ext_id }}" aria-label="Edit {{ $v->name }}"
                                           href="{{ route('role.woodart.procurement.vendors.edit', ['role' => request()->route('role'), 'vendor' => $v]) }}">
                                            <i class="bi bi-pencil"></i></a>
                                        <a class="wap-proj-act wap-proj-act-bad" title="Remove {{ $v->ext_id }}" aria-label="Remove {{ $v->name }}"
                                           href="{{ route('role.woodart.procurement.vendors.delete', ['role' => request()->route('role'), 'vendor' => $v]) }}">
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

    @else
        {{-- SCREEN 3 · SPEND — where the procurement money goes. --}}
        <div class="wap-kpi-grid">
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Total Spend</span><span class="wap-kpi-ico"><i class="bi bi-cash-coin"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['value']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Avg Order Value</span><span class="wap-kpi-ico"><i class="bi bi-calculator"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['avg']) }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Top Vendor</span><span class="wap-kpi-ico"><i class="bi bi-trophy"></i></span></div>
                <div class="wap-kpi-value" style="font-size:18px">{{ $stats['top'] ?: '—' }}</div>
            </div>
            <div class="wap-kpi-card">
                <div class="wap-kpi-top"><span class="wap-kpi-label">Outstanding</span><span class="wap-kpi-ico"><i class="bi bi-hourglass-split"></i></span></div>
                <div class="wap-kpi-value">{{ Project::money($stats['outstanding']) }}</div>
            </div>
        </div>

        @if($spend->isEmpty() || $stats['value'] === 0)
        <div class="wap-empty">
            <i class="bi bi-cart"></i>
            <div class="wap-empty-title">No purchase orders yet</div>
            <div class="wap-empty-sub">Raise an order on a vendor and the spend analysis fills in.</div>
        </div>
        @else
        @if($stats['unregistered'] > 0)
        <div class="wap-banner" style="background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
            <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
            <div><strong>{{ $stats['unregistered'] }}</strong>
                {{ \Illuminate\Support\Str::plural('supplier', $stats['unregistered']) }}
                named on orders {{ $stats['unregistered'] === 1 ? 'is' : 'are' }} not in the vendor
                register. Their spend is counted below and marked
                <strong>Unregistered</strong> &mdash; orders join to vendors by name, so adding them with
                the exact same name links the history up.</div>
        </div>
        @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-bar-chart-fill"></i> Spend by Vendor</h3>
                <span class="wap-card-sub">Highest first, with what is still outstanding</span>
            </div>
            <div class="wap-card-body">
                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th>Vendor</th><th>Category</th>
                                <th class="wap-t-num">Orders</th><th class="wap-t-num">Open</th>
                                <th class="wap-t-num">Spend</th><th class="wap-t-num">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($spend as $r)
                            <tr>
                                <td class="wap-t-strong">{{ $r['name'] }}</td>
                                <td>
                                    <span class="wap-badge {{ $r['vendor'] ? '' : 'wap-badge-warn' }}">{{ $r['category'] }}</span>
                                </td>
                                <td class="wap-t-num">{{ $r['orders'] }}</td>
                                <td class="wap-t-num">{{ $r['open'] }}</td>
                                <td class="wap-t-num">{{ Project::money($r['value']) }}</td>
                                <td class="wap-t-num {{ $r['outstanding'] > 0 ? 'wap-t-bad' : '' }}">
                                    {{ Project::money($r['outstanding']) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    @endif

@endsection
