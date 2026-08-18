{{--
    Wood Art · Procurement — the purchase order form, for BOTH new and edit.
    A page, not a modal (no <script> inside [data-wa-view]).
--}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($order) && $order;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($order->{$f} ?? $fallback) : $fallback);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => 'orders']) }}">
        <i class="bi bi-arrow-left"></i> Back to Orders</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.procurement.orders.update', ['role' => request()->route('role'), 'order' => $order])
            : route('role.woodart.procurement.orders.store',  ['role' => request()->route('role')]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-receipt"></i> Order Details</h3>
                <span class="wap-card-sub">
                    @if($editing) {{ $order->ext_id }} &mdash; the code cannot change
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

                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>Marking an order <strong>Received</strong> records that it arrived. It does
                        <strong>not</strong> add to stock yet &mdash; that needs the movements ledger, so
                        every stock figure can be explained by the movements behind it.</div>
                </div>

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-supplier">Supplier</label>
                        <input id="wa-supplier" name="supplier" type="text" required maxlength="160"
                               list="wa-vendor-list"
                               class="wap-input {{ $errors->has('supplier') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('supplier') }}" placeholder="e.g. Haji Enterprise">
                        {{-- A datalist suggests registered vendors without forcing one: the
                             reference allows an order against a supplier not yet in the register,
                             and the Spend screen reports those separately rather than losing them. --}}
                        <datalist id="wa-vendor-list">
                            @foreach($vendors as $vend)<option value="{{ $vend->name }}"></option>@endforeach
                        </datalist>
                        <span class="wap-hint">Orders link to a vendor by name &mdash; matching the register exactly keeps the spend roll-up together.</span>
                        @error('supplier')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-project">For Project</label>
                        <select id="wa-project" name="project" class="wap-input">
                            <option value="">General stock</option>
                            @foreach($projects as $p)
                            <option value="{{ $p->ext_id }}" @selected($v('project') === $p->ext_id)>
                                {{ $p->ext_id }} — {{ \Illuminate\Support\Str::limit($p->name, 40) }}</option>
                            @endforeach
                        </select>
                        <span class="wap-hint">Blank means buying for the store, not a job.</span>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-status">Status</label>
                        <select id="wa-status" name="status" class="wap-input">
                            @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected($v('status', 'Ordered') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-items">Item Count</label>
                        <input id="wa-items" name="items" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('items') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('items', 0) }}">
                        @error('items')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-amount">Order Value (৳)</label>
                        <input id="wa-amount" name="amount" type="number" min="0" step="1"
                               class="wap-input {{ $errors->has('amount') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('amount', 0) }}">
                        @error('amount')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-date">Order Date</label>
                        <input id="wa-date" name="date" type="date"
                               class="wap-input {{ $errors->has('date') ? 'wap-input-bad' : '' }}"
                               value="{{ old('date', $editing && $order->date ? $order->date->format('Y-m-d') : now()->toDateString()) }}">
                        @error('date')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Raise Order' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => 'orders']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.procurement.orders.delete', ['role' => request()->route('role'), 'order' => $order]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
