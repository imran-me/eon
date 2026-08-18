<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-Factor Authentication — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { font-family: 'Instrument Sans', sans-serif; background: #f3f4f6; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem 2rem; max-width: 420px; width: 100%; margin: auto; }
        .btn { display: block; width: 100%; padding: .75rem; background: #4f46e5; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background .2s; }
        .btn:hover { background: #4338ca; }
        input[type="text"] { width: 100%; padding: .7rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1.25rem; letter-spacing: .3em; text-align: center; outline: none; box-sizing: border-box; margin-top: .4rem; }
        input[type="text"]:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.15); }
        .error { color: #dc2626; font-size: .875rem; margin-top: .4rem; }
    </style>
</head>
<body style="min-height:100vh; display:flex; align-items:center; justify-content:center;">

<div class="card">
    <div style="text-align:center; margin-bottom:1.75rem;">
        <div style="font-size:2.5rem; margin-bottom:.5rem;">🔐</div>
        <h1 style="font-size:1.4rem; font-weight:700; color:#111827; margin:0;">Two-Factor Verification</h1>
        <p style="color:#6b7280; font-size:.9rem; margin-top:.5rem;">
            Open your authenticator app and enter the 6-digit code.
        </p>
    </div>

    @if ($errors->any())
        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:.75rem 1rem; margin-bottom:1rem;">
            <p class="error" style="margin:0;">{{ $errors->first() }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.verify.post') }}">
        @csrf
        <div style="margin-bottom:1.25rem;">
            <label style="font-size:.875rem; font-weight:600; color:#374151;">Authentication Code</label>
            <input type="text"
                   name="code"
                   maxlength="6"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   autofocus
                   placeholder="000000"
                   value="{{ old('code') }}">
            @error('code')
                <p class="error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn">Verify & Continue</button>
    </form>

    <div style="text-align:center; margin-top:1.25rem;">
        <a href="{{ route('login') }}" style="font-size:.85rem; color:#6b7280; text-decoration:none;">
            ← Back to Login
        </a>
    </div>
</div>

</body>
</html>
