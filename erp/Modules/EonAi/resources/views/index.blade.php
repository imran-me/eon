@extends('layout.app')

@section('meta-information')
    <title>EON AI</title>
@endsection

@section('css')
    @include('eonai::partials.styles')
@endsection

{{--
  EON's home in the ERP — everything EON-related happens under /eon-ai. For now
  it shows the connection's state; EON's own workspace (served from the EON
  server) will be added here in a later step.
--}}

@section('main-content')
@php
    $connected = (bool) ($row?->last_test_ok) && (bool) ($row?->enabled);
@endphp
<div class="eai-wrap">
    <div class="eai-head">
        <div class="eai-title">
            <div class="eai-logo"><i class="fas fa-robot"></i></div>
            <div>
                <h1>EON AI</h1>
                <p>The EON assistant, connected from its own server.</p>
            </div>
        </div>
        <a href="{{ route('role.eon-ai.connection', ['role' => $role]) }}" class="eai-btn"><i class="fas fa-plug"></i> Connection settings</a>
    </div>

    @unless($installed)
        <div class="eai-alert warn"><i class="fas fa-triangle-exclamation"></i>
            <div>EON AI is not installed on this server yet. Run
                <code>php artisan migrate --path=Modules/EonAi/Database/Migrations</code>, then reload.</div>
        </div>
    @endunless

    <div class="eai-card">
        <div class="eai-card-h"><h2><i class="fas fa-link" style="color:#6366f1"></i> Connection</h2>
            @if($connected)
                <span class="eai-pill ok">Connected</span>
            @elseif(($row->last_test_ok ?? null) === false || ($row->last_test_ok ?? null) === 0)
                <span class="eai-pill err">Connection failed</span>
            @elseif($row?->server_url)
                <span class="eai-pill wait">{{ ($row->enabled ?? false) ? 'Not tested yet' : 'Switched off' }}</span>
            @else
                <span class="eai-pill off">Not configured</span>
            @endif
        </div>
        <div class="eai-card-b">
            <dl class="eai-kv">
                <dt>EON server</dt><dd>{{ $row->server_url ?? '—' }}</dd>
                <dt>Shared key</dt><dd>{{ $hasKey ? 'Set' : 'Not set' }}</dd>
                <dt>Access switch</dt><dd>{{ ($row->enabled ?? false) ? 'On' : 'Off' }}</dd>
                <dt>Last test</dt><dd style="font-weight:500">{{ $row->last_test_message ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="eai-card">
        <div class="eai-card-h"><h2><i class="fas fa-shield-halved" style="color:#059669"></i> What EON can do today</h2></div>
        <div class="eai-card-b">
            <ul class="eai-steps">
                <li>Answer the connection test. Nothing else — no ERP data is shared at this stage.</li>
                <li>Reading ERP data comes next, through a read-only door with its own guardrails: EON will never be able to edit or delete anything.</li>
                <li>EON's workspace will open on this page once that is in place.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
