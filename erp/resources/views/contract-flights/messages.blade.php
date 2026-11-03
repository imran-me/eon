@extends('layout.app')
@section('meta-information')<title>Messages</title>@endsection
@section('css')
<style>
    /* Messages — the Contract Flight communication desk. cfm- prefixed, own page only. */
    .cfm-wrap { display: flex; flex-direction: column; gap: 14px; }
    .cfm-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
    .cfm-head .ic { display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 10px; background: #ecfdf5; color: #059669; font-size: 18px; flex-shrink: 0; }
    .cfm-head h1 { margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; }
    .cfm-head p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
    .cfm-head .sp { flex: 1; }
    .cfm-stat { text-align: right; padding: 0 12px; border-left: 1px solid #e2e8f0; }
    .cfm-stat b { display: block; font-size: 18px; font-weight: 900; color: #0f172a; line-height: 1; }
    .cfm-stat small { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 700; }
    .cfm-stat.bad b { color: #dc2626; }
    .cfm-notice { padding: 10px 14px; border-radius: 10px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 12.5px; }

    .cfm-tabs { display: flex; gap: 4px; padding: 6px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
    .cfm-tab { display: inline-flex; align-items: center; gap: 7px; height: 36px; padding: 0 14px; border-radius: 8px; border: 0; background: transparent; color: #475569; font-size: 12.5px; font-weight: 700; cursor: pointer; }
    .cfm-tab i { color: #94a3b8; } .cfm-tab:hover { background: #f1f5f9; }
    .cfm-tab.is-on { background: #059669; color: #fff; } .cfm-tab.is-on i { color: #a7f3d0; }
    .cfm-panel[hidden] { display: none; }
    .cfm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .cfm-card + .cfm-card { margin-top: 14px; }
    .cfm-card-h { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
    .cfm-card-h h3 { margin: 0; font-size: 13.5px; font-weight: 800; color: #1e293b; }
    .cfm-card-h small { color: #64748b; font-size: 11.5px; }
    .cfm-card-h .sp { flex: 1; }
    .cfm-card-b { padding: 14px 16px; }
    .cfm-step { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: #059669; color: #fff; font-size: 11px; font-weight: 800; }

    .cfm-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 900px) { .cfm-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .cfm-f label { display: block; color: #475569; font-size: 11px; font-weight: 600; margin: 0 0 4px; }
    .cfm-f input, .cfm-f select, .cfm-f textarea { width: 100%; border: 1px solid #dce2eb; border-radius: 9px; background: #fff; color: #334155; font-size: 12.5px; padding: 6px 9px; box-sizing: border-box; }
    .cfm-f input, .cfm-f select { height: 32px; padding: 0 9px; }
    .cfm-f textarea { min-height: 84px; line-height: 1.45; font-family: inherit; resize: vertical; }
    .cfm-f input:focus, .cfm-f select:focus, .cfm-f textarea:focus { outline: none; border-color: #059669; box-shadow: 0 0 0 3px #05966920; }
    .cfm-btn { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 14px; border-radius: 8px; border: 1px solid #dce2eb; background: #fff; color: #475569; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; }
    .cfm-btn:hover { background: #f8fafc; }
    .cfm-btn.primary { background: #059669; border-color: #059669; color: #fff; } .cfm-btn.primary:hover { background: #047857; }
    .cfm-btn.lg { height: 40px; padding: 0 22px; font-size: 13px; }
    .cfm-btn:disabled { opacity: .5; cursor: not-allowed; }
    .cfm-chk { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: #334155; cursor: pointer; margin-right: 14px; }
    .cfm-chk input { width: 15px; height: 15px; accent-color: #059669; }
    .cfm-hint { font-size: 11.5px; color: #64748b; }
    .cfm-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .cfm-chip { border: 1px solid #dce2eb; border-radius: 999px; padding: 2px 8px; font-size: 10.5px; font-family: ui-monospace, monospace; color: #475569; background: #f8fafc; cursor: pointer; }
    .cfm-chip:hover { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }

    .cfm-scroll { overflow-x: auto; }
    .cfm-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .8rem; }
    .cfm-table th { background: #ecfdf5; color: #047857; padding: 9px 12px; border-bottom: 1px solid #a7f3d0; text-align: left; font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .cfm-table td { padding: 7px 12px; border-bottom: 1px solid #e2e8f0; color: #334155; line-height: 1.35; vertical-align: top; }
    .cfm-table tbody tr:last-child td { border-bottom: 0; }
    .cfm-table tbody tr:hover { background: #ecfdf5; box-shadow: inset 3px 0 0 #059669; }
    .cfm-table small { display: block; color: #94a3b8; font-size: .7rem; }
    .cfm-table .b { font-weight: 700; color: #1e293b; }
    .cfm-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .68rem; font-weight: 700; background: #f1f5f9; color: #475569; white-space: nowrap; }
    .cfm-pill.ok { background: #ecfdf5; color: #047857; } .cfm-pill.bad { background: #fef2f2; color: #b91c1c; } .cfm-pill.warn { background: #fffbeb; color: #b45309; } .cfm-pill.info { background: #eef2ff; color: #4338ca; } .cfm-pill.wa { background: #dcfce7; color: #15803d; }
    .cfm-empty { padding: 30px 16px; text-align: center; color: #64748b; font-size: .85rem; }
    .cfm-empty i { display: block; font-size: 26px; color: #cbd5e1; margin-bottom: 8px; }
    .cfm-nophone { color: #dc2626; }
    .cfm-preview { white-space: pre-wrap; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 9px; padding: 10px 12px; font-size: 12.5px; color: #1e293b; line-height: 1.45; min-height: 44px; }
    .cfm-preview.empty { color: #94a3b8; font-style: italic; }
    .cfm-len { font-size: 10.5px; color: #94a3b8; margin-top: 4px; }
    .cfm-len.over { color: #b45309; font-weight: 700; }

    .cfm-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
    .cfm-bar .cfm-f { flex: 1 1 150px; min-width: 0; } .cfm-bar .cfm-grow { flex: 1 1 200px; } .cfm-bar .cfm-grow2 { flex: 2 1 260px; }
    .cfm-tpl { border-top: 1px solid #e2e8f0; }
    .cfm-tpl summary { padding: 9px 16px; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px; }
    .cfm-tpl summary::-webkit-details-marker { display: none; }
    .cfm-tpl summary i { color: #059669; } .cfm-tpl[open] summary { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .cfm-list { max-height: 62vh; overflow-y: auto; }
    .cfm-row { display: grid; grid-template-columns: 26px 200px 1fr 1fr 118px; gap: 10px; align-items: start; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; transition: background .15s; }
    .cfm-row:last-child { border-bottom: 0; }
    .cfm-row:hover { background: #f8fafc; } .cfm-row.is-off { opacity: .55; }
    .cfm-row .who { font-size: 12.5px; }
    .cfm-row .who b { display: block; color: #1e293b; }
    .cfm-row .who small { display: block; color: #64748b; font-size: 11px; line-height: 1.4; }
    .cfm-msg { position: relative; }
    .cfm-msg .lbl { display: flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; margin-bottom: 3px; }
    .cfm-msg .lbl .to { text-transform: none; letter-spacing: 0; font-weight: 600; color: #64748b; }
    .cfm-msg .lbl .to.none { color: #dc2626; }
    .cfm-msg .txt { white-space: pre-wrap; font-size: 12.5px; line-height: 1.45; color: #1e293b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 9px; min-height: 36px; }
    .cfm-msg .txt.edited { border-color: #059669; background: #ecfdf5; }
    .cfm-msg .txt.empty { color: #94a3b8; font-style: italic; }
    .cfm-msg textarea { width: 100%; min-height: 84px; border: 1px solid #059669; border-radius: 8px; padding: 7px 9px; font-size: 12.5px; line-height: 1.45; font-family: inherit; box-sizing: border-box; box-shadow: 0 0 0 3px #05966920; resize: vertical; }
    .cfm-msg .ed { margin-left: auto; border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 11px; padding: 0 4px; }
    .cfm-msg .ed:hover { color: #059669; }
    .cfm-row .acts { display: flex; flex-direction: column; gap: 4px; }
    .cfm-mini { display: inline-flex; align-items: center; gap: 5px; height: 26px; padding: 0 8px; border-radius: 7px; border: 1px solid #dce2eb; background: #fff; color: #475569; font-size: 11px; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .cfm-mini:hover { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
    .cfm-mini.wa { color: #15803d; } .cfm-mini:disabled { opacity: .4; cursor: not-allowed; }
    .cfm-sendbar { position: sticky; bottom: 0; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 -6px 20px rgba(15,23,42,.06); }
    .cfm-rules td textarea { width: 100%; min-height: 64px; border: 1px solid #dce2eb; border-radius: 8px; padding: 6px 8px; font-size: 12px; line-height: 1.4; font-family: inherit; box-sizing: border-box; resize: vertical; }
    .cfm-rules td textarea:focus { outline: none; border-color: #059669; box-shadow: 0 0 0 3px #05966920; }
    .cfm-rules .grp td { background: #f8fafc; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 6px 12px; }
    .cfm-toggle { position: relative; display: inline-block; width: 34px; height: 20px; }
    .cfm-toggle input { opacity: 0; width: 0; height: 0; }
    .cfm-toggle span { position: absolute; inset: 0; border-radius: 999px; background: #cbd5e1; transition: background .15s; cursor: pointer; }
    .cfm-toggle span::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s; }
    .cfm-toggle input:checked + span { background: #059669; } .cfm-toggle input:checked + span::after { transform: translateX(14px); }
    .cfm-log-body { max-width: 420px; white-space: pre-wrap; }
    @media print { .cfm-tabs, .cfm-head .cfm-btn { display: none !important; } .cfm-panel[hidden] { display: block !important; } }
</style>
@endsection
@section('main-content')
@php
    $groups = ['pipeline' => 'Pipeline — fires as the passenger moves', 'flight' => 'Flight — schedule, delays, cancellations', 'money' => 'Money — agent side', 'manual' => 'Manual only'];
    $chLabel = ['sms' => 'SMS', 'email' => 'Email', 'whatsapp' => 'WhatsApp'];
@endphp

@include('layout.contract-flight-tabs')

<div class="cfm-wrap">
    <header class="cfm-head">
        <span class="ic"><i class="fas fa-comment-sms"></i></span>
        <div>
            <h1>Messages</h1>
            <p>Keep passengers and agents informed — documents, tickets, schedule, delays, dues — by SMS, email or WhatsApp, by hand or automatically as the pipeline moves</p>
        </div>
        <span class="sp"></span>
        <div class="cfm-stat"><b>{{ $stats['today'] }}</b><small>Sent today</small></div>
        <div class="cfm-stat"><b>{{ $stats['week'] }}</b><small>This week</small></div>
        <div class="cfm-stat"><b>{{ $stats['auto'] }}</b><small>Auto · 7 days</small></div>
        <div class="cfm-stat {{ $stats['failed'] ? 'bad' : '' }}"><b>{{ $stats['failed'] }}</b><small>Failed · 7 days</small></div>
    </header>

    @unless($installed)
        <div class="cfm-notice"><i class="fas fa-triangle-exclamation"></i> Messaging is not installed yet — the <code>contract_flight_message_*</code> migration has not run. You can compose and preview, but nothing can be sent or logged until it does.</div>
    @endunless

    <div class="cfm-tabs" role="tablist">
        <button type="button" class="cfm-tab is-on" data-tab="compose"><i class="fas fa-paper-plane"></i> Compose &amp; send</button>
        <button type="button" class="cfm-tab" data-tab="rules"><i class="fas fa-bolt"></i> Automatic rules</button>
        <button type="button" class="cfm-tab" data-tab="log"><i class="fas fa-list-check"></i> Sent log @if($log)<span class="cfm-pill">{{ $log->total() }}</span>@endif</button>
    </div>

    {{-- ── Compose ── --}}
    <section class="cfm-panel" data-panel="compose">
        <div class="cfm-card">
            <div class="cfm-card-b cfm-bar">
                <div class="cfm-f cfm-grow"><label for="cfmScenario">Message type</label>
                    <select id="cfmScenario">
                        @foreach($groups as $g => $gl)
                            <optgroup label="{{ $gl }}">
                                @foreach($rules as $r) @if($r['group'] === $g)<option value="{{ $r['scenario'] }}">{{ $r['label'] }}</option>@endif @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="cfm-f cfm-grow2"><label for="cfmNote">Note <span class="cfm-hint">— the specifics: delay reason, new time…</span></label><input type="text" id="cfmNote" maxlength="1000" placeholder="e.g. Departure now 22:40 due to a late incoming aircraft."></div>
                <div class="cfm-f"><label for="cfmFlight">Flight</label>
                    <select id="cfmFlight">
                        <option value="active">All active flights</option>
                        @foreach($flights as $f)
                            <option value="{{ $f->id }}">{{ $f->flight_number }} · {{ $f->ticket?->from_airport?->code ?? '—' }}→{{ $f->ticket?->to_airport?->code ?? '—' }} · {{ $f->departure_at?->format('d M Y, h:i A') ?? 'Date pending' }} · {{ $f->passengers_count }} pax</option>
                        @endforeach
                    </select>
                </div>
                <div class="cfm-f"><label for="cfmStage">Stage</label>
                    <select id="cfmStage"><option value="">Any stage</option>@foreach($stages as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div class="cfm-f"><label for="cfmAgent">Agent</label>
                    <select id="cfmAgent"><option value="">Any agent</option>@foreach($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
                </div>
                <div class="cfm-f"><label for="cfmSearch">Find</label><input type="search" id="cfmSearch" placeholder="Name, passport, PNR…"></div>
            </div>
            <details class="cfm-tpl">
                <summary><i class="fas fa-pen-to-square"></i> Edit the wording for everyone <span class="cfm-hint">— placeholders fill in per person; edit one person's text in their row instead</span></summary>
                <div class="cfm-card-b">
                    <div class="cfm-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="cfm-f"><label for="cfmTplPassenger">To passenger <span class="cfm-hint">(SMS / WhatsApp)</span></label><textarea id="cfmTplPassenger"></textarea><div class="cfm-len" id="cfmLenP"></div></div>
                        <div class="cfm-f"><label for="cfmTplAgent">To agent <span class="cfm-hint">(SMS / email / WhatsApp)</span></label><textarea id="cfmTplAgent"></textarea><div class="cfm-len" id="cfmLenA"></div></div>
                    </div>
                    <div class="cfm-chips" id="cfmChips">
                        @foreach($placeholders as $ph => $desc)<span class="cfm-chip" data-ph="{{ $ph }}" title="{{ $desc }}">{{ $ph }}</span>@endforeach
                    </div>
                    <div class="cfm-f" style="max-width: 360px; margin-top: 10px;"><label for="cfmSubject">Email subject <span class="cfm-hint">(agents)</span></label><input type="text" id="cfmSubject" maxlength="150" placeholder="Auto from message type"></div>
                </div>
            </details>
        </div>

        <div class="cfm-card">
            <div class="cfm-card-h">
                <label class="cfm-chk" style="margin:0"><input type="checkbox" id="cfmAll" checked> <b id="cfmCount">0 selected</b></label>
                <small>Each row shows the exact text that person gets · click <i class="fas fa-pen"></i> to change it for them only · send one with the buttons on the row, or everyone ticked with the bar below</small>
                <span class="sp"></span>
                <label class="cfm-chk"><input type="checkbox" id="cfmToPassenger" checked> Passengers</label>
                <label class="cfm-chk"><input type="checkbox" id="cfmToAgent" checked> Agents</label>
            </div>
            <div id="cfmList" class="cfm-list"><div class="cfm-empty"><i class="fas fa-spinner fa-spin"></i>Loading…</div></div>
        </div>

        <div class="cfm-sendbar">
            <span class="cfm-hint"><b>Send to everyone ticked via</b></span>
            <label class="cfm-chk"><input type="checkbox" id="cfmChSms" checked> SMS</label>
            <label class="cfm-chk"><input type="checkbox" id="cfmChEmail" checked> Email <span class="cfm-hint">(agents)</span></label>
            <label class="cfm-chk"><input type="checkbox" id="cfmChWa"> WhatsApp <span class="cfm-hint">(one chat each)</span></label>
            <span class="sp" style="flex:1"></span>
            <span class="cfm-hint" id="cfmSendSummary"></span>
            @can('edit contract flight')
            <button type="button" class="cfm-btn primary lg" id="cfmSend" {{ $installed ? '' : 'disabled' }}><i class="fas fa-paper-plane"></i> Send to selected</button>
            @endcan
        </div>
    </section>

    {{-- ── Rules ── --}}
    <section class="cfm-panel" data-panel="rules" hidden>
        <div class="cfm-card">
            <div class="cfm-card-h"><h3>Automatic rules</h3><small>When the pipeline moves, a flag is raised or a schedule changes, these go out on their own. Wording here is also what Compose starts from.</small><span class="sp"></span>
                @can('edit contract flight')<button type="button" class="cfm-btn primary" id="cfmSaveRules" {{ $installed ? '' : 'disabled' }}><i class="fas fa-floppy-disk"></i> Save rules</button>@endcan
            </div>
            <div class="cfm-scroll"><table class="cfm-table cfm-rules" id="cfmRulesTable" data-cf-sort>
                <thead><tr><th>Scenario</th><th style="width:70px">Auto</th><th style="width:90px">Passenger</th><th style="width:70px">Agent</th><th style="width:60px">SMS</th><th style="width:60px">Email</th><th>Passenger wording</th><th>Agent wording</th></tr></thead>
                <tbody>
                @foreach($groups as $g => $gl)
                    <tr class="grp"><td colspan="8">{{ $gl }}</td></tr>
                    @foreach($rules as $r) @if($r['group'] === $g)
                        <tr data-scenario="{{ $r['scenario'] }}">
                            <td class="b">{{ $r['label'] }}<small>{{ $r['group'] === 'pipeline' ? ($scenarios[$r['scenario']]['stage'] ? 'On reaching this stage' : 'On any other stage change') : ($r['group'] === 'flight' ? ($r['scenario'] === 'rescheduled' ? 'On departure/arrival edit or flag' : ($r['scenario'] === 'flight_cancelled' ? 'On the flight-cancelled flag' : ($r['scenario'] === 'offloaded' ? 'On boarding denied / missed / deported' : 'Manual'))) : 'Manual') }}</small></td>
                            <td><label class="cfm-toggle"><input type="checkbox" name="auto" {{ $r['auto'] ? 'checked' : '' }} {{ $r['group'] === 'manual' || in_array($r['scenario'], ['flight_schedule', 'delay', 'pre_departure', 'payment_reminder', 'payment_received']) ? 'disabled' : '' }}><span></span></label></td>
                            <td><label class="cfm-toggle"><input type="checkbox" name="to_passenger" {{ $r['to_passenger'] ? 'checked' : '' }}><span></span></label></td>
                            <td><label class="cfm-toggle"><input type="checkbox" name="to_agent" {{ $r['to_agent'] ? 'checked' : '' }}><span></span></label></td>
                            <td><label class="cfm-toggle"><input type="checkbox" name="via_sms" {{ $r['via_sms'] ? 'checked' : '' }}><span></span></label></td>
                            <td><label class="cfm-toggle"><input type="checkbox" name="via_email" {{ $r['via_email'] ? 'checked' : '' }}><span></span></label></td>
                            <td><textarea name="passenger_template">{{ $r['passenger_template'] }}</textarea></td>
                            <td><textarea name="agent_template">{{ $r['agent_template'] }}</textarea></td>
                        </tr>
                    @endif @endforeach
                @endforeach
                </tbody>
            </table></div>
            <div class="cfm-card-b cfm-hint">Placeholders: @foreach($placeholders as $ph => $desc)<code title="{{ $desc }}">{{ $ph }}</code>{{ $loop->last ? '' : ' · ' }}@endforeach. Passengers receive SMS/WhatsApp only (passport records carry a phone, not an email); agents receive all channels. "Auto" is fixed off for scenarios that only make sense by hand.</div>
        </div>
    </section>

    {{-- ── Log ── --}}
    <section class="cfm-panel" data-panel="log" hidden>
        <div class="cfm-card">
            <div class="cfm-card-h"><h3>Sent log</h3><small>Every message, whoever it went to, whether it arrived</small><span class="sp"></span>
                <form method="GET" style="display:flex; gap:6px; align-items:center;" id="cfmLogFilter">
                    <input type="hidden" name="tab" value="log">
                    <select name="log_scenario" class="cfm-f" style="height:30px;border:1px solid #dce2eb;border-radius:8px;font-size:12px;padding:0 8px;"><option value="">All scenarios</option>@foreach($rules as $r)<option value="{{ $r['scenario'] }}" {{ request('log_scenario') === $r['scenario'] ? 'selected' : '' }}>{{ $r['label'] }}</option>@endforeach</select>
                    <select name="log_channel" style="height:30px;border:1px solid #dce2eb;border-radius:8px;font-size:12px;padding:0 8px;"><option value="">All channels</option>@foreach($chLabel as $k => $l)<option value="{{ $k }}" {{ request('log_channel') === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select>
                    <select name="log_status" style="height:30px;border:1px solid #dce2eb;border-radius:8px;font-size:12px;padding:0 8px;"><option value="">All statuses</option>@foreach(['sent', 'failed', 'skipped', 'prepared'] as $st)<option value="{{ $st }}" {{ request('log_status') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>@endforeach</select>
                    <input type="search" name="log_q" value="{{ request('log_q') }}" placeholder="Name, number, text…" style="height:30px;border:1px solid #dce2eb;border-radius:8px;font-size:12px;padding:0 8px;width:170px;">
                    <button type="submit" class="cfm-btn" style="height:30px;">Filter</button>
                </form>
            </div>
            <div class="cfm-scroll"><table class="cfm-table" data-cf-sort>
                <thead><tr><th>When</th><th>To</th><th>Channel</th><th>Scenario</th><th>Message</th><th>Status</th><th>By</th></tr></thead>
                <tbody>
                @forelse(($log ?? collect()) as $m)
                    <tr>
                        <td>{{ $m->created_at->format('d/m/Y') }}<small>{{ $m->created_at->format('h:i A') }}</small></td>
                        <td class="b">{{ $m->recipient_name }}<small>{{ ucfirst($m->recipient_type) }} · {{ $m->to ?: 'no contact' }}{{ $m->flight ? ' · ' . $m->flight->flight_number . ' · ' . ($m->flight->departure_at?->format('d M Y, h:i A') ?? 'Date pending') : '' }}</small></td>
                        <td><span class="cfm-pill {{ $m->channel === 'whatsapp' ? 'wa' : ($m->channel === 'email' ? 'info' : '') }}">{{ $chLabel[$m->channel] ?? $m->channel }}</span></td>
                        <td>{{ $scenarios[$m->scenario]['label'] ?? $m->scenario }}<small>{{ $m->trigger === 'auto' ? 'Automatic' : 'Manual' }}</small></td>
                        <td class="cfm-log-body">{{ $m->body }}</td>
                        <td><span class="cfm-pill {{ ['sent' => 'ok', 'failed' => 'bad', 'skipped' => 'warn', 'prepared' => 'wa'][$m->status] ?? '' }}">{{ ucfirst($m->status) }}</span>@if($m->error)<small title="{{ $m->error }}">{{ \Illuminate\Support\Str::limit($m->error, 60) }}</small>@endif</td>
                        <td>{{ $m->sender?->name ?? ($m->trigger === 'auto' ? 'System' : '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="cfm-empty"><i class="fas fa-inbox"></i>{{ $installed ? 'Nothing sent yet.' : 'The log will appear once messaging is installed.' }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
            @if($log && $log->hasPages())<div class="cfm-card-b">{{ $log->links() }}</div>@endif
        </div>
    </section>
</div>
@endsection
@section('script')
@include('contract-flights.partials.table-sort')
<script>
const CFM = {
    rules: @json(collect($rules)->keyBy('scenario')),
    urls: {
        recipients: @json(route('role.contract-flights.messages.recipients', ['role' => $role])),
        preview: @json(route('role.contract-flights.messages.preview', ['role' => $role])),
        send: @json(route('role.contract-flights.messages.send', ['role' => $role])),
        rules: @json(route('role.contract-flights.messages.rules', ['role' => $role])),
    },
    csrf: document.querySelector('meta[name="csrf-token"]')?.content,
    canSend: @json($installed && auth()->user()->can('edit contract flight')),
    passengers: [],
    lastBox: null,
};
const $id = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// ── Tabs (remembered in the hash; ?tab=log from the log filter form) ──
(function () {
    const tabs = document.querySelectorAll('.cfm-tab'), panels = document.querySelectorAll('.cfm-panel');
    function show(name) {
        let found = false;
        panels.forEach(p => { const on = p.dataset.panel === name; p.hidden = !on; found = found || on; });
        if (!found) return show('compose');
        tabs.forEach(t => t.classList.toggle('is-on', t.dataset.tab === name));
    }
    tabs.forEach(t => t.addEventListener('click', () => { show(t.dataset.tab); history.replaceState(null, '', '#' + t.dataset.tab); }));
    show(new URLSearchParams(location.search).get('tab') || (location.hash || '#compose').slice(1));
})();

// ── Message type → wording (kept for "everyone"; rows render from it) ──
function cfmLoadScenario() {
    const r = CFM.rules[$id('cfmScenario').value];
    if (!r) return;
    $id('cfmTplPassenger').value = r.passenger_template || '';
    $id('cfmTplAgent').value = r.agent_template || '';
    $id('cfmToPassenger').checked = !!r.to_passenger && r.passenger_template !== '';
    $id('cfmToAgent').checked = !!r.to_agent;
    $id('cfmSubject').placeholder = r.label;
    cfmLengths();
    cfmLoadRecipients();
}
function cfmLengths() {
    for (const [box, out] of [['cfmTplPassenger', 'cfmLenP'], ['cfmTplAgent', 'cfmLenA']]) {
        const n = $id(box).value.length, segs = Math.max(1, Math.ceil(n / 160));
        const el = $id(out); el.textContent = `${n} characters · ~${segs} SMS segment${segs > 1 ? 's' : ''} before placeholders fill in`;
        el.classList.toggle('over', n > 320);
    }
}
let cfmReloadTimer = null;
function cfmReloadSoon() { clearTimeout(cfmReloadTimer); cfmReloadTimer = setTimeout(cfmLoadRecipients, 350); }
$id('cfmScenario').addEventListener('change', cfmLoadScenario);
['cfmTplPassenger', 'cfmTplAgent'].forEach(id => {
    $id(id).addEventListener('focus', () => CFM.lastBox = $id(id));
    $id(id).addEventListener('input', () => { cfmLengths(); cfmReloadSoon(); });
});
$id('cfmNote').addEventListener('input', cfmReloadSoon);
$id('cfmChips').addEventListener('click', e => {
    const chip = e.target.closest('.cfm-chip'); if (!chip) return;
    const box = CFM.lastBox || $id('cfmTplPassenger');
    const s = box.selectionStart ?? box.value.length, t = box.selectionEnd ?? s;
    box.value = box.value.slice(0, s) + chip.dataset.ph + box.value.slice(t);
    box.focus(); box.selectionStart = box.selectionEnd = s + chip.dataset.ph.length;
    cfmLengths(); cfmReloadSoon();
});

// ── Recipients: one row per passenger, with the filled-in text for them
//    and their agent. Edits are kept per person (CFM.edits) and win over
//    the template when sending. ──
CFM.edits = {};
CFM.picked = new Set();
async function cfmLoadRecipients() {
    const list = $id('cfmList');
    list.innerHTML = '<div class="cfm-empty"><i class="fas fa-spinner fa-spin"></i>Loading…</div>';
    const qs = new URLSearchParams({ flight_id: $id('cfmFlight').value, stage: $id('cfmStage').value, agent_id: $id('cfmAgent').value,
        scenario: $id('cfmScenario').value, note: $id('cfmNote').value, passenger_template: $id('cfmTplPassenger').value, agent_template: $id('cfmTplAgent').value });
    try {
        const res = await fetch(`${CFM.urls.recipients}?${qs}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        CFM.passengers = (await res.json()).passengers || [];
    } catch (e) { CFM.passengers = []; }
    CFM.edits = {};
    CFM.picked = new Set(CFM.passengers.map(p => p.id));
    $id('cfmAll').checked = true;
    cfmRenderList();
}
function cfmRenderList() {
    const list = $id('cfmList');
    const q = $id('cfmSearch').value.trim().toLowerCase();
    const rows = CFM.passengers.filter(p => !q || [p.name, p.passport, p.pnr, p.agent, p.flight].join(' ').toLowerCase().includes(q));
    if (!rows.length) { list.innerHTML = '<div class="cfm-empty"><i class="fas fa-user-slash"></i>No passengers match.</div>'; cfmCount(); return; }
    const toP = $id('cfmToPassenger').checked, toA = $id('cfmToAgent').checked;
    list.innerHTML = rows.map(p => {
        const e = CFM.edits[p.id] || {};
        const pm = e.passenger ?? p.passenger_message ?? '', am = e.agent ?? p.agent_message ?? '';
        const msg = (kind, text, to, toNone, show) => show ? `
            <div class="cfm-msg" data-kind="${kind}">
                <div class="lbl">${kind === 'passenger' ? 'Passenger' : 'Agent'} <span class="to ${toNone ? 'none' : ''}">${esc(to)}</span>
                    <button type="button" class="ed" data-act="edit" title="Edit this text for ${kind === 'passenger' ? esc(p.name) : esc(p.agent || 'agent')} only"><i class="fas fa-pen"></i> ${e[kind] !== undefined ? 'edited' : 'edit'}</button></div>
                <div class="txt ${e[kind] !== undefined ? 'edited' : ''} ${text ? '' : 'empty'}">${esc(text) || '(no wording for ' + kind + ')'}</div>
            </div>` : '<div></div>';
        const pTo = p.phone ? p.phone : 'no phone';
        const aTo = p.agent ? [p.agent_phone || 'no phone', p.agent_email || 'no email'].join(' · ') : 'no agent';
        return `<div class="cfm-row ${CFM.picked.has(p.id) ? '' : 'is-off'}" data-id="${p.id}">
            <input type="checkbox" class="cfm-pick" value="${p.id}" ${CFM.picked.has(p.id) ? 'checked' : ''}>
            <div class="who"><b>${esc(p.name)}</b><small>${esc(p.passport || '—')} · ${esc(p.flight || '—')} ${esc(p.route)}</small><small>${esc(p.departure || 'TBA')} · <span class="cfm-pill info">${esc(p.stage)}</span>${p.exception ? ` <span class="cfm-pill bad">${esc(p.exception)}</span>` : ''}</small><small>Agent: ${esc(p.agent || 'Direct')}${p.pnr ? ' · PNR ' + esc(p.pnr) : ''}</small></div>
            ${msg('passenger', pm, pTo, !p.phone, toP)}
            ${p.agent ? msg('agent', am, aTo, !p.agent_phone && !p.agent_email, toA) : (toA ? '<div class="cfm-msg"><div class="lbl">Agent <span class="to none">none — direct passenger</span></div></div>' : '<div></div>')}
            <div class="acts">
                <button type="button" class="cfm-mini" data-act="send" data-ch="sms" ${CFM.canSend ? '' : 'disabled'} title="SMS now to ${toP ? 'passenger' : ''} / agent"><i class="fas fa-comment-sms"></i> SMS</button>
                <button type="button" class="cfm-mini" data-act="send" data-ch="email" ${CFM.canSend && p.agent_email ? '' : 'disabled'} title="Email the agent now"><i class="fas fa-envelope"></i> Email agent</button>
                <button type="button" class="cfm-mini wa" data-act="send" data-ch="whatsapp" ${CFM.canSend && (p.phone || p.agent_phone) ? '' : 'disabled'} title="Open WhatsApp chats"><i class="fab fa-whatsapp"></i> WhatsApp</button>
            </div>
        </div>`;
    }).join('');
    cfmCount();
}
function cfmCount() {
    const n = CFM.picked.size;
    const agents = new Set(CFM.passengers.filter(p => CFM.picked.has(p.id) && p.agent_id).map(p => p.agent_id));
    $id('cfmCount').textContent = `${n} passenger${n === 1 ? '' : 's'} · ${agents.size} agent${agents.size === 1 ? '' : 's'} selected`;
    const edited = Object.keys(CFM.edits).length;
    $id('cfmSendSummary').textContent = edited ? `${edited} message${edited === 1 ? '' : 's'} customised` : '';
}
['cfmFlight', 'cfmStage', 'cfmAgent'].forEach(id => $id(id).addEventListener('change', cfmLoadRecipients));
$id('cfmSearch').addEventListener('input', cfmRenderList);
['cfmToPassenger', 'cfmToAgent'].forEach(id => $id(id).addEventListener('change', cfmRenderList));
$id('cfmAll').addEventListener('change', e => { CFM.picked = new Set(e.target.checked ? CFM.passengers.map(p => p.id) : []); cfmRenderList(); });
$id('cfmList').addEventListener('change', e => {
    if (!e.target.classList.contains('cfm-pick')) return;
    const id = Number(e.target.value); e.target.checked ? CFM.picked.add(id) : CFM.picked.delete(id);
    e.target.closest('.cfm-row').classList.toggle('is-off', !e.target.checked); cfmCount();
});
// Inline editing: the text block becomes a textarea; blur commits it as
// that person's own wording. Same text as the template → edit is dropped.
$id('cfmList').addEventListener('click', e => {
    const btn = e.target.closest('[data-act]'); if (!btn) return;
    const row = btn.closest('.cfm-row'); const id = Number(row.dataset.id); const p = CFM.passengers.find(x => x.id === id);
    if (btn.dataset.act === 'edit') {
        const box = btn.closest('.cfm-msg'); const kind = box.dataset.kind; const txt = box.querySelector('.txt'); if (!txt) return;
        const ta = document.createElement('textarea'); ta.value = (CFM.edits[id] || {})[kind] ?? (kind === 'passenger' ? p.passenger_message : p.agent_message) ?? '';
        txt.replaceWith(ta); ta.focus();
        ta.addEventListener('blur', () => {
            const base = kind === 'passenger' ? p.passenger_message : p.agent_message;
            if (ta.value.trim() === (base || '').trim()) { if (CFM.edits[id]) { delete CFM.edits[id][kind]; if (!Object.keys(CFM.edits[id]).length) delete CFM.edits[id]; } }
            else { CFM.edits[id] = CFM.edits[id] || {}; CFM.edits[id][kind] = ta.value; }
            cfmRenderList();
        });
        return;
    }
    if (btn.dataset.act === 'send') cfmSendNow([id], [btn.dataset.ch], p.name, btn);
});

// ── Sending: one person (row buttons) or everyone ticked (the bar) ──
async function cfmSendNow(ids, channels, label, btn) {
    const to = [$id('cfmToPassenger').checked && 'passenger', $id('cfmToAgent').checked && 'agent'].filter(Boolean);
    if (!ids.length) return Swal.fire({ icon: 'info', title: 'Nobody selected', text: 'Tick at least one passenger.' });
    if (!to.length) return Swal.fire({ icon: 'info', title: 'No audience', text: 'Choose passengers, agents, or both.' });
    if (!channels.length) return Swal.fire({ icon: 'info', title: 'No channel', text: 'Choose SMS, email or WhatsApp.' });
    const type = CFM.rules[$id('cfmScenario').value]?.label || 'message';
    const chLabel = channels.map(c => ({sms:'SMS',email:'Email',whatsapp:'WhatsApp'})[c]).join(' + ');
    const ask = await Swal.fire({ icon: 'question', title: `Send "${type}" via ${chLabel}?`,
        html: `<div style="text-align:left;font-size:13px">${ids.length === 1 ? `To <b>${esc(label)}</b>` : `To <b>${ids.length}</b> passengers`}${to.includes('agent') ? (to.includes('passenger') ? ' and their agent' + (ids.length > 1 ? 's' : '') : ' — agent' + (ids.length > 1 ? 's' : '') + ' only') : ''}.${channels.includes('whatsapp') ? '<br><br>WhatsApp opens a chat per recipient for you to press send.' : ''}</div>`,
        showCancelButton: true, confirmButtonText: 'Send now', confirmButtonColor: '#059669' });
    if (!ask.isConfirmed) return;
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    try {
        const overrides = {}; ids.forEach(id => { if (CFM.edits[id]) overrides[id] = CFM.edits[id]; });
        const res = await fetch(CFM.urls.send, { method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFM.csrf },
            body: JSON.stringify({ scenario: $id('cfmScenario').value, passenger_ids: ids, to, channels, note: $id('cfmNote').value || null,
                subject: $id('cfmSubject').value || null, passenger_template: $id('cfmTplPassenger').value, agent_template: $id('cfmTplAgent').value, overrides }) });
        const d = await res.json().catch(() => ({}));
        if (!res.ok && !d.message) throw new Error('Request failed (' + res.status + ')');
        if ((d.whatsapp || []).length) {
            const links = d.whatsapp.map(w => `<a href="${esc(w.url)}" target="_blank" rel="noopener" class="cfm-btn" style="margin:3px"><i class="fab fa-whatsapp" style="color:#16a34a"></i> ${esc(w.name)}</a>`).join('');
            if (d.whatsapp.length === 1) window.open(d.whatsapp[0].url, '_blank', 'noopener');
            await Swal.fire({ icon: d.success ? 'success' : 'warning', title: d.message, html: `<div style="text-align:left;font-size:12.5px;margin-bottom:8px">WhatsApp — click each to open the chat with the message prefilled:</div><div>${links}</div>`, confirmButtonColor: '#059669', width: 620 });
        } else {
            Swal.fire({ toast: ids.length === 1, position: ids.length === 1 ? 'top-end' : 'center', icon: d.success ? 'success' : 'error',
                title: d.success ? (ids.length === 1 ? `Sent to ${label}` : 'Sent') : 'Not sent', text: d.message || '', showConfirmButton: ids.length !== 1, timer: ids.length === 1 ? 3000 : undefined, confirmButtonColor: '#059669' });
        }
        if (d.success && ids.length > 1) location.href = location.pathname + '?tab=log';
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Not sent', text: e.message });
    } finally { btn.disabled = false; btn.innerHTML = orig; }
}
$id('cfmSend')?.addEventListener('click', () => cfmSendNow(Array.from(CFM.picked),
    [$id('cfmChSms').checked && 'sms', $id('cfmChEmail').checked && 'email', $id('cfmChWa').checked && 'whatsapp'].filter(Boolean), '', $id('cfmSend')));

// ── Rules ──
$id('cfmSaveRules')?.addEventListener('click', async () => {
    const rules = Array.from(document.querySelectorAll('#cfmRulesTable tr[data-scenario]')).map(tr => ({
        scenario: tr.dataset.scenario,
        auto: tr.querySelector('[name=auto]').checked, to_passenger: tr.querySelector('[name=to_passenger]').checked, to_agent: tr.querySelector('[name=to_agent]').checked,
        via_sms: tr.querySelector('[name=via_sms]').checked, via_email: tr.querySelector('[name=via_email]').checked,
        passenger_template: tr.querySelector('[name=passenger_template]').value, agent_template: tr.querySelector('[name=agent_template]').value,
    }));
    const btn = $id('cfmSaveRules'); const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    try {
        const res = await fetch(CFM.urls.rules, { method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CFM.csrf }, body: JSON.stringify({ rules }) });
        const d = await res.json().catch(() => ({}));
        if (!res.ok || !d.success) throw new Error(d.message || (d.errors ? Object.values(d.errors).flat()[0] : 'Could not save'));
        (d.rules || []).forEach(r => CFM.rules[r.scenario] = r);
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Rules saved', showConfirmButton: false, timer: 2200 });
    } catch (e) { Swal.fire({ icon: 'error', title: 'Not saved', text: e.message }); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
});

cfmLoadScenario();
</script>
@endsection
