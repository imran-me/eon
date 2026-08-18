{{-- Wood Art · Procurement — purchase order delete confirmation. --}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.procurement.orders.edit', ['role' => request()->route('role'), 'order' => $order]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $order->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this purchase order?</h3>
            <span class="wap-card-sub">Check the figures before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $order->supplier }}</div>
                        <div class="wap-proj-ext">{{ $order->ext_id }} &middot; {{ $order->project ?: 'General stock' }}</div>
                    </div>
                    <span class="wap-badge {{ $order->status === 'Received' ? 'wap-badge-good' : ($order->status === 'Partial' ? 'wap-badge-warn' : '') }}">
                        {{ $order->status }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Items</div>
                        <div class="wap-proj-stat-value">{{ number_format($order->items) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Value</div>
                        <div class="wap-proj-stat-value">{{ Project::money($order->amount) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Ordered</div>
                        <div class="wap-proj-stat-value">{{ $order->date?->format('d M Y') ?: '—' }}</div>
                    </div>
                </div>
            </div>

            @if($order->status === 'Received')
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This order is marked <strong>Received</strong> &mdash; the goods arrived. Removing it
                    takes {{ Project::money($order->amount) }} out of the spend history. If the order
                    was wrong, correcting it is usually better than erasing what happened.</div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>The order is <strong>archived, not erased</strong> &mdash; kept with a removal date, so
                    it can be restored and the code <strong>{{ $order->ext_id }}</strong> is never reused.</div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.procurement.orders.destroy', ['role' => request()->route('role'), 'order' => $order]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $order->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => 'orders']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
