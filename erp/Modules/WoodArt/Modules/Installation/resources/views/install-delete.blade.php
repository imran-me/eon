{{-- Wood Art · Site & Install — site visit delete confirmation. --}}
@extends('woodart::layouts.suite')

@php
    $waRole = request()->route('role');
    $orphan = $install->project && ! \array_key_exists($install->project, $projectNames);

    // Built here rather than with an inline @if. Blade only compiles a
    // directive that is NOT preceded by a word character, so `work@if(…)` was
    // left as literal text and printed raw Blade onto the page.
    $waLate = $install->is_overdue ? ', and already past its date' : '';
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.installation.edit', ['role' => $waRole, 'install' => $install]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $install->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this site visit?</h3>
            <span class="wap-card-sub">Check what is attached before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $install->site }}</div>
                        <div class="wap-proj-ext">
                            {{ $install->ext_id }} &middot;
                            @if(! $install->project)
                                Not linked to a project
                            @elseif($orphan)
                                {{ $install->project }} (no longer exists)
                            @else
                                {{ $install->project }} — {{ $projectNames[$install->project] }}
                            @endif
                        </div>
                    </div>
                    <span class="wap-badge {{ $install->status === 'Handover' ? 'wap-badge-good' : ($install->status === 'Snagging' ? 'wap-badge-bad' : ($install->status === 'In Progress' ? 'wap-badge-warn' : '')) }}">
                        {{ $install->status }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Team</div>
                        <div class="wap-proj-stat-value">{{ $install->team ?: '—' }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Date</div>
                        <div class="wap-proj-stat-value">{{ $install->date?->format('d M Y') ?: '—' }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Open Snags</div>
                        <div class="wap-proj-stat-value">{{ number_format($install->open_snags) }}</div>
                    </div>
                </div>
            </div>

            @if($install->open_snags > 0)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This site still carries <strong>{{ number_format($install->open_snags) }}</strong> open
                    {{ \Illuminate\Support\Str::plural('snag', $install->open_snags) }}. Removing the visit
                    takes them out of the handover queue &mdash; the faults do not stop existing because
                    the record went. If they were fixed, set the count to zero instead.</div>
            </div>
            @elseif($install->is_open)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This visit is <strong>{{ $install->status }}</strong> &mdash; still live work{{ $waLate }}.
                    Removing it takes it off the schedule and out of the team load.</div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>The visit is <strong>archived, not erased</strong> &mdash; kept with a removal date, so
                    it can be restored and the code <strong>{{ $install->ext_id }}</strong> is never reused.
                    No invoice is affected: this module never billed anything.</div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.installation.destroy', ['role' => $waRole, 'install' => $install]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $install->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.installation', ['role' => $waRole, 'section' => 'schedule']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
