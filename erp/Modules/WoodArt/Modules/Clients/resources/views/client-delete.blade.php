{{--
    Wood Art · Clients — the delete confirmation.

    A page rather than a JS confirm(), and it states what happens rather than
    asking "are you sure?" — including how much work is attached, which is the
    fact that should actually decide it.
--}}
@extends('woodart::layouts.suite')

@php use Modules\WoodArt\Modules\Projects\Models\Project; @endphp

@section('wa-head-actions')
    <a class="wap-btn wap-btn-ghost"
       href="{{ route('role.woodart.clients.edit', ['role' => request()->route('role'), 'client' => $client]) }}">
        <i class="bi bi-arrow-left"></i> Back to {{ $client->ext_id }}</a>
@endsection

@section('wa-view')

    <div class="wap-card">
        <div class="wap-card-head">
            <h3><i class="bi bi-exclamation-triangle"></i> Remove this client?</h3>
            <span class="wap-card-sub">Check what is attached before confirming</span>
        </div>
        <div class="wap-card-body">

            <div class="wap-proj-card" style="max-width:520px">
                <div class="wap-proj-top">
                    <div style="min-width:0">
                        <div class="wap-proj-name">{{ $client->name }}</div>
                        <div class="wap-proj-ext">{{ $client->ext_id }} &middot; {{ $client->area ?: 'No area recorded' }}</div>
                    </div>
                    <span class="wap-badge">{{ $client->type }}</span>
                </div>
                <div class="wap-proj-stats">
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Projects</div>
                        <div class="wap-proj-stat-value">{{ $linked['projects'] }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Live</div>
                        <div class="wap-proj-stat-value">{{ $linked['live'] }}</div>
                    </div>
                    <div class="wap-proj-stat">
                        <div class="wap-proj-stat-label">Value</div>
                        <div class="wap-proj-stat-value">{{ Project::money($linked['value']) }}</div>
                    </div>
                </div>
            </div>

            @if($linked['projects'] > 0)
            <div class="wap-banner" style="margin-top:20px; background: rgba(244,183,64,.12); border-color: rgba(244,183,64,.4)">
                <i class="bi bi-exclamation-triangle" style="color:#9a6b00"></i>
                <div>
                    This client has <strong>{{ $linked['projects'] }}</strong>
                    {{ \Illuminate\Support\Str::plural('project', $linked['projects']) }}
                    worth <strong>{{ Project::money($linked['value']) }}</strong>.
                    Those projects are <strong>not</strong> deleted &mdash; the work really happened,
                    and removing the customer record must not rewrite the job history. They will
                    simply stop rolling up to a registered client on the Portfolio screen.
                </div>
            </div>
            @endif

            <div class="wap-banner" style="margin-top:16px">
                <i class="bi bi-info-circle"></i>
                <div>
                    The client is <strong>archived, not erased</strong> &mdash; the row is kept with a
                    removal date, so it can be restored and the code
                    <strong>{{ $client->ext_id }}</strong> is never reused.
                </div>
            </div>

            <div class="wap-form-actions">
                <form method="POST"
                      action="{{ route('role.woodart.clients.destroy', ['role' => request()->route('role'), 'client' => $client]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="wap-btn wap-btn-danger">
                        <i class="bi bi-trash3"></i> Yes, remove {{ $client->ext_id }}</button>
                </form>
                <a class="wap-btn wap-btn-ghost"
                   href="{{ route('role.woodart.clients', ['role' => request()->route('role'), 'section' => 'directory']) }}">Keep it</a>
            </div>

        </div>
    </div>

@endsection
