{{-- Wood Art · Spaces & Phases — the space form, for BOTH new and edit. --}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($space) && $space;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($space->{$f} ?? $fallback) : $fallback);
    $waRole = request()->route('role');

    $currentProject = (string) $v('project');
    $orphanProject  = $currentProject !== '' && ! \array_key_exists($currentProject, $projects);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.scope', ['role' => $waRole, 'section' => 'spaces']) }}">
        <i class="bi bi-arrow-left"></i> Back to Spaces</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.scope.spaces.update', ['role' => $waRole, 'space' => $space])
            : route('role.woodart.scope.spaces.store',  ['role' => $waRole]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-door-open"></i> Space Details</h3>
                <span class="wap-card-sub">
                    @if($editing) {{ $space->ext_id }} &mdash; the code cannot change, phases point at it
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

                @if($orphanProject)
                <div class="wap-banner" style="margin-bottom:18px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                    <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                    <div>This space belongs to project <strong>{{ $currentProject }}</strong>, which no
                        longer exists. The reference is preserved &mdash; pick a live project, or leave
                        it as it is.</div>
                </div>
                @endif

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-name">Room Name</label>
                        <input id="wa-name" name="name" type="text" required maxlength="120"
                               class="wap-input {{ $errors->has('name') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('name') }}" placeholder="e.g. Master Bed Room">
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
                        <label class="wap-label" for="wa-kind">Room Type</label>
                        <input id="wa-kind" name="kind" type="text" required maxlength="40"
                               list="wa-kind-list"
                               class="wap-input {{ $errors->has('kind') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('kind', 'Common') }}">
                        <datalist id="wa-kind-list">
                            @foreach($kinds as $k)<option value="{{ $k }}"></option>@endforeach
                        </datalist>
                        <span class="wap-hint">Suggestions only &mdash; any type can be typed.</span>
                        @error('kind')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-area">Area (sft)</label>
                        <input id="wa-area" name="area" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('area') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('area', 0) }}">
                        <span class="wap-hint">0 means not measured yet.</span>
                        @error('area')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-sort">Order</label>
                        <input id="wa-sort" name="sort" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('sort') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('sort', 1) }}">
                        <span class="wap-hint">Where this room sits in the project's running order.</span>
                        @error('sort')<span class="wap-error">{{ $message }}</span>@enderror
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
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Add Space' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.scope', ['role' => $waRole, 'section' => 'spaces']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.scope.spaces.delete', ['role' => $waRole, 'space' => $space]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
