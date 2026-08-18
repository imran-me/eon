{{--
    Wood Art · Materials — the delete confirmation.
    A page rather than a JS confirm(), stating what happens rather than asking
    "are you sure?".
--}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; use Modules\WoodArt\Modules\Materials\Models\Material; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.materials.edit', ['role' => request()->route('role'), 'material' => $material]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $material->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this material?</h3>
            <span class="wap-card-sub">Check the figures before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $material->name }}</div>
                        <div class="wap-proj-ext">{{ $material->ext_id }} &middot; {{ $material->supplier ?: 'No supplier recorded' }}</div>
                    </div>
                    <span class="wap-badge">{{ $material->category }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">In Stock</div>
                        <div class="wap-proj-stat-value">{{ Material::qty($material->stock) }} {{ $material->unit }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Unit Cost</div>
                        <div class="wap-proj-stat-value">{{ Project::money($material->unit_cost) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Value</div>
                        <div class="wap-proj-stat-value">{{ Project::money($material->value) }}</div>
                    </div>
                </div>
            </div>

            @if($material->stock > 0)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>
                    This item still shows <strong>{{ Material::qty($material->stock) }} {{ $material->unit }}</strong>
                    on the shelf, worth <strong>{{ Project::money($material->value) }}</strong>. Removing it takes that
                    value out of the stock valuation. If the stock is genuinely gone, it is better recorded as
                    wastage or an adjustment once the movements ledger exists, so the loss is explained rather
                    than simply disappearing.
                </div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>
                    The material is <strong>archived, not erased</strong> &mdash; the row is kept with a
                    removal date, so it can be restored and the code
                    <strong>{{ $material->ext_id }}</strong> is never reused.
                </div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.materials.destroy', ['role' => request()->route('role'), 'material' => $material]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $material->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.materials', ['role' => request()->route('role'), 'section' => 'stock']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
