{{-- Wood Art · Spaces & Phases — space delete confirmation. --}}
@extends('woodart::layouts.suite')

@php $waRole = request()->route('role'); @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.scope.spaces.edit', ['role' => $waRole, 'space' => $space]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $space->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this space?</h3>
            <span class="wap-card-sub">Check what is attached before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $space->name }}</div>
                        <div class="wap-proj-ext">{{ $space->ext_id }} &middot; {{ $space->project }}</div>
                    </div>
                    <span class="wap-badge">{{ $space->kind }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Area</div>
                        <div class="wap-proj-stat-value">{{ $space->area ? number_format($space->area) . ' sft' : '—' }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Phases</div>
                        <div class="wap-proj-stat-value">{{ number_format($attached->count()) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Complete</div>
                        <div class="wap-proj-stat-value">{{ number_format($attached->where('status', 'Complete')->count()) }}</div>
                    </div>
                </div>
            </div>

            @if($attached->count() > 0)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This room has <strong>{{ number_format($attached->count()) }}</strong>
                    {{ \Illuminate\Support\Str::plural('phase', $attached->count()) }}, of which
                    <strong>{{ number_format($attached->where('status', 'Complete')->count()) }}</strong>
                    {{ $attached->where('status', 'Complete')->count() === 1 ? 'is' : 'are' }} already complete.
                    Those phases are <strong>not</strong> deleted &mdash; the work really was planned and in
                    many cases done. They stay on the board showing this room as removed, until a space
                    with the code <strong>{{ $space->ext_id }}</strong> exists again.</div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>The space is <strong>archived, not erased</strong> &mdash; kept with a removal date, so
                    it can be restored and the code <strong>{{ $space->ext_id }}</strong> is never reused.</div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.scope.spaces.destroy', ['role' => $waRole, 'space' => $space]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $space->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.scope', ['role' => $waRole, 'section' => 'spaces']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
