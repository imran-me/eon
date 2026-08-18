{{-- Wood Art · Design & 3D — the drawing form, for BOTH new and edit. --}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($drawing) && $drawing;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($drawing->{$f} ?? $fallback) : $fallback);
    $waRole = request()->route('role');

    $currentProject = (string) $v('project');
    $orphanProject  = $currentProject !== '' && ! \array_key_exists($currentProject, $projects);
@endphp

@section('wa-head-actions')
    @if($editing)
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.design.revision.create', ['role' => $waRole, 'drawing' => $drawing]) }}">
        <i class="bi bi-arrow-repeat"></i> Log a Revision</a>
    @endif
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.design', ['role' => $waRole, 'section' => 'register']) }}">
        <i class="bi bi-arrow-left"></i> Back to Register</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.design.update', ['role' => $waRole, 'drawing' => $drawing])
            : route('role.woodart.design.store',  ['role' => $waRole]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-vector-pen"></i> Drawing Details</h3>
                <span class="wap-card-sub">
                    @if($editing) {{ $drawing->ext_id }} &mdash; the code cannot change, its trail points at it
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
                    <div>This drawing belongs to project <strong>{{ $currentProject }}</strong>, which no
                        longer exists. The reference is preserved &mdash; pick a live project, or leave it.</div>
                </div>
                @endif

                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>This records the <strong>deliverable</strong>, not the file &mdash; no drawing or render
                        is stored here. Changing the status directly does not add a trail entry;
                        <strong>Log a Revision</strong> does both, and is what leaves an audit record.</div>
                </div>

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-title">Title</label>
                        <input id="wa-title" name="title" type="text" required maxlength="200"
                               class="wap-input {{ $errors->has('title') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('title') }}" placeholder="e.g. Master wardrobe detail">
                        @error('title')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-kind">Type</label>
                        <select id="wa-kind" name="kind" class="wap-input">
                            @foreach($kinds as $k)
                            <option value="{{ $k }}" @selected($v('kind', 'Plan') === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-project">Project</label>
                        <select id="wa-project" name="project" class="wap-input">
                            <option value="">Not linked to a project</option>
                            @if($orphanProject)
                            <option value="{{ $currentProject }}" selected>{{ $currentProject }} — no longer exists</option>
                            @endif
                            @foreach($projects as $ext => $label)
                            <option value="{{ $ext }}" @selected($currentProject === $ext)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-designer">Designer</label>
                        <input id="wa-designer" name="designer" type="text" maxlength="160"
                               list="wa-designer-list"
                               class="wap-input {{ $errors->has('designer') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('designer') }}">
                        <datalist id="wa-designer-list">
                            @foreach($designers as $p)<option value="{{ $p }}"></option>@endforeach
                        </datalist>
                        @error('designer')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-rev">Revision</label>
                        <input id="wa-rev" name="rev" type="text" required maxlength="4"
                               class="wap-input {{ $errors->has('rev') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('rev', 'A') }}" style="max-width:90px">
                        <span class="wap-hint">A is the first draft. B means revised once, and so on.</span>
                        @error('rev')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-status">Status</label>
                        <select id="wa-status" name="status" class="wap-input">
                            @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected($v('status', 'Draft') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-issued">Issued</label>
                        <input id="wa-issued" name="issued" type="date"
                               class="wap-input {{ $errors->has('issued') ? 'wap-input-bad' : '' }}"
                               value="{{ old('issued', $editing && $drawing->issued ? $drawing->issued->format('Y-m-d') : '') }}">
                        <span class="wap-hint">When it went to the client. The waiting clock runs from here.</span>
                        @error('issued')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-approved">Approved</label>
                        <input id="wa-approved" name="approved" type="date"
                               class="wap-input {{ $errors->has('approved') ? 'wap-input-bad' : '' }}"
                               value="{{ old('approved', $editing && $drawing->approved ? $drawing->approved->format('Y-m-d') : '') }}">
                        @error('approved')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Add Drawing' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.design', ['role' => $waRole, 'section' => 'register']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.design.delete', ['role' => $waRole, 'drawing' => $drawing]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

    @if($editing && $trail->isNotEmpty())
    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-clock-history"></i> Revision Trail</h3>
            <span class="wap-card-sub">Oldest first &mdash; appended, never edited</span>
        </div>
        <div class="wap-card-body">
            <div class="wap-table-wrap">
                <table class="wap-table">
                    <thead>
                        <tr><th>Entry</th><th class="wap-t-num">Rev</th><th>Action</th><th>By</th><th>Date</th><th>Note</th></tr>
                    </thead>
                    <tbody>
                        @foreach($trail as $r)
                        <tr>
                            <td class="wap-t-strong">{{ $r->ext_id }}</td>
                            <td class="wap-t-num">{{ $r->rev }}</td>
                            <td><span class="wap-badge {{ $r->action === 'Approved' ? 'wap-badge-good' : ($r->action === 'Commented' ? 'wap-badge-bad' : '') }}">{{ $r->action }}</span></td>
                            <td>{{ $r->by ?: '—' }}</td>
                            <td>{{ $r->date?->format('d M Y') ?: '—' }}</td>
                            <td>{{ $r->note ?: '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

@endsection
