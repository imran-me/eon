{{-- Wood Art · Design & 3D — drawing delete confirmation. --}}
@extends('woodart::layouts.suite')

@php
    $waRole = request()->route('role');

    // Built here rather than with an inline @if. Blade only compiles a
    // directive that is NOT preceded by a word character, so `days@endif`
    // is left as literal text — which either prints raw Blade on the page or,
    // when the opening @if did compile, kills the view with a parse error.
    $waWaited = $drawing->waiting_days !== null
        ? ', and has been for ' . $drawing->waiting_days . ' days'
        : '';
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.design.edit', ['role' => $waRole, 'drawing' => $drawing]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $drawing->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this drawing?</h3>
            <span class="wap-card-sub">Check what is attached before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $drawing->title }}</div>
                        <div class="wap-proj-ext">
                            {{ $drawing->ext_id }} &middot; {{ $drawing->kind }}
                            @if($drawing->project) &middot; {{ $drawing->project }} @endif
                        </div>
                    </div>
                    <span class="wap-badge {{ $drawing->status === 'Approved' ? 'wap-badge-good' : ($drawing->status === 'Commented' ? 'wap-badge-bad' : ($drawing->status === 'Issued' ? 'wap-badge-warn' : '')) }}">
                        {{ $drawing->status }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Revision</div>
                        <div class="wap-proj-stat-value">{{ $drawing->rev }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Trail</div>
                        <div class="wap-proj-stat-value">{{ number_format($trail->count()) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Designer</div>
                        <div class="wap-proj-stat-value">{{ $drawing->designer ?: '—' }}</div>
                    </div>
                </div>
            </div>

            @if($trail->count() > 0)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This deliverable has <strong>{{ number_format($trail->count()) }}</strong> revision
                    {{ \Illuminate\Support\Str::plural('entry', $trail->count()) }} behind it. Those are
                    <strong>kept</strong>, not deleted &mdash; they are the record of what was issued to and
                    agreed with the client, which is exactly the history that matters if anyone later
                    disagrees about what was approved.</div>
            </div>
            @endif

            @if($drawing->status === 'Approved')
            <div class="wap-banner" style="margin-top:16px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This drawing is <strong>Approved</strong> &mdash; the client signed it off. It is also
                    counted towards its project's design phase being complete; removing it changes that
                    picture.</div>
            </div>
            @elseif($drawing->is_waiting)
            <div class="wap-banner" style="margin-top:16px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>This drawing is <strong>with the client</strong>{{ $waWaited }}.
                    Removing it takes it off the approvals queue &mdash; but they are still holding it.</div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>The drawing is <strong>archived, not erased</strong> &mdash; kept with a removal date, so
                    it can be restored and the code <strong>{{ $drawing->ext_id }}</strong> is never reused.
                    No file is deleted: this module stores records, not drawings.</div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.design.destroy', ['role' => $waRole, 'drawing' => $drawing]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $drawing->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.design', ['role' => $waRole, 'section' => 'register']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
