@extends('layout.app')

@section('meta-information')
    <title>Site Settings</title>
@endsection

@section('css')
<style>
    .site-settings-shell {
        display: grid;
        grid-template-columns: 1.15fr .85fr;
        gap: 20px;
    }

    .site-settings-card,
    .site-settings-preview {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
    }

    .site-settings-card-header,
    .site-settings-preview-header {
        padding: 18px 20px 0;
    }

    .site-settings-card-body,
    .site-settings-preview-body {
        padding: 20px;
    }

    .site-settings-label {
        display: block;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
    }

    .site-settings-input {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 14px;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }

    .site-settings-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    }

    .site-settings-preview-box {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 140px;
        border: 1px dashed #d1d5db;
        border-radius: 16px;
        background: linear-gradient(135deg, #f8fafc, #eff6ff);
        overflow: hidden;
    }

    .site-settings-preview-box img {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
    }

    .site-settings-note {
        font-size: 12px;
        color: #6b7280;
        margin-top: 6px;
    }

    @media (max-width: 1024px) {
        .site-settings-shell { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('main-content')
@php
    $role = Str::slug(Auth::user()->getRoleNames()->first());
    $logoUrl = site_setting_url('logo', asset('image/company/69cb5771cc187.png'));
    $faviconUrl = site_setting_url('favicon', asset('airplane-ticket.png'));
@endphp

<div class="site-settings-shell">
    <div class="site-settings-card">
        <div class="site-settings-card-header">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Site Settings</h1>
                    <p class="text-sm text-gray-500 mt-1">Update the global app name, logo, and favicon.</p>
                </div>
            </div>
        </div>

        <div class="site-settings-card-body">
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('role.site-settings.update', ['role' => $role]) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="app_name" class="site-settings-label">Application Name</label>
                    <input id="app_name" name="app_name" type="text" value="{{ old('app_name', $siteSettings['app_name'] ?? config('app.name')) }}" class="site-settings-input" placeholder="Enter app name">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="logo" class="site-settings-label">Logo</label>
                        <input id="logo" name="logo" type="file" accept="image/*" class="site-settings-input bg-white">
                        <p class="site-settings-note">Recommended: transparent PNG or SVG.</p>
                    </div>

                    <div>
                        <label for="favicon" class="site-settings-label">Favicon</label>
                        <input id="favicon" name="favicon" type="file" accept="image/*,.ico" class="site-settings-input bg-white">
                        <p class="site-settings-note">Recommended: 32x32 PNG or ICO.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="site-settings-preview">
        <div class="site-settings-preview-header">
            <h2 class="text-lg font-bold text-gray-900">Preview</h2>
            <p class="text-sm text-gray-500 mt-1">Current branding loaded from the database.</p>
        </div>
        <div class="site-settings-preview-body space-y-5">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400 mb-2">Logo</div>
                <div class="site-settings-preview-box p-4">
                    <img src="{{ $logoUrl }}" alt="Site logo">
                </div>
            </div>

            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400 mb-2">Favicon</div>
                <div class="site-settings-preview-box p-4">
                    <img src="{{ $faviconUrl }}" alt="Site favicon" style="max-height:72px;">
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400 mb-2">App Name</div>
                <div class="text-xl font-extrabold text-gray-900">{{ $siteSettings['app_name'] ?? config('app.name') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection