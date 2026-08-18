{{--
    Wood Art · Materials — the material form, used for BOTH new and edit.
    A page, not a modal (no <script> inside [data-wa-view]). Writes one row to
    wa_materials and nothing else.
--}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($material) && $material;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($material->{$f} ?? $fallback) : $fallback);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.materials', ['role' => request()->route('role'), 'section' => 'stock']) }}">
        <i class="bi bi-arrow-left"></i> Back to Stock</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.materials.update', ['role' => request()->route('role'), 'material' => $material])
            : route('role.woodart.materials.store',  ['role' => request()->route('role')]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-boxes"></i> Material Details</h3>
                <span class="wap-card-sub">
                    @if($editing)
                        {{ $material->ext_id }} &mdash; the code cannot change
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

                {{-- The rule this form is temporarily breaking, stated plainly
                     so it is not forgotten when the ledger arrives. --}}
                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>Stock is set directly here for now. Once the movements ledger is built, a
                        stock figure will only change by recording a receipt, issue, adjustment or
                        wastage &mdash; so every number can be explained by the movements behind it.</div>
                </div>

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-name">Material Name</label>
                        <input id="wa-name" name="name" type="text" required maxlength="160"
                               class="wap-input {{ $errors->has('name') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('name') }}" placeholder="e.g. Marine Plywood 18mm">
                        @error('name')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-category">Category</label>
                        <select id="wa-category" name="category" class="wap-input">
                            @foreach($categories as $c)
                            <option value="{{ $c }}" @selected($v('category', 'Board') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-unit">Unit</label>
                        <select id="wa-unit" name="unit" class="wap-input">
                            @foreach($units as $un)
                            <option value="{{ $un }}" @selected($v('unit', 'pcs') === $un)>{{ $un }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-stock">In Stock</label>
                        {{-- step="any" is half of a double lock with the
                             `decimal:0,3` rule: step="1" makes the BROWSER
                             refuse to submit 12.5 before the request is even
                             sent, so server-side validation never sees it. --}}
                        <input id="wa-stock" name="stock" type="number" step="any"
                               class="wap-input {{ $errors->has('stock') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('stock', 0) }}">
                        <span class="wap-hint">Fractions are allowed, up to 3 decimals (12.5 kg). A negative is allowed &mdash; it records more issued than held.</span>
                        @error('stock')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-reorder">Reorder Level</label>
                        <input id="wa-reorder" name="reorder" type="number" min="0" step="any"
                               class="wap-input {{ $errors->has('reorder') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('reorder', 0) }}">
                        <span class="wap-hint">Fractions are allowed (2.5 sheets). Leave at 0 for no reorder alert.</span>
                        @error('reorder')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-cost">Unit Cost (৳)</label>
                        <input id="wa-cost" name="unit_cost" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('unit_cost') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('unit_cost', 0) }}">
                        @error('unit_cost')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-supplier">Supplier</label>
                        <input id="wa-supplier" name="supplier" type="text" maxlength="160"
                               class="wap-input {{ $errors->has('supplier') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('supplier') }}">
                        @error('supplier')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Save Material' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.materials', ['role' => request()->route('role'), 'section' => 'stock']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.materials.delete', ['role' => request()->route('role'), 'material' => $material]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
