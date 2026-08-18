{{-- Wood Art · Procurement — vendor delete confirmation. --}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.procurement.vendors.edit', ['role' => request()->route('role'), 'vendor' => $vendor]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $vendor->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this vendor?</h3>
            <span class="wap-card-sub">Check what is attached before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $vendor->name }}</div>
                        <div class="wap-proj-ext">{{ $vendor->ext_id }} &middot; {{ $vendor->area ?: 'No area recorded' }}</div>
                    </div>
                    <span class="wap-badge">{{ $vendor->category }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Orders</div>
                        <div class="wap-proj-stat-value">{{ $linked['orders'] }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Open</div>
                        <div class="wap-proj-stat-value">{{ $linked['open'] }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Spend</div>
                        <div class="wap-proj-stat-value">{{ Project::money($linked['value']) }}</div>
                    </div>
                </div>
            </div>

            @if($linked['orders'] > 0)
            @php
                // Built here rather than with an inline @if: Blade mis-parses a
                // directive opened and closed mid-sentence, which 500'd this page.
                $openNote = $linked['open'] > 0
                    ? ', of which ' . $linked['open'] . ' still '
                      . ($linked['open'] === 1 ? 'is' : 'are') . ' awaiting delivery'
                    : '';
            @endphp
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>
                    This vendor has <strong>{{ $linked['orders'] }}</strong>
                    {{ \Illuminate\Support\Str::plural('order', $linked['orders']) }} worth
                    <strong>{{ Project::money($linked['value']) }}</strong>{{ $openNote }}.
                    Those orders are <strong>not</strong> deleted &mdash; the buying really happened. They
                    will show as <strong>Unregistered</strong> on the Spend screen, still counted in the
                    totals, until a vendor with the same name exists again.
                </div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>The vendor is <strong>archived, not erased</strong> &mdash; kept with a removal date, so
                    it can be restored and the code <strong>{{ $vendor->ext_id }}</strong> is never reused.</div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.procurement.vendors.destroy', ['role' => request()->route('role'), 'vendor' => $vendor]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $vendor->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => 'vendors']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
