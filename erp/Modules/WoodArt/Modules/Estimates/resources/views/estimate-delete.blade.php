{{-- Wood Art · Estimates — quotation delete confirmation. --}}
@extends('woodart::layouts.suite')

@php
    use Modules\WoodArt\Modules\Projects\Models\Project;
    $waRole = request()->route('role');
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.estimates.edit', ['role' => $waRole, 'estimate' => $estimate]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $estimate->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this estimate?</h3>
            <span class="wap-card-sub">Check what goes with it before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $estimate->title }}</div>
                        <div class="wap-proj-ext">
                            {{ $estimate->ext_id }} &middot; {{ $estimate->client ?: 'No client' }}
                            @if($estimate->project_ext) &middot; {{ $estimate->project_ext }} @endif
                        </div>
                    </div>
                    <span class="wap-badge {{ $estimate->status === 'Approved' ? 'wap-badge-good' : ($estimate->status === 'Rejected' ? 'wap-badge-bad' : ($estimate->status === 'Sent' ? 'wap-badge-warn' : '')) }}">
                        {{ $estimate->status }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Lines</div>
                        <div class="wap-proj-stat-value">{{ number_format($estimate->line_count) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Sale</div>
                        <div class="wap-proj-stat-value">{{ Project::money($estimate->sale) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Margin</div>
                        <div class="wap-proj-stat-value">{{ Project::money($estimate->margin) }}</div>
                    </div>
                </div>
            </div>

            @if($estimate->line_count > 0)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This estimate carries a <strong>{{ number_format($estimate->line_count) }}-line</strong>
                    bill of quantities worth {{ Project::money($estimate->sale) }}. Those lines go with it
                    &mdash; they leave the Bill of Materials screen, the material demand figures and the
                    costing table. Rebuilding them by hand is a long job.</div>
            </div>
            @endif

            @if($estimate->status === 'Approved')
            <div class="wap-banner" style="margin-top:16px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This quotation was <strong>Approved</strong> &mdash; the client accepted it. It is the
                    record of what was agreed. If the job changed, a revised estimate keeps the history;
                    removing this one erases what was signed off.</div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>The estimate is <strong>archived, not erased</strong> &mdash; kept with a removal date,
                    so it can be restored and the code <strong>{{ $estimate->ext_id }}</strong> is never
                    reused. No invoice or ledger entry is affected: this module never posted any.</div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.estimates.destroy', ['role' => $waRole, 'estimate' => $estimate]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $estimate->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.estimates', ['role' => $waRole, 'section' => 'quotations']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
