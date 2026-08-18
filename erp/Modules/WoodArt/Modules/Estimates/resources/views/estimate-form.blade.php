{{--
    Wood Art · Estimates — the quotation form and its bill of quantities,
    for BOTH new and edit. A page, not a modal.

    THE LINE EDITOR IS PLAIN HTML. No script may live inside [data-wa-view]
    (woodart-nav.js replaces that region wholesale), so rows are not added by
    JavaScript: the form renders every existing line plus a few blanks, and
    clearing a line's Item removes it on save. Needing more rows than the
    blanks provided means saving and reopening — the honest cost of the rule.
--}}
@extends('woodart::layouts.suite')

@php
    use Modules\WoodArt\Modules\Projects\Models\Project;

    $editing = isset($estimate) && $estimate;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($estimate->{$f} ?? $fallback) : $fallback);
    $waRole = request()->route('role');

    // old('lines') wins after a failed validation so nothing typed is lost.
    $waRows = old('lines', $rows);

    $currentProject = (string) $v('project_ext');
    $orphanProject  = $currentProject !== '' && ! \array_key_exists($currentProject, $projects);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.estimates', ['role' => $waRole, 'section' => 'quotations']) }}">
        <i class="bi bi-arrow-left"></i> Back to Quotations</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.estimates.update', ['role' => $waRole, 'estimate' => $estimate])
            : route('role.woodart.estimates.store',  ['role' => $waRole]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-file-earmark-text"></i> Quotation</h3>
                <span class="wap-card-sub">
                    @if($editing) {{ $estimate->ext_id }} &mdash; the code cannot change
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
                    <div>This estimate quotes project <strong>{{ $currentProject }}</strong>, which no
                        longer exists. The reference is preserved &mdash; pick a live project to
                        re-attach it, or leave it as it is.</div>
                </div>
                @endif

                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>Marking an estimate <strong>Approved</strong> records that the client accepted
                        it. It does <strong>not</strong> raise an invoice or post anything to the books
                        &mdash; billing belongs to the project.</div>
                </div>

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-title">Title</label>
                        <input id="wa-title" name="title" type="text" required maxlength="200"
                               class="wap-input {{ $errors->has('title') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('title') }}" placeholder="e.g. Munshi Villa Duplex — bill of quantities">
                        @error('title')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-client">Client</label>
                        <input id="wa-client" name="client" type="text" maxlength="160"
                               list="wa-client-list"
                               class="wap-input {{ $errors->has('client') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('client') }}">
                        <datalist id="wa-client-list">
                            @foreach($clients as $c)<option value="{{ $c }}"></option>@endforeach
                        </datalist>
                        <span class="wap-hint">Matching the client register exactly keeps their history together.</span>
                        @error('client')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-project">Project</label>
                        <select id="wa-project" name="project_ext" class="wap-input">
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
                        <label class="wap-label" for="wa-status">Status</label>
                        <select id="wa-status" name="status" class="wap-input">
                            @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected($v('status', 'Draft') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-valid">Valid Till</label>
                        <input id="wa-valid" name="valid_till" type="date"
                               class="wap-input {{ $errors->has('valid_till') ? 'wap-input-bad' : '' }}"
                               value="{{ old('valid_till', $editing && $estimate->valid_till ? $estimate->valid_till->format('Y-m-d') : '') }}">
                        <span class="wap-hint">An approved or rejected quote is never flagged expired, whatever the date.</span>
                        @error('valid_till')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>
            </div>
        </div>

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-list-ul"></i> Bill of Quantities</h3>
                <span class="wap-card-sub">
                    {{ $editing ? $estimate->line_count : 0 }} existing
                    {{ \Illuminate\Support\Str::plural('line', $editing ? $estimate->line_count : 0) }},
                    plus blank rows &mdash; clear an Item to remove that line
                </span>
            </div>
            <div class="wap-card-body">

                <div class="wap-banner" style="margin-bottom:16px">
                    <i class="bi bi-info-circle"></i>
                    <div>Rows with an empty <strong>Item</strong> are ignored on save, so a blank row costs
                        nothing and clearing an Item deletes that line. Need more rows than are shown?
                        Save, then reopen &mdash; blanks are added each time.</div>
                </div>

                <div class="wap-table-wrap">
                    <table class="wap-table">
                        <thead>
                            <tr>
                                <th style="min-width:150px">Trade / Code</th>
                                <th style="min-width:220px">Item</th>
                                <th>Kind</th><th>Unit</th>
                                <th class="wap-t-num">Qty</th>
                                <th class="wap-t-num">Unit Cost</th>
                                <th class="wap-t-num">Unit Sale</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($waRows as $n => $row)
                            <tr>
                                <td>
                                    <input type="text" maxlength="120" class="wap-input"
                                           name="lines[{{ $n }}][code]" value="{{ $row['code'] ?? '' }}"
                                           aria-label="Trade for line {{ $n + 1 }}">
                                </td>
                                <td>
                                    <input type="text" maxlength="200" class="wap-input"
                                           list="wa-material-list"
                                           name="lines[{{ $n }}][item]" value="{{ $row['item'] ?? '' }}"
                                           aria-label="Item for line {{ $n + 1 }}">
                                </td>
                                <td>
                                    <select name="lines[{{ $n }}][kind]" class="wap-input"
                                            aria-label="Kind for line {{ $n + 1 }}">
                                        @foreach($kinds as $k)
                                        <option value="{{ $k }}" @selected(($row['kind'] ?? 'material') === $k)>{{ $k }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" maxlength="20" class="wap-input" style="max-width:90px"
                                           list="wa-unit-list"
                                           name="lines[{{ $n }}][unit]" value="{{ $row['unit'] ?? '' }}"
                                           aria-label="Unit for line {{ $n + 1 }}">
                                </td>
                                <td>
                                    <input type="number" step="any" min="0" class="wap-input" style="max-width:110px"
                                           name="lines[{{ $n }}][qty]" value="{{ $row['qty'] ?? '' }}"
                                           aria-label="Quantity for line {{ $n + 1 }}">
                                </td>
                                <td>
                                    <input type="number" step="1" min="0" class="wap-input" style="max-width:130px"
                                           name="lines[{{ $n }}][unitCost]" value="{{ $row['unitCost'] ?? '' }}"
                                           aria-label="Unit cost for line {{ $n + 1 }}">
                                </td>
                                <td>
                                    <input type="number" step="1" min="0" class="wap-input" style="max-width:130px"
                                           name="lines[{{ $n }}][unitSale]" value="{{ $row['unitSale'] ?? '' }}"
                                           aria-label="Unit sale for line {{ $n + 1 }}">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Shared suggestion lists, declared once for every row. --}}
                <datalist id="wa-material-list">
                    @foreach($materials as $m)<option value="{{ $m }}"></option>@endforeach
                </datalist>
                <datalist id="wa-unit-list">
                    @foreach($units as $u)<option value="{{ $u }}"></option>@endforeach
                </datalist>

                @if($editing)
                <div class="wap-proj-stats" style="margin-top:18px">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Cost</div>
                        <div class="wap-proj-stat-value">{{ Project::money($estimate->cost) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Sale</div>
                        <div class="wap-proj-stat-value">{{ Project::money($estimate->sale) }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Margin</div>
                        <div class="wap-proj-stat-value">{{ Project::money($estimate->margin) }} ({{ $estimate->margin_pct }}%)</div>
                    </div>
                </div>
                <span class="wap-hint">These are the saved figures &mdash; they update when you save.</span>
                @endif

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Create Estimate' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.estimates', ['role' => $waRole, 'section' => 'quotations']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.estimates.delete', ['role' => $waRole, 'estimate' => $estimate]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
