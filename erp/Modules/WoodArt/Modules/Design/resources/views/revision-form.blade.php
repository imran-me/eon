{{--
    Wood Art · Design & 3D — append one entry to a drawing's trail.

    This is the only screen that writes a revision, and it never edits or
    deletes one. Saving here does TWO things on purpose: it appends the audit
    entry AND brings the deliverable's own revision/status in step, so the
    trail and the drawing can never disagree.
--}}
@extends('woodart::layouts.suite')

@php $waRole = request()->route('role'); @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.design.edit', ['role' => $waRole, 'drawing' => $drawing]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $drawing->ext_id }}</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ route('role.woodart.design.revision.store', ['role' => $waRole, 'drawing' => $drawing]) }}">
        @csrf

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-arrow-repeat"></i> {{ $drawing->title }}</h3>
                <span class="wap-card-sub">
                    {{ $drawing->ext_id }} &middot; currently rev {{ $drawing->rev }} &middot; {{ $drawing->status }}
                    &middot; entry {{ $nextExt }} will be assigned
                </span>
            </div>
            <div class="wap-card-body">

                @if($errors->any())
                <div class="wap-empty-sub wap-error" style="margin-bottom:16px">
                    Please correct the {{ $errors->count() }} highlighted
                    {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} below.
                </div>
                @endif

                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>The action decides what happens to the drawing:
                        <strong>Drafted</strong> and <strong>Revised</strong> put it back to Draft,
                        <strong>Issued</strong> sends it to the client and starts the waiting clock,
                        <strong>Commented</strong> brings it back to us, and
                        <strong>Approved</strong> closes it. Entries are never edited or removed &mdash;
                        a mistake is corrected by appending another.</div>
                </div>

                <div class="wap-form-grid">

                    <div class="wap-field">
                        <label class="wap-label" for="wa-rev">Revision Letter</label>
                        <input id="wa-rev" name="rev" type="text" required maxlength="4"
                               class="wap-input {{ $errors->has('rev') ? 'wap-input-bad' : '' }}"
                               value="{{ old('rev', $drawing->next_rev) }}" style="max-width:90px">
                        <span class="wap-hint">Currently {{ $drawing->rev }}. Keep it for a comment or approval; bump it for a re-issue.</span>
                        @error('rev')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-action">Action</label>
                        <select id="wa-action" name="action" class="wap-input">
                            @foreach($actions as $a)
                            <option value="{{ $a }}" @selected(old('action', 'Issued') === $a)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-by">By</label>
                        <input id="wa-by" name="by" type="text" maxlength="160"
                               class="wap-input {{ $errors->has('by') ? 'wap-input-bad' : '' }}"
                               value="{{ old('by', $drawing->designer) }}">
                        @error('by')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-date">Date</label>
                        <input id="wa-date" name="date" type="date"
                               class="wap-input {{ $errors->has('date') ? 'wap-input-bad' : '' }}"
                               value="{{ old('date', now()->toDateString()) }}">
                        @error('date')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-note">Note</label>
                        <input id="wa-note" name="note" type="text" maxlength="500"
                               class="wap-input {{ $errors->has('note') ? 'wap-input-bad' : '' }}"
                               value="{{ old('note') }}"
                               placeholder="e.g. Client asked for deeper shelves in the master wardrobe">
                        <span class="wap-hint">What changed, or what the client said. This is the part that matters later.</span>
                        @error('note')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> Append Entry</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.design', ['role' => $waRole, 'section' => 'register']) }}">Cancel</a>
                </div>

            </div>
        </div>
    </form>

    @if($trail->isNotEmpty())
    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-clock-history"></i> Existing Trail</h3>
            <span class="wap-card-sub">{{ $trail->count() }} {{ \Illuminate\Support\Str::plural('entry', $trail->count()) }}, oldest first</span>
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
