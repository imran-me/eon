{{--
    Wood Art · Procurement — the vendor form, for BOTH new and edit.
--}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($vendor) && $vendor;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($vendor->{$f} ?? $fallback) : $fallback);
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => 'vendors']) }}">
        <i class="bi bi-arrow-left"></i> Back to Vendors</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.procurement.vendors.update', ['role' => request()->route('role'), 'vendor' => $vendor])
            : route('role.woodart.procurement.vendors.store',  ['role' => request()->route('role')]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-shop"></i> Vendor Details</h3>
                <span class="wap-card-sub">
                    @if($editing) {{ $vendor->ext_id }} &mdash; the code cannot change
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

                @if($editing)
                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>Orders record a supplier by <strong>name</strong>, not by code. Renaming this
                        vendor will stop their existing orders rolling up to them until those orders
                        are updated too.</div>
                </div>
                @endif

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-name">Vendor Name</label>
                        <input id="wa-name" name="name" type="text" required maxlength="160"
                               class="wap-input {{ $errors->has('name') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('name') }}" placeholder="e.g. Haji Enterprise">
                        @error('name')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-category">Supplies</label>
                        <select id="wa-category" name="category" class="wap-input">
                            @foreach($categories as $c)
                            <option value="{{ $c }}" @selected($v('category', 'General') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-terms">Payment Terms</label>
                        <select id="wa-terms" name="terms" class="wap-input">
                            <option value="">Not set</option>
                            @foreach($terms as $t)
                            <option value="{{ $t }}" @selected($v('terms') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-contact">Contact Person</label>
                        <input id="wa-contact" name="contact" type="text" maxlength="160"
                               class="wap-input {{ $errors->has('contact') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('contact') }}">
                        @error('contact')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-phone">Phone</label>
                        <input id="wa-phone" name="phone" type="text" maxlength="40"
                               class="wap-input {{ $errors->has('phone') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('phone') }}">
                        @error('phone')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-email">Email</label>
                        <input id="wa-email" name="email" type="email" maxlength="160"
                               class="wap-input {{ $errors->has('email') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('email') }}">
                        @error('email')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-area">Area</label>
                        <input id="wa-area" name="area" type="text" maxlength="120"
                               class="wap-input {{ $errors->has('area') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('area') }}" placeholder="e.g. Old Dhaka">
                        @error('area')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-since">Supplier Since</label>
                        <input id="wa-since" name="since" type="date"
                               class="wap-input {{ $errors->has('since') ? 'wap-input-bad' : '' }}"
                               value="{{ old('since', $editing && $vendor->since ? $vendor->since->format('Y-m-d') : '') }}">
                        @error('since')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Save Vendor' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.procurement', ['role' => request()->route('role'), 'section' => 'vendors']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.procurement.vendors.delete', ['role' => request()->route('role'), 'vendor' => $vendor]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
