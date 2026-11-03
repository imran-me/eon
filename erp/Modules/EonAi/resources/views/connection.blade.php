@extends('layout.app')

@section('meta-information')
    <title>EON Connection</title>
@endsection

@section('css')
    @include('eonai::partials.styles')
@endsection

{{--
  The connection point with the external EON server. This page stores EON's
  address and a shared key and can run a signed handshake. It sends NO ERP
  data — reading data is a later, separate step with its own guardrails.
--}}

@section('main-content')
@php
    $status = ! $row?->server_url ? 'off'
        : ($row->last_test_ok === null ? 'wait' : ($row->last_test_ok ? 'ok' : 'err'));
    $statusText = [
        'off'  => 'Not configured',
        'wait' => 'Not tested yet',
        'ok'   => 'Connected',
        'err'  => 'Connection failed',
    ][$status];
    $newKey = session('eai_new_key');
@endphp
<div class="eai-wrap">
    <div class="eai-head">
        <div class="eai-title">
            <div class="eai-logo"><i class="fas fa-plug"></i></div>
            <div>
                <h1>EON Connection</h1>
                <p>Link this ERP to the EON assistant on its own server. EON's code and brain stay on its server.</p>
            </div>
        </div>
        <div class="eai-row">
            <span class="eai-pill {{ $status }}"><i class="fas fa-circle" style="font-size:7px"></i> {{ $statusText }}</span>
            <a href="{{ route('role.eon-ai.index', ['role' => $role]) }}" class="eai-btn"><i class="fas fa-robot"></i> EON AI</a>
        </div>
    </div>

    @unless($installed)
        <div class="eai-alert warn"><i class="fas fa-triangle-exclamation"></i>
            <div>EON AI is not installed on this server yet — its settings table does not exist. Run
                <code>php artisan migrate --path=Modules/EonAi/Database/Migrations</code>, then reload.</div>
        </div>
    @endunless

    @if(session('eai_ok'))
        <div class="eai-alert ok"><i class="fas fa-circle-check"></i><div>{{ session('eai_ok') }}</div></div>
    @endif
    @if(session('eai_error'))
        <div class="eai-alert err"><i class="fas fa-circle-exclamation"></i><div>{{ session('eai_error') }}</div></div>
    @endif
    @if($errors->any())
        <div class="eai-alert err"><i class="fas fa-circle-exclamation"></i><div>{{ $errors->first() }}</div></div>
    @endif

    {{-- 1 · Server --}}
    <div class="eai-card">
        <div class="eai-card-h"><h2><i class="fas fa-server" style="color:#6366f1"></i> 1 · EON server</h2></div>
        <div class="eai-card-b">
            <form method="POST" action="{{ route('role.eon-ai.connection.save', ['role' => $role]) }}">
                @csrf
                <div class="eai-grid">
                    <div>
                        <label class="eai-label" for="eai_url">Server address</label>
                        <input id="eai_url" name="server_url" type="text" class="eai-input" placeholder="https://eon.example.com/server"
                               value="{{ old('server_url', $row->server_url ?? '') }}" @disabled(! $installed)>
                        <div class="eai-hint">The base address of EON's server. The ERP calls <b>{{ $path }}</b> under it. Must be https.</div>
                    </div>
                    <div>
                        <span class="eai-label">Access</span>
                        <label class="eai-toggle">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $row->enabled ?? false)) @disabled(! $installed)>
                            Allow EON to connect
                        </label>
                        <div class="eai-hint">The master switch. Off means EON is refused everything, instantly — whatever else is set here.</div>
                    </div>
                </div>
                <div class="eai-row" style="margin-top:14px">
                    <button type="submit" class="eai-btn primary" @disabled(! $installed)><i class="fas fa-floppy-disk"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 2 · Shared key --}}
    <div class="eai-card">
        <div class="eai-card-h"><h2><i class="fas fa-key" style="color:#d97706"></i> 2 · Shared key</h2>
            @if($masked)<span class="eai-hint" style="margin:0">Current key: <b>{{ $masked }}</b></span>@endif
        </div>
        <div class="eai-card-b">
            @if($newKey)
                <label class="eai-label">Your new key — copy it into EON's settings now. It will not be shown again.</label>
                <div class="eai-row" style="align-items:stretch">
                    <div class="eai-key" id="eaiNewKey" style="flex:1">{{ $newKey }}</div>
                    <button type="button" class="eai-btn" onclick="navigator.clipboard.writeText(document.getElementById('eaiNewKey').textContent.trim()).then(function(){ this.innerHTML='<i class=&quot;fas fa-check&quot;></i> Copied'; }.bind(this))"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <div style="height:14px"></div>
            @endif
            <div class="eai-grid">
                <form method="POST" action="{{ route('role.eon-ai.connection.key', ['role' => $role]) }}"
                      onsubmit="return {{ $masked ? "confirm('Replace the current key? EON stops connecting until it has the new one.')" : 'true' }}">
                    @csrf
                    <input type="hidden" name="mode" value="generate">
                    <span class="eai-label">Generate here</span>
                    <button type="submit" class="eai-btn" @disabled(! $installed)><i class="fas fa-wand-magic-sparkles"></i> {{ $masked ? 'Generate a new key' : 'Generate key' }}</button>
                    <div class="eai-hint">A random 64-character key. Put the same key in EON — both sides sign with it, so neither can be impersonated.</div>
                </form>
                <form method="POST" action="{{ route('role.eon-ai.connection.key', ['role' => $role]) }}">
                    @csrf
                    <input type="hidden" name="mode" value="paste">
                    <label class="eai-label" for="eai_key">…or use EON's existing key</label>
                    <div class="eai-row">
                        <input id="eai_key" name="key" type="password" class="eai-input" style="flex:1" placeholder="Paste key (32+ characters)" autocomplete="off" @disabled(! $installed)>
                        <button type="submit" class="eai-btn" @disabled(! $installed)><i class="fas fa-check"></i> Save</button>
                    </div>
                    <div class="eai-hint">Stored encrypted. Never shown again in full.</div>
                </form>
            </div>
        </div>
    </div>

    {{-- 3 · Test --}}
    <div class="eai-card">
        <div class="eai-card-h"><h2><i class="fas fa-satellite-dish" style="color:#059669"></i> 3 · Test the connection</h2>
            <form method="POST" action="{{ route('role.eon-ai.connection.test', ['role' => $role]) }}" style="margin:0">
                @csrf
                <button type="submit" class="eai-btn primary" @disabled(! $installed || ! ($row->server_url ?? null) || ! $masked)><i class="fas fa-bolt"></i> Test connection</button>
            </form>
        </div>
        <div class="eai-card-b">
            <dl class="eai-kv">
                <dt>Status</dt><dd><span class="eai-pill {{ $status }}">{{ $statusText }}</span></dd>
                <dt>Last tested</dt><dd>{{ $row?->last_tested_at ? \Carbon\Carbon::parse($row->last_tested_at)->format('d M Y, h:i A') : '—' }}</dd>
                <dt>Result</dt><dd style="font-weight:500">{{ $row->last_test_message ?? '—' }}</dd>
                <dt>EON version</dt><dd>{{ $row->eon_version ?? '—' }}</dd>
                <dt>Access switch</dt><dd>{{ ($row->enabled ?? false) ? 'On — EON may connect' : 'Off — EON is refused' }}</dd>
            </dl>
            <div class="eai-hint" style="margin-top:12px">The test sends no ERP data — only a signed "hello". EON must answer with its own proof of the key.</div>
        </div>
    </div>

    {{-- 4 · What EON must answer --}}
    <div class="eai-card">
        <div class="eai-card-h"><h2><i class="fas fa-code" style="color:#475569"></i> 4 · For the EON side</h2></div>
        <div class="eai-card-b">
            <ol class="eai-steps">
                <li>Save the same shared key in EON's own settings.</li>
                <li>Add a handshake route on EON's server:</li>
            </ol>
<pre class="eai-code">GET {server address}{{ $path }}
Headers sent by the ERP:
  X-Erp-Id:        epal-erp
  X-Erp-Timestamp: &lt;unix seconds&gt;
  X-Erp-Signature: hex HMAC-SHA256(key, "&lt;timestamp&gt;\nGET\n{{ $path }}\n" + sha256(""))

EON checks: signature matches, and timestamp is within 5 minutes of now.
EON answers 200 with JSON:
  { "ok": true, "app": "EON", "version": "1.0",
    "proof": hex HMAC-SHA256(key, "&lt;the same timestamp&gt;\nEON-OK") }
A wrong signature → 401.</pre>
        </div>
    </div>
</div>
@endsection
