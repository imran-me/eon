@extends('layout.app')
@section('meta-information')
    <title>Employees with Roles — User Role Setting</title>
@endsection
@section('css')
<style>
    .urs-page{padding:14px 16px 24px;font-family:Inter,"Segoe UI",system-ui,sans-serif;color:#1e293b;}
    .urs-page *{box-sizing:border-box;}
    .urs-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:8px 10px;margin-bottom:12px;}
    .urs-search{position:relative;flex:0 0 280px;}
    .urs-search i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;}
    .urs-search input{width:100%;height:32px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px 0 30px;font-size:13px;outline:none;}
    .urs-search input:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12);}
    .urs-add{margin-left:auto;height:32px;padding:0 14px;border-radius:8px;background:#0f766e;color:#fff;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:7px;text-decoration:none;}
    .urs-add:hover{background:#115e59;color:#fff;}
    .urs-pick-wrap{position:relative;}
    .urs-tbl-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 2px 7px rgba(15,45,63,.04);overflow:hidden;}
    .urs-tbl{width:100%;border-collapse:collapse;font-size:13px;}
    .urs-tbl th{background:#f8fafc;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:#64748b;padding:9px 12px;border-bottom:1px solid #e2e8f0;white-space:nowrap;}
    .urs-tbl td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top;}
    .urs-tbl tr:last-child td{border-bottom:0;}
    .urs-tbl tr.urs-row-main:hover td{background:#fafcfd;}
    .urs-who{display:flex;align-items:center;gap:9px;min-width:0;}
    .urs-av{width:34px;height:34px;border-radius:999px;object-fit:cover;flex-shrink:0;background:#ccfbf1;color:#0f766e;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;}
    .urs-who b{display:block;font-size:13px;}
    .urs-who small{display:block;font-size:11.5px;color:#64748b;}
    .urs-roles{display:flex;flex-wrap:wrap;gap:3px;}
    .urs-roles span{font-size:10.5px;font-weight:600;padding:2px 7px;border-radius:999px;background:#f1f5f9;color:#475569;}
    .urs-mods{display:flex;flex-wrap:wrap;gap:4px;max-width:520px;}
    .urs-mod{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;padding:3px 4px 3px 8px;border-radius:7px;border:1px solid #e2e8f0;background:#f8fafc;color:#334155;}
    .urs-mod small{color:#94a3b8;font-size:10.5px;}
    .urs-letters{display:inline-flex;gap:2px;}
    .urs-letters i{font-style:normal;width:16px;height:16px;border-radius:4px;font-size:9.5px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;}
    .urs-letters i.v{background:#ccfbf1;color:#0f766e;} .urs-letters i.c{background:#dbeafe;color:#1d4ed8;}
    .urs-letters i.e{background:#fef3c7;color:#b45309;} .urs-letters i.d{background:#fee2e2;color:#b91c1c;}
    .urs-basic-chip{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 8px;border-radius:7px;background:#f1f5f9;color:#475569;}
    .urs-num{font-variant-numeric:tabular-nums;text-align:right;white-space:nowrap;}
    .urs-when{font-size:12px;color:#475569;white-space:nowrap;}
    .urs-when small{display:block;color:#94a3b8;font-size:11px;}
    .urs-acts{display:flex;gap:4px;justify-content:flex-end;}
    .urs-ib{width:30px;height:30px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;color:#475569;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;font-size:12px;}
    .urs-ib:hover{background:#f1f5f9;color:#0f766e;}
    .urs-ib.danger:hover{background:#fee2e2;color:#b91c1c;border-color:#fecaca;}
    .urs-detail td{background:#fbfdfe;padding:4px 12px 14px 55px;}
    .urs-detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px;}
    .urs-detail-co{border:1px solid #e2e8f0;border-radius:10px;padding:8px 10px;background:#fff;}
    .urs-detail-co h4{font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#0f766e;margin:0 0 4px;}
    .urs-detail-m{display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:12.5px;padding:3px 0;border-bottom:1px dashed #f1f5f9;}
    .urs-empty{padding:48px 16px;text-align:center;color:#475569;}
    .urs-empty i{font-size:28px;color:#94a3b8;margin-bottom:10px;display:block;}
    .urs-empty h2{font-size:16px;font-weight:650;color:#1e293b;margin:0 0 4px;}
    .urs-empty p{font-size:13px;margin:0 0 14px;}
    .urs-off{font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;background:#fee2e2;color:#b91c1c;margin-left:4px;}
    .urs-warn{font-size:12.5px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;margin-bottom:12px;}
    .urs-hidden{display:none !important;}
    .urs-master{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e2e8f0;border-left:4px solid #94a3b8;border-radius:12px;padding:10px 14px;margin-bottom:12px;}
    .urs-master.on{border-left-color:#0f766e;background:#f0fdfa;border-color:#99f6e4;}
    .urs-master-ic{width:34px;height:34px;border-radius:9px;background:#f1f5f9;color:#64748b;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .urs-master.on .urs-master-ic{background:#0f766e;color:#fff;}
    .urs-master b{display:block;font-size:14px;}
    .urs-master small{display:block;font-size:12px;color:#475569;}
    .urs-master-btn{height:32px;padding:0 14px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#1e293b;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;}
    .urs-master.on .urs-master-btn{border-color:#fecaca;color:#b91c1c;}
    .urs-master-btn:hover{background:#f8fafc;}
    .urs-status{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:999px;background:#f1f5f9;color:#475569;white-space:nowrap;}
    .urs-status.live{background:#dcfce7;color:#15803d;}
    .urs-status.held{background:#fef3c7;color:#92400e;}
    .urs-apply{display:block;margin-top:5px;height:26px;padding:0 10px;border-radius:7px;border:1px solid #0f766e;background:#0f766e;color:#fff;font-size:11.5px;font-weight:600;cursor:pointer;white-space:nowrap;}
    .urs-apply:hover{background:#115e59;}
    .urs-apply.off{background:#fff;color:#b91c1c;border-color:#fecaca;}
    .urs-apply.off:hover{background:#fee2e2;}
</style>
@endsection
@section('main-content')
<div class="urs-page">
    @include('user-role-settings.partials.head', ['active' => 'employees',
        'sub' => 'Everyone given access here, module by module. Open a row to see it in full, edit it on the map, or remove it.'])

    @if(! $ready)
        <div class="urs-warn"><i class="fas fa-exclamation-triangle"></i> Not installed yet: the migration has not run, so nobody can be saved here.</div>
    @endif

    @if($applyReady)
    <div class="urs-master {{ $master ? 'on' : '' }}">
        <div class="urs-master-ic"><i class="fas fa-power-off"></i></div>
        <div style="min-width:0;flex:1">
            <b>New system is {{ $master ? 'ON' : 'OFF' }}</b>
            <small>{{ $master
                ? 'Everyone marked "Applied" below uses the new system — their old role no longer counts. Everybody else is on the old system, untouched.'
                : 'Nobody uses the new system now — everybody, including people marked "Applied", is on their old role.' }}</small>
        </div>
        <button type="button" class="urs-master-btn" id="ursMaster" data-on="{{ $master ? 0 : 1 }}">{{ $master ? 'Turn OFF for everyone' : 'Turn ON' }}</button>
    </div>
    @endif

    <div class="urs-bar">
        <div class="urs-search"><i class="fas fa-search"></i><input type="text" id="ursFind" placeholder="Find by name, ID, email, role or module…" autocomplete="off" aria-label="Find"></div>
        <span style="font-size:12px;color:#64748b" id="ursShown">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('person', $rows->count()) }}</span>
        <a href="{{ route('role.user-role-settings.index', ['role' => $role]) }}" class="urs-add"><i class="fas fa-user-plus"></i> Add employee</a>
    </div>

    <div class="urs-tbl-wrap">
        @if($rows->isEmpty())
            <div class="urs-empty">
                <i class="fas fa-users"></i>
                <h2>Nobody has a setting yet</h2>
                <p>Pick an employee on Assign Role, tick their modules and Save — they appear here.</p>
                <a href="{{ route('role.user-role-settings.index', ['role' => $role]) }}" class="urs-add" style="margin:0 auto;display:inline-flex"><i class="fas fa-user-plus"></i> Add employee</a>
            </div>
        @else
        <table class="urs-tbl">
            <thead><tr>
                <th>Employee</th><th>Current role</th><th>Given here</th><th class="urs-num">Pages</th><th>Status</th><th>Last change</th><th style="text-align:right">Actions</th>
            </tr></thead>
            <tbody>
            @foreach($rows as $r)
                @php
                    $p = $r['person'];
                    $ini = collect(preg_split('/\s+/', trim($p['name'])))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                    $find = mb_strtolower(implode(' ', array_merge([$p['name'], $p['email'], $p['emp_no'], $p['phone']], $p['roles'], array_column($r['modules'], 'label'))));
                    $byCo = collect($r['modules'])->groupBy('company');
                @endphp
                <tr class="urs-row-main" data-find="{{ $find }}" data-id="{{ $p['id'] }}">
                    <td>
                        <div class="urs-who">
                            @if($p['image'])<img class="urs-av" src="{{ $p['image'] }}" alt="" onerror="this.outerHTML='<span class=&quot;urs-av&quot;>{{ $ini }}</span>'">@else<span class="urs-av">{{ $ini }}</span>@endif
                            <div style="min-width:0"><b>{{ $p['name'] }} @if($r['trashed'])<span class="urs-off">DELETED</span>@endif</b>
                                <small>{{ collect([$p['emp_no'], $p['email']])->filter()->implode(' · ') }}</small></div>
                        </div>
                    </td>
                    <td><div class="urs-roles">@forelse($p['roles'] as $rn)<span>{{ $rn }}</span>@empty<span>—</span>@endforelse</div></td>
                    <td>
                        <div class="urs-mods">
                            <span class="urs-basic-chip" title="Basic employee access — always on, own data only"><i class="fas fa-lock"></i> Basic · {{ $basicPages }}</span>
                            @foreach($r['modules'] as $m)
                                <span class="urs-mod" title="{{ $m['company'] }} → {{ $m['label'] }}: {{ $m['pages'] }} of {{ $m['total'] }} pages">{{ $m['label'] }} <small>{{ $m['pages'] }}/{{ $m['total'] }}</small>
                                    <span class="urs-letters">@foreach(str_split($m['acts']) as $a)<i class="{{ $a }}">{{ strtoupper($a) }}</i>@endforeach</span></span>
                            @endforeach
                        </div>
                    </td>
                    <td class="urs-num">{{ $r['pages'] }}<small style="display:block;color:#94a3b8;font-size:11px">{{ count($r['modules']) }} {{ \Illuminate\Support\Str::plural('module', count($r['modules'])) }}</small></td>
                    <td>
                        @if($applyReady)
                            @if($r['applied'])
                                <span class="urs-status {{ $master ? 'live' : 'held' }}" title="{{ $master ? 'Using the new system now' : 'Marked Applied, but the master switch is OFF — on his old role' }}">
                                    <i class="fas {{ $master ? 'fa-check-circle' : 'fa-pause-circle' }}"></i> {{ $master ? 'Applied' : 'Applied · paused' }}</span>
                                <button type="button" class="urs-apply off" data-apply="{{ $p['id'] }}" data-on="0" data-name="{{ $p['name'] }}">Back to old role</button>
                            @else
                                <span class="urs-status">Old role</span>
                                <button type="button" class="urs-apply" data-apply="{{ $p['id'] }}" data-on="1" data-name="{{ $p['name'] }}" data-roles="{{ implode(', ', $p['roles']) }}">Apply new system</button>
                            @endif
                        @else
                            <span class="urs-status">Not installed</span>
                        @endif
                    </td>
                    <td class="urs-when">{{ \Illuminate\Support\Carbon::parse($r['updated_at'])->format('d M Y, h:i A') }}<small>{{ $r['by'] ? 'by '.$r['by'] : '' }}</small></td>
                    <td>
                        <div class="urs-acts">
                            <button type="button" class="urs-ib" data-view="{{ $p['id'] }}" title="View all of it" aria-label="View {{ $p['name'] }}'s access"><i class="fas fa-eye"></i></button>
                            <a class="urs-ib" href="{{ route('role.user-role-settings.index', ['role' => $role, 'user' => $p['id']]) }}" title="Edit on the map" aria-label="Edit {{ $p['name'] }}'s access"><i class="fas fa-pen"></i></a>
                            <button type="button" class="urs-ib danger" data-remove="{{ $p['id'] }}" data-name="{{ $p['name'] }}" title="Remove this setting" aria-label="Remove {{ $p['name'] }}'s setting"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>
                <tr class="urs-detail urs-hidden" data-detail="{{ $p['id'] }}">
                    <td colspan="7">
                        <div class="urs-detail-grid">
                            <div class="urs-detail-co"><h4 style="color:#475569"><i class="fas fa-lock"></i> Basic employee access</h4>
                                <div class="urs-detail-m"><span>Own attendance, leave, salary, requests, claims, tasks</span><span style="color:#64748b;font-size:11.5px">{{ $basicPages }} pages · always</span></div></div>
                            @foreach($byCo as $co => $mods)
                            <div class="urs-detail-co"><h4>{{ $co }}</h4>
                                @foreach($mods as $m)
                                <div class="urs-detail-m"><span>{{ $m['label'] }} <small style="color:#94a3b8">{{ $m['pages'] }}/{{ $m['total'] }} pages</small></span>
                                    <span class="urs-letters">@foreach(str_split('vced') as $a)<i class="{{ str_contains($m['acts'], $a) ? $a : '' }}" style="{{ str_contains($m['acts'], $a) ? '' : 'background:#f1f5f9;color:#cbd5e1' }}">{{ strtoupper($a) }}</i>@endforeach</span></div>
                                @endforeach
                            </div>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
@section('script')
{{-- the shell's task script (board-show.js) calls $ on every click; this page loads jQuery as most pages do --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function () {
    const URL_ONE = @json(route('role.user-role-settings.show', ['role' => $role, 'user' => 0]));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const ask = o => window.Swal ? Swal.fire(o) : Promise.resolve({ isConfirmed: confirm(o.title) });
    const URL_MASTER = @json(route('role.user-role-settings.master', ['role' => $role]));
    const MASTER = @json($master);
    const post = (url, body) => fetch(url, { method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(body) })
        .then(async r => { const d = await r.json().catch(() => ({})); if (!r.ok || !d.success) throw new Error(d.message || 'Not done'); return d; });
    const fail = err => window.Swal ? Swal.fire({ icon: 'error', title: 'Not done', text: err.message }) : alert(err.message);

    // switch one person onto the new system, or back to his old role
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-apply]');
        if (!b) return;
        const on = b.dataset.on === '1';
        const esc = s => String(s || '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
        ask(on ? {
            icon: 'warning', title: 'Apply the new system to ' + b.dataset.name + '?',
            html: `His old role <b>${esc(b.dataset.roles || '—')}</b> stops counting for him. He gets <b>Basic employee access</b> + only what is ticked on the map — sidebar, pages and buttons.<br><br>Everybody else stays on the old system, untouched.` +
                (MASTER ? '' : '<br><br><span style="color:#92400e">The master switch is OFF, so this waits until you turn it ON.</span>'),
            showCancelButton: true, confirmButtonText: 'Apply to him only', confirmButtonColor: '#0f766e',
        } : {
            icon: 'question', title: 'Put ' + b.dataset.name + ' back on his old role?', text: 'His saved setting stays — you can apply it again any time.',
            showCancelButton: true, confirmButtonText: 'Back to old role',
        }).then(r => {
            if (!r.isConfirmed) return;
            b.disabled = true;
            b.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            post(URL_ONE.replace(/\/0$/, '/' + b.dataset.apply + '/apply'), { on }).then(() => location.reload()).catch(err => { b.disabled = false; b.textContent = on ? 'Apply new system' : 'Back to old role'; fail(err); });
        });
    });
    document.getElementById('ursMaster')?.addEventListener('click', ev => {
        const b = ev.currentTarget;
        const on = b.dataset.on === '1';
        ask(on ? { icon: 'warning', title: 'Turn the new system ON?', text: 'Everyone marked "Applied" will use it right away. Everybody else stays on the old system.', showCancelButton: true, confirmButtonText: 'Turn ON', confirmButtonColor: '#0f766e' }
              : { icon: 'warning', title: 'Turn the new system OFF for everyone?', text: 'Everybody goes back to their old role at once. Settings and "Applied" marks are kept.', showCancelButton: true, confirmButtonText: 'Turn OFF', confirmButtonColor: '#dc2626' })
            .then(r => {
                if (!r.isConfirmed) return;
                b.disabled = true;
                post(URL_MASTER, { on }).then(() => location.reload()).catch(err => { b.disabled = false; fail(err); });
            });
    });

    document.addEventListener('click', e => {
        const v = e.target.closest('[data-view]');
        if (v) {
            const d = document.querySelector(`[data-detail="${v.dataset.view}"]`);
            d.classList.toggle('urs-hidden');
            v.querySelector('i').className = d.classList.contains('urs-hidden') ? 'fas fa-eye' : 'fas fa-eye-slash';
            return;
        }
        const rm = e.target.closest('[data-remove]');
        if (rm) {
            ask({ icon: 'warning', title: 'Remove ' + rm.dataset.name + '\'s setting?', text: 'Only this setting goes. Their role and current access stay as they are.',
                showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#dc2626' }).then(r => {
                if (!r.isConfirmed) return;
                rm.disabled = true;
                rm.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                fetch(URL_ONE.replace(/\/0$/, '/' + rm.dataset.remove), { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
                    .then(res => { if (!res.ok) throw new Error('Not removed'); location.reload(); })
                    .catch(err => { rm.disabled = false; rm.innerHTML = '<i class="fas fa-trash-alt"></i>'; window.Swal ? Swal.fire({ icon: 'error', title: err.message }) : alert(err.message); });
            });
        }
    });

    const find = document.getElementById('ursFind');
    find?.addEventListener('input', () => {
        const q = find.value.trim().toLowerCase();
        let n = 0;
        document.querySelectorAll('.urs-row-main').forEach(tr => {
            const show = !q || tr.dataset.find.includes(q);
            tr.classList.toggle('urs-hidden', !show);
            if (!show) document.querySelector(`[data-detail="${tr.dataset.id}"]`)?.classList.add('urs-hidden');
            if (show) n++;
        });
        document.getElementById('ursShown').textContent = n + (n === 1 ? ' person' : ' people');
    });
})();
</script>
@endsection
