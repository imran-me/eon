{{--
    Wood Art · Clients — the client form, used for BOTH new and edit.

    A page, not a modal: no <script> may run inside [data-wa-view] (CLAUDE.md),
    and a plain form needs none. It writes one row to wa_clients and nothing
    else.
--}}
@extends('woodart::layouts.suite')

@php
    $editing = isset($client) && $client;
    $v = fn (string $f, $fallback = '') => old($f, $editing ? ($client->{$f} ?? $fallback) : $fallback);
    $d = fn (string $f) => old($f, $editing && $client->{$f} ? $client->{$f}->format('Y-m-d') : '');
@endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.clients', ['role' => request()->route('role'), 'section' => 'directory']) }}">
        <i class="bi bi-arrow-left"></i> Back to Directory</a>
@endsection

@section('wa-view')

    <form method="POST" action="{{ $editing
            ? route('role.woodart.clients.update', ['role' => request()->route('role'), 'client' => $client])
            : route('role.woodart.clients.store',  ['role' => request()->route('role')]) }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="wap-card">
            <div class="wap-card-head">
                <h3><i class="bi bi-person-hearts"></i> Client Details</h3>
                <span class="wap-card-sub">
                    @if($editing)
                        {{ $client->ext_id }} &mdash; the code cannot change
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

                @if($editing)
                {{-- Renaming is allowed but has a consequence worth stating: a
                     project stores its client's NAME, not this row's id. --}}
                <div class="wap-banner" style="margin-bottom:18px">
                    <i class="bi bi-info-circle"></i>
                    <div>Projects record a client by <strong>name</strong>, not by code. Renaming this
                        client will stop their existing projects rolling up to them until those
                        projects are updated too.</div>
                </div>
                @endif

                <div class="wap-form-grid">

                    <div class="wap-field wap-field-wide">
                        <label class="wap-label" for="wa-name">Client Name</label>
                        <input id="wa-name" name="name" type="text" required maxlength="160"
                               class="wap-input {{ $errors->has('name') ? 'wap-input-bad' : '' }}"
                               value="{{ $v('name') }}" placeholder="e.g. Munshi Billah">
                        @error('name')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-type">Segment</label>
                        <select id="wa-type" name="type" class="wap-input">
                            @foreach($types as $t)
                            <option value="{{ $t }}" @selected($v('type', 'Homeowner') === $t)>{{ $t }}</option>
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
                               value="{{ $v('area') }}" placeholder="e.g. Gulshan, Dhaka">
                        @error('area')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="wap-field">
                        <label class="wap-label" for="wa-since">Client Since</label>
                        <input id="wa-since" name="since" type="date"
                               class="wap-input {{ $errors->has('since') ? 'wap-input-bad' : '' }}"
                               value="{{ $d('since') }}">
                        @error('since')<span class="wap-error">{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wap-form-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">
                        <i class="bi bi-check-lg"></i> {{ $editing ? 'Save Changes' : 'Save Client' }}</button>
                    <a class="wap-btn wap-btn-ghost"
                       href="{{ route('role.woodart.clients', ['role' => request()->route('role'), 'section' => 'directory']) }}">Cancel</a>

                    @if($editing)
                    <a class="wap-btn wap-btn-ghost" style="margin-left:auto"
                       href="{{ route('role.woodart.clients.delete', ['role' => request()->route('role'), 'client' => $client]) }}">
                        <i class="bi bi-trash3"></i> Remove</a>
                    @endif
                </div>

            </div>
        </div>
    </form>

@endsection
