{{--
    Wood Art · Site & Install — the site visit form, for BOTH new and edit.
    A page, not a modal (no <script> inside [data-wa-view]).
--}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($install) && $install;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($install->{$f} ?? $fallback) : $fallback);
    $waRole = request()->route('role');

    // A visit may point at a project that has since been removed. The picker
    // must still show that value, flagged — silently dropping it would rewrite
    // the visit's history the next time anyone pressed Save (decision I8).
    $current = (string) $v('project');
    $orphan  = $current !== '' && ! \array_key_exists($current, $projects);

    // When a record carries an itemised snag list, the stored count is derived
    // from it on save, so the plain number field would be overwritten. Say so
    // rather than letting someone type into a field that will not stick.
    $itemised = $editing && is_array($install->snag_list) && $install->snag_list !== [];
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.installation', ['role' => $waRole, 'section' => 'schedule']) }}">
        <i class="bi bi-arrow-left"></i> Back to Schedule</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.installation.update', ['role' => $waRole, 'install' => $install])
            : route('role.woodart.installation.store',  ['role' => $waRole]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-truck"></i> Site Visit Details</h3>
                <span class="wap-card-sub">
                    @if($editing) {{ $install->ext_id }} &mdash; the code cannot change
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
                    <div>This visit points at project <strong>{{ $current }}</strong>, which no longer
                        exists. The visit is kept and the reference preserved &mdash; pick a live project
                        to re-attach it, or leave it as it is.</div>
                </div>
                @endif

                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>Marking a visit <strong>Handover</strong> records that the site was handed over.
                        It does <strong>not</strong> raise an invoice &mdash; billing belongs to the
                        project, and a second path here would bill the same job twice.</div>
                </div>

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-site">Site</label>
                        <input id="wa-site" name="site" type="text" required maxlength="160"
                               class="wap-input {{ $errors->has('site') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('site') }}" placeholder="e.g. Gulshan-2, House 41">
                        @error('site')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-project">Project</label>
                        <select id="wa-project" name="project" class="wap-input">
                            <option value="">Not linked to a project</option>
                            @if($orphan)
                            <option value="{{ $current }}" selected>{{ $current }} — no longer exists</option>
                            @endif
                            @foreach($projects as $ext => $label)
                            <option value="{{ $ext }}" @selected($current === $ext)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-team">Team</label>
                        <input id="wa-team" name="team" type="text" maxlength="120"
                               list="wa-team-list"
                               class="wap-input {{ $errors->has('team') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('team') }}" placeholder="e.g. Install Team 1">
                        {{-- Suggestions only, drawn from teams already named on
                             real visits. No invented crew names — see
                             InstallationController::teamOptions(). --}}
                        <datalist id="wa-team-list">
                            @foreach($crews as $crew)<option value="{{ $crew }}"></option>@endforeach
                        </datalist>
                        @error('team')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-status">Status</label>
                        <select id="wa-status" name="status" class="wap-input">
                            @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected($v('status', 'Scheduled') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-date">Visit Date</label>
                        <input id="wa-date" name="date" type="date"
                               class="wap-input {{ $errors->has('date') ? 'wap-input-bad' : '' }}"
                               value="{{ old('date', $editing && $install->date ? $install->date->format('Y-m-d') : '') }}">
                        <span class="wap-hint">Leave blank if unscheduled &mdash; an undated visit is never counted overdue.</span>
                        @error('date')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-snags">Open Snags</label>
                        <input id="wa-snags" name="snags" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('snags') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('snags', 0) }}" @disabled($itemised)>
                        <span class="wap-hint">
                            @if($itemised)
                                This visit has an itemised snag list, so the count is derived from it and cannot be typed here.
                            @else
                                How many faults are still outstanding. The handover queue is ordered by this.
                            @endif
                        </span>
                        @error('snags')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Schedule Visit' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.installation', ['role' => $waRole, 'section' => 'schedule']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.installation.delete', ['role' => $waRole, 'install' => $install]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
