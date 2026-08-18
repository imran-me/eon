<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup 2FA — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { font-family: 'Instrument Sans', sans-serif; background: #f3f4f6; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem 2rem; max-width: 480px; width: 100%; margin: auto; }
        .btn { display: block; width: 100%; padding: .75rem; background: #4f46e5; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background .2s; }
        .btn:hover { background: #4338ca; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        input[type="text"] { width: 100%; padding: .7rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1.25rem; letter-spacing: .3em; text-align: center; outline: none; box-sizing: border-box; margin-top: .4rem; }
        input[type="text"]:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.15); }
        .error { color: #dc2626; font-size: .875rem; margin-top: .4rem; }
        .secret-box { background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px; padding: .75rem 1rem; font-family: monospace; font-size: 1rem; letter-spacing: .15em; color: #374151; text-align: center; word-break: break-all; margin: .75rem 0; }
        .qr-wrap { display: flex; justify-content: center; margin: 1rem 0; }
        .step { display: flex; gap: .75rem; align-items: flex-start; margin-bottom: .75rem; }
        .step-num { background: #4f46e5; color: #fff; border-radius: 50%; width: 1.5rem; height: 1.5rem; display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; flex-shrink: 0; }
        .step-text { font-size: .9rem; color: #374151; line-height: 1.5; }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }
        .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:.75rem 1rem; color:#166534; font-size:.875rem; margin-bottom:1rem; }
        .alert-info { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:.75rem 1rem; color:#1e40af; font-size:.875rem; margin-bottom:1rem; }
    </style>
</head>
<body style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding: 2rem 1rem;">

<div class="card">
    <div style="text-align:center; margin-bottom:1.5rem;">
        <div style="font-size:2rem; margin-bottom:.4rem;">🔒</div>
        <h1 style="font-size:1.35rem; font-weight:700; color:#111827; margin:0;">Two-Factor Authentication</h1>
        <p style="color:#6b7280; font-size:.875rem; margin-top:.4rem;">Secure your account with Google Authenticator</p>
    </div>

    @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if (session('info'))
        <div class="alert-info">{{ session('info') }}</div>
    @endif

    {{-- Show if 2FA is already enabled --}}
    @if (Auth::user()->two_factor_confirmed_at)
        <div style="text-align:center; padding:1rem 0;">
            <div style="font-size:2rem;">✅</div>
            <p style="font-weight:600; color:#166534; margin:.5rem 0;">2FA is currently <strong>enabled</strong></p>
            <p style="color:#6b7280; font-size:.875rem;">To disable it, confirm with your current OTP below.</p>
        </div>

        <hr class="divider">

        <form method="POST" action="{{ route('two-factor.disable') }}">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="font-size:.875rem; font-weight:600; color:#374151;">Enter OTP to Disable</label>
                <input type="text" name="code" maxlength="6" inputmode="numeric" placeholder="000000" value="{{ old('code') }}">
                @error('code') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn btn-danger">Disable 2FA</button>
        </form>

    @else
        {{-- Setup steps --}}
        <div style="margin-bottom:1.25rem;">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text">Install <strong>Google Authenticator</strong> on your phone (Android / iOS).</div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">Tap <strong>+</strong> → <em>Scan a QR code</em> and scan the code below.</div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">Enter the 6-digit code shown in the app to confirm setup.</div>
            </div>
        </div>

        @if($qrCodeSvg)
        <div class="qr-wrap">
            {!! $qrCodeSvg !!}
        </div>

        <p style="font-size:.8rem; color:#6b7280; text-align:center; margin:.25rem 0 .5rem;">
            Can't scan? Enter this key manually:
        </p>
        <div class="secret-box">{{ chunk_split($secret, 4, ' ') }}</div>
        @endif

        <hr class="divider">

        @if ($errors->any())
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:.75rem 1rem; margin-bottom:1rem;">
                <p class="error" style="margin:0;">{{ $errors->first() }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="font-size:.875rem; font-weight:600; color:#374151;">Confirmation Code</label>
                <input type="text" name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" autofocus placeholder="000000" value="{{ old('code') }}">
                @error('code') <p class="error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn">Enable 2FA</button>
        </form>
    @endif

    <div style="text-align:center; margin-top:1.25rem;">
        <a href="javascript:history.back()" style="font-size:.85rem; color:#6b7280; text-decoration:none;">← Go Back</a>
    </div>
</div>

</body>
</html>
