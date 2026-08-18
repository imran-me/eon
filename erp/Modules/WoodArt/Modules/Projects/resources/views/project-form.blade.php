{{--
    Wood Art · Projects — the project form, used for BOTH new and edit.

    A PAGE, not a modal. CLAUDE.md forbids <script> inside [data-wa-view], and a
    modal needs one; a plain form needs no JavaScript at all. It still arrives
    without a reload, because the nav script claims any path under woodart/.

    It writes one row to wa_projects and nothing else. No shared table is read
    or written, and no other company's data is reachable from here.

    $project is null when registering and the model when editing. Every field
    falls back old() → existing value → sensible default, so a failed validation
    redisplays what was typed rather than reverting to what was stored.
--}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($project) && $project;

    // old() first so a rejected submission redisplays what was typed, then the
    // stored value when editing, then the column's own default. Dates are
    // rendered Y-m-d because that is what <input type="date"> requires.
    $v = fn (string $field, $fallback = '') => old($field, $editing ? ($project->{$field} ?? $fallback) : $fallback);
    $d = fn (string $field) => old($field, $editing && $project->{$field} ? $project->{$field}->format('Y-m-d') : '');

    // A project saved under a stage or type that has since been retired would
    // otherwise be rewritten silently: the <select> finds no matching option,
    // the browser falls back to the first one, and saving changes the value
    // without anybody choosing to. Surface it instead.
    $goneStage = $editing && ! in_array($project->stage, $stages, true) ? $project->stage : null;
    $goneType  = $editing && ! in_array($project->type,  $types,  true) ? $project->type  : null;
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.projects', ['role' => request()->route('role'), 'section' => 'active']) }}">
        <i class="bi bi-arrow-left"></i> Back to Portfolio</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.projects.update', ['role' => request()->route('role'), 'project' => $project])
            : route('role.woodart.projects.store',  ['role' => request()->route('role')]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-easel2-fill"></i> Project Details</h3>
                <span class="wap-card-sub">
                    @if($editing)
                        {{ $project->ext_id }} &mdash; the code cannot change, other records point at it
                    @else
                        Reference {{ $nextExt }} will be assigned on save
                    @endif
                </span>
            </div>
            <div class="wap-card-body">

                @if($errors->any())
                <div class="wap-empty-sub wap-error" style="margin-bottom:16px">
                    Please correct the {{ $errors->count() }} highlighted
                    {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} below.
                </div>
                @endif

                @if($goneStage || $goneType)
                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        This project was saved with
                        @if($goneStage) a stage of <strong>{{ $goneStage }}</strong> @endif
                        @if($goneStage && $goneType) and @endif
                        @if($goneType) a type of <strong>{{ $goneType }}</strong> @endif
                        &mdash; no longer part of the workflow. Pick a current
                        {{ $goneStage && $goneType ? 'stage and type' : ($goneStage ? 'stage' : 'type') }}
                        below before saving, so the change is yours rather than the form's.
                    </div>
                </div>
                @endif

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-name">Project Name</label>
                        <input id="wa-name" name="name" type="text" required maxlength="200"
                               class="wap-input {{ $errors->has('name') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('name') }}" placeholder="e.g. Munshi Villa Duplex — full interior">
                        @error('name')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-client">Client</label>
                        <input id="wa-client" name="client" type="text" maxlength="160"
                               class="wap-input {{ $errors->has('client') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('client') }}">
                        @error('client')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-type">Type</label>
                        <select id="wa-type" name="type" class="wap-input">
                            @foreach($types as $t)
                            <option value="{{ $t }}" @selected($v('type', 'Residential') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-stage">Stage</label>
                        <select id="wa-stage" name="stage" class="wap-input">
                            @foreach($stages as $s)
                            <option value="{{ $s }}" @selected($v('stage', 'Design') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-area">Area (sft)</label>
                        <input id="wa-area" name="area" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('area') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('area') }}">
                        @error('area')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-value">Contract Value (৳)</label>
                        <input id="wa-value" name="value" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('value') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('value') }}">
                        <span class="wap-hint">Whole taka, no commas.</span>
                        @error('value')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-cost">Committed Cost (৳)</label>
                        <input id="wa-cost" name="cost" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('cost') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('cost') }}">
                        @error('cost')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-progress">Progress (%)</label>
                        <input id="wa-progress" name="progress" type="number" min="0" max="100" step="1"
                               class="wap-input {{ $errors->has('progress') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('progress', 0) }}">
                        @error('progress')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-designer">Designer</label>
                        <input id="wa-designer" name="designer" type="text" maxlength="160"
                               class="wap-input {{ $errors->has('designer') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('designer') }}">
                        @error('designer')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-start">Start Date</label>
                        <input id="wa-start" name="start" type="date"
                               class="wap-input {{ $errors->has('start') ? 'wap-input-bad' : '' }}"
                               value="{{ $d('start') }}">
                        @error('start')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-deadline">Deadline</label>
                        <input id="wa-deadline" name="deadline" type="date"
                               class="wap-input {{ $errors->has('deadline') ? 'wap-input-bad' : '' }}"
                               value="{{ $d('deadline') }}">
                        @error('deadline')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Save Project' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.projects', ['role' => request()->route('role'), 'section' => 'active']) }}">Cancel</a>

                    @if($editing)
                    {{-- Pushed to the far right: destructive actions should not
                         sit next to the primary one. --}}
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.projects.delete', ['role' => request()->route('role'), 'project' => $project]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
