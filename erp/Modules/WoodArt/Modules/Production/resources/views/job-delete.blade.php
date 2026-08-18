{{-- Wood Art · Workshop — job delete confirmation. --}}
@extends('woodart::layouts.suite')

@php
    $waRole = request()->route('role');
    $orphan = $job->project && ! \array_key_exists($job->project, $projectNames);

    // Built here rather than with an inline @if. Blade only compiles a
    // directive that is NOT preceded by a word character, so `floor@if(…)` was
    // left as literal text and printed raw Blade onto the page.
    $waLate = $job->is_overdue ? ', and already past its due date' : '';
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.production.edit', ['role' => $waRole, 'job' => $job]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $job->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this job?</h3>
            <span class="wap-card-sub">Check this is the right job before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $job->job }}</div>
                        <div class="wap-proj-ext">
                            {{ $job->ext_id }} &middot;
                            @if(! $job->project)
                                General workshop work
                            @elseif($orphan)
                                {{ $job->project }} (no longer exists)
                            @else
                                {{ $job->project }} — {{ $projectNames[$job->project] }}
                            @endif
                        </div>
                    </div>
                    <span class="wap-badge {{ $job->status === 'Done' ? 'wap-badge-good' : ($job->status === 'Blocked' ? 'wap-badge-bad' : ($job->status === 'Running' ? 'wap-badge-warn' : '')) }}">
                        {{ $job->status }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Station</div>
                        <div class="wap-proj-stat-value">{{ $job->station }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Assigned</div>
                        <div class="wap-proj-stat-value">{{ $job->assigned_to ?: '—' }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Due</div>
                        <div class="wap-proj-stat-value">{{ $job->due?->format('d M Y') ?: '—' }}</div>
                    </div>
                </div>
            </div>

            @if($job->is_open)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This job is <strong>{{ $job->status }}</strong> &mdash; it is still open work on the
                    floor{{ $waLate }}. Removing it takes it
                    off the board and out of the station load. If the work was cancelled rather than
                    mistaken, that is worth recording before it goes.</div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>The job is <strong>archived, not erased</strong> &mdash; kept with a removal date, so
                    it can be restored and the code <strong>{{ $job->ext_id }}</strong> is never reused.
                    Nothing else changes: this module holds no stock and no money, so no other record
                    moves when a job goes.</div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.production.destroy', ['role' => $waRole, 'job' => $job]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $job->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.production', ['role' => $waRole, 'section' => 'jobs']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
