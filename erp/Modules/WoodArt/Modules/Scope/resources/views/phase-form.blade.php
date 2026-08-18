{{-- Wood Art · Spaces & Phases — the phase form, for BOTH new and edit. --}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($phase) && $phase;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($phase->{$f} ?? $fallback) : $fallback);
    $waRole = request()->route('role');

    $currentProject = (string) $v('project');
    $orphanProject  = $currentProject !== '' && ! \array_key_exists($currentProject, $projects);

    $currentSpace = (string) $v('space');
    $spaceCodes   = $spaceList->pluck('ext_id')->all();
    $orphanSpace  = $currentSpace !== '' && ! \in_array($currentSpace, $spaceCodes, true);

    $currentOwner = (string) $v('owner_id');
    $orphanOwner  = $currentOwner !== '' && ! \array_key_exists($currentOwner, $people);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.scope', ['role' => $waRole, 'section' => 'phases']) }}">
        <i class="bi bi-arrow-left"></i> Back to the Board</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.scope.phases.update', ['role' => $waRole, 'phase' => $phase])
            : route('role.woodart.scope.phases.store',  ['role' => $waRole]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-diagram-3"></i> Phase Details</h3>
                <span class="wap-card-sub">
                    @if($editing) {{ $phase->ext_id }} &mdash; the code cannot change
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

                @if($orphanSpace || $orphanProject || $orphanOwner)
                <div class="wap-banner" style="margin-bottom:18px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                    <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                    <div>
                        @if($orphanProject) This phase belongs to project <strong>{{ $currentProject }}</strong>, which no longer exists. @endif
                        @if($orphanSpace) Its space <strong>{{ $currentSpace }}</strong> no longer exists. @endif
                        @if($orphanOwner) Its owner code <strong>{{ $currentOwner }}</strong> matches nobody in the register. @endif
                        Every reference is preserved &mdash; re-point it below, or leave it as it is.
                    </div>
                </div>
                @endif

                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>Marking a phase <strong>Complete</strong> records that this stage finished. It does
                        <strong>not</strong> move the project's progress bar &mdash; that stays the project's
                        own number, set on the project.</div>
                </div>

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-name">Phase</label>
                        <input id="wa-name" name="name" type="text" required maxlength="120"
                               class="wap-input {{ $errors->has('name') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('name') }}" placeholder="e.g. Wood Work">
                        @error('name')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-project">Project</label>
                        <select id="wa-project" name="project" class="wap-input" required>
                            <option value="">Choose a project…</option>
                            @if($orphanProject)
                            <option value="{{ $currentProject }}" selected>{{ $currentProject }} — no longer exists</option>
                            @endif
                            @foreach($projects as $ext => $label)
                            <option value="{{ $ext }}" @selected($currentProject === $ext)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('project')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-space">Space</label>
                        <select id="wa-space" name="space" class="wap-input">
                            <option value="">Whole project — not one room</option>
                            @if($orphanSpace)
                            <option value="{{ $currentSpace }}" selected>{{ $currentSpace }} — no longer exists</option>
                            @endif
                            @foreach($spaceList as $s)
                            <option value="{{ $s->ext_id }}" @selected($currentSpace === $s->ext_id)>
                                {{ $s->ext_id }} — {{ $s->name }} ({{ $s->project }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-code">Trade</label>
                        <input id="wa-code" name="code" type="text" maxlength="60"
                               list="wa-trade-list"
                               class="wap-input {{ $errors->has('code') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('code') }}" placeholder="e.g. Wood Work">
                        <datalist id="wa-trade-list">
                            @foreach($trades as $t)<option value="{{ $t }}"></option>@endforeach
                        </datalist>
                        <span class="wap-hint">Matches the bill of quantities' trade names.</span>
                        @error('code')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-status">Status</label>
                        <select id="wa-status" name="status" class="wap-input">
                            @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected($v('status', 'Not started') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-owner">Owner</label>
                        <select id="wa-owner" name="owner_id" class="wap-input">
                            <option value="">Unassigned</option>
                            @if($orphanOwner)
                            <option value="{{ $currentOwner }}" selected>{{ $currentOwner }} — unknown code</option>
                            @endif
                            @foreach($people as $code => $name)
                            <option value="{{ $code }}" @selected($currentOwner === $code)>{{ $name }} ({{ $code }})</option>
                            @endforeach
                        </select>
                        <span class="wap-hint">Wood Art's own people only.</span>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-sort">Order</label>
                        <input id="wa-sort" name="sort" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('sort') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('sort', 0) }}">
                        <span class="wap-hint">Where this phase sits in the room's sequence.</span>
                        @error('sort')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-start">Start</label>
                        <input id="wa-start" name="start" type="date"
                               class="wap-input {{ $errors->has('start') ? 'wap-input-bad' : '' }}"
                               value="{{ old('start', $editing && $phase->start ? $phase->start->format('Y-m-d') : '') }}">
                        @error('start')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-finish">Finish</label>
                        <input id="wa-finish" name="finish" type="date"
                               class="wap-input {{ $errors->has('finish') ? 'wap-input-bad' : '' }}"
                               value="{{ old('finish', $editing && $phase->finish ? $phase->finish->format('Y-m-d') : '') }}">
                        <span class="wap-hint">Leave blank if undated &mdash; an undated phase is never counted overdue.</span>
                        @error('finish')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-note">Note</label>
                        <input id="wa-note" name="note" type="text" maxlength="5000"
                               class="wap-input {{ $errors->has('note') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('note') }}">
                        @error('note')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Add Phase' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.scope', ['role' => $waRole, 'section' => 'phases']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.scope.phases.delete', ['role' => $waRole, 'phase' => $phase]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
