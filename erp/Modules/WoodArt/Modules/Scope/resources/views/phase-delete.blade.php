{{-- Wood Art · Spaces & Phases — phase delete confirmation. --}}
@extends('woodart::layouts.suite')

@php
    use Modules\WoodArt\Modules\Projects\Models\Project;
    $waRole = request()->route('role');
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.scope.phases.edit', ['role' => $waRole, 'phase' => $phase]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $phase->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this phase?</h3>
            <span class="wap-card-sub">Check what is attached before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $phase->name }}</div>
                        <div class="wap-proj-ext">
                            {{ $phase->ext_id }} &middot;
                            {{ $spaceName ?: ($phase->space ? $phase->space . ' (removed)' : 'whole project') }}
                            &middot; {{ $phase->project }}
                        </div>
                    </div>
                    <span class="wap-badge {{ $phase->status === 'Complete' ? 'wap-badge-good' : ($phase->status === 'Active' ? 'wap-badge-warn' : '') }}">
                        {{ $phase->status }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Trade</div>
                        <div class="wap-proj-stat-value">{{ $phase->code ?: '—' }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Finish</div>
                        <div class="wap-proj-stat-value">{{ $phase->finish?->format('d M Y') ?: '—' }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Requirements</div>
                        <div class="wap-proj-stat-value">{{ number_format($needs->count()) }}</div>
                    </div>
                </div>
            </div>

            @if($needs->count() > 0)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This phase has <strong>{{ number_format($needs->count()) }}</strong>
                    {{ \Illuminate\Support\Str::plural('requirement', $needs->count()) }} planned against it,
                    costed at <strong>{{ Project::money((int) $needs->sum('line_cost')) }}</strong>.
                    Those are <strong>not</strong> deleted &mdash; they stay as the record of what was
                    planned. They will point at a phase that no longer exists until one with the code
                    <strong>{{ $phase->ext_id }}</strong> is created again.</div>
            </div>
            @endif

            @if($phase->status === 'Complete')
            <div class="wap-banner" style="margin-top:16px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This phase is marked <strong>Complete</strong> &mdash; it is the record that this stage
                    of the work was finished. Removing it erases that history.</div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>The phase is <strong>archived, not erased</strong> &mdash; kept with a removal date, so
                    it can be restored and the code <strong>{{ $phase->ext_id }}</strong> is never reused.
                    No money moves: this module posts nothing to any ledger.</div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.scope.phases.destroy', ['role' => $waRole, 'phase' => $phase]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $phase->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.scope', ['role' => $waRole, 'section' => 'phases']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
