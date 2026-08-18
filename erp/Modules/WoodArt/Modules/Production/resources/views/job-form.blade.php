{{--
    Wood Art · Workshop — the job form, for BOTH new and edit.
    A page, not a modal (no <script> inside [data-wa-view]).
--}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($job) && $job;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($job->{$f} ?? $fallback) : $fallback);
    $waRole = request()->route('role');

    // A job may point at a project that has since been removed. The picker must
    // still show that value, flagged — silently dropping it would rewrite the
    // job's history the next time anyone pressed Save (decision W3).
    $current = (string) $v('project');
    $orphan  = $current !== '' && ! \array_key_exists($current, $projects);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.production', ['role' => $waRole, 'section' => 'jobs']) }}">
        <i class="bi bi-arrow-left"></i> Back to Jobs</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.production.update', ['role' => $waRole, 'job' => $job])
            : route('role.woodart.production.store',  ['role' => $waRole]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-hammer"></i> Job Details</h3>
                <span class="wap-card-sub">
                    @if($editing) {{ $job->ext_id }} &mdash; the code cannot change
                    @else Reference {{ $nextExt }} will be assigned on save @endif
                </span>
            </div>
            <div class="wap-card-body">

                @if($errors->any())
                <div class="wap-empty-sub wap-error" style="margin-bottom:16px">
                    Please correct the {{ $errors->count() }} highlighted
                    {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} below.
                </div>
                @endif

                @if($orphan)
                <div class="wap-banner" style="margin-bottom:18px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                    <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                    <div>This job points at project <strong>{{ $current }}</strong>, which no longer
                        exists. The job is kept and the reference preserved &mdash; pick a live project
                        to re-attach it, or leave it as it is.</div>
                </div>
                @endif

                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>Marking a job <strong>Done</strong> records that it finished. It does
                        <strong>not</strong> consume stock or move the project's progress &mdash; stock
                        needs the movements ledger, and progress belongs to the project.</div>
                </div>

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-job">What is being made</label>
                        <input id="wa-job" name="job" type="text" required maxlength="160"
                               class="wap-input {{ $errors->has('job') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('job') }}" placeholder="e.g. Wardrobe shutters">
                        @error('job')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-project">For Project</label>
                        <select id="wa-project" name="project" class="wap-input">
                            <option value="">General workshop work</option>
                            @if($orphan)
                            <option value="{{ $current }}" selected>{{ $current }} — no longer exists</option>
                            @endif
                            @foreach($projects as $ext => $label)
                            <option value="{{ $ext }}" @selected($current === $ext)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="wap-hint">Blank means floor work not tied to one job.</span>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-station">Station</label>
                        <select id="wa-station" name="station" class="wap-input">
                            @foreach($stations as $s)
                            <option value="{{ $s }}" @selected($v('station', 'CNC') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-status">Status</label>
                        <select id="wa-status" name="status" class="wap-input">
                            @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected($v('status', 'Queued') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-crew">Assigned To</label>
                        <input id="wa-crew" name="assigned_to" type="text" maxlength="160"
                               list="wa-crew-list"
                               class="wap-input {{ $errors->has('assigned_to') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('assigned_to') }}">
                        {{-- Suggestions only — the field stays free text so a new
                             hand can be put on a job before anyone registers them. --}}
                        <datalist id="wa-crew-list">
                            @foreach($crew as $person)<option value="{{ $person }}"></option>@endforeach
                        </datalist>
                        @error('assigned_to')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-due">Due Date</label>
                        <input id="wa-due" name="due" type="date"
                               class="wap-input {{ $errors->has('due') ? 'wap-input-bad' : '' }}"
                               value="{{ old('due', $editing && $job->due ? $job->due->format('Y-m-d') : '') }}">
                        <span class="wap-hint">Leave blank if there is no date yet &mdash; an undated job is never counted overdue.</span>
                        @error('due')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Put on the Floor' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.production', ['role' => $waRole, 'section' => 'jobs']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.production.delete', ['role' => $waRole, 'job' => $job]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
