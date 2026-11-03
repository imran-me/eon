@extends('layout.app')
@section('meta-information')
    <title>User Role Setting</title>
@endsection
@section('css')
<style>
    .urs-page{padding:14px 16px 24px;font-family:Inter,"Segoe UI",system-ui,sans-serif;color:#1e293b;}
    .urs-page *{box-sizing:border-box;}
    .urs-head{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
    .urs-head-ic{width:42px;height:42px;border-radius:12px;background:#ccfbf1;color:#0f766e;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
    .urs-title{font-size:22px;font-weight:750;letter-spacing:-.03em;margin:0;line-height:1.2;}
    .urs-sub{font-size:13px;color:#64748b;margin:2px 0 0;}
    .urs-test{margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:12px;font-weight:600;white-space:nowrap;}

    .urs-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:8px 10px;margin-bottom:12px;}
    .urs-search{position:relative;flex:0 0 220px;}
    .urs-search i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;}
    .urs-search input{width:100%;height:32px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px 0 30px;font-size:13px;outline:none;}
    .urs-search input:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12);}
    .urs-chips{display:flex;gap:6px;flex-wrap:wrap;flex:1;min-width:0;}
    .urs-chip{height:30px;display:inline-flex;align-items:center;gap:6px;padding:0 10px;border-radius:999px;border:1px solid #e2e8f0;background:#f8fafc;color:#475569;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;}
    .urs-chip img{width:16px;height:16px;border-radius:4px;object-fit:cover;}
    .urs-chip b{font-weight:700;color:#94a3b8;font-size:11px;}
    .urs-chip.on{background:#0f766e;border-color:#0f766e;color:#fff;}
    .urs-chip.on b{color:#99f6e4;}
    .urs-tool{height:30px;padding:0 10px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
    .urs-tool:hover{background:#f1f5f9;}

    .urs-grid{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:12px;align-items:start;}

    /* the root map */
    .urs-map{display:flex;flex-direction:column;gap:10px;}
    .urs-co{background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 2px 7px rgba(15,45,63,.04);overflow:hidden;}
    .urs-co-head{display:flex;align-items:center;gap:10px;padding:10px 12px;cursor:pointer;border-left:4px solid var(--urs-co,#0f766e);}
    .urs-co-head:hover{background:#f8fafc;}
    .urs-co-logo{width:30px;height:30px;border-radius:8px;object-fit:cover;flex-shrink:0;}
    .urs-co-ic{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--urs-co,#0f766e);color:#fff;font-size:13px;flex-shrink:0;}
    .urs-co-name{font-size:14px;font-weight:700;line-height:1.2;}
    .urs-co-meta{font-size:11.5px;color:#64748b;}
    .urs-caret{width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;color:#94a3b8;transition:transform .15s;flex-shrink:0;}
    .urs-closed > .urs-co-head .urs-caret,.urs-closed > .urs-row .urs-caret{transform:rotate(-90deg);}
    .urs-co-body{padding:2px 12px 10px 26px;}
    .urs-closed > .urs-co-body,.urs-closed > .urs-kids{display:none;}

    .urs-sec{position:relative;padding-left:14px;border-left:2px solid #e2e8f0;margin-top:6px;}
    .urs-sec-label{font-size:10px;font-weight:700;letter-spacing:1.1px;text-transform:uppercase;color:#94a3b8;padding:6px 0 2px;}
    .urs-node{position:relative;}
    .urs-node::before{content:"";position:absolute;left:-14px;top:17px;width:12px;border-top:2px solid #e2e8f0;}
    .urs-row{display:flex;align-items:center;gap:8px;min-height:34px;padding:2px 6px;border-radius:8px;}
    .urs-row:hover{background:#f8fafc;}
    .urs-row.urs-hit{background:#fefce8;}
    .urs-mod-ic{width:26px;height:26px;border-radius:7px;background:#f1f5f9;color:#475569;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
    .urs-has .urs-mod-ic{background:#ccfbf1;color:#0f766e;}
    .urs-lbl{font-size:13px;font-weight:600;color:#1e293b;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .urs-page-row .urs-lbl{font-weight:500;color:#334155;}
    .urs-count{font-size:11px;color:#94a3b8;white-space:nowrap;}
    .urs-soon{font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;background:#f1f5f9;color:#64748b;white-space:nowrap;}
    .urs-with{font-size:11px;color:#0f766e;cursor:help;white-space:nowrap;}
    .urs-open{color:#94a3b8;font-size:11px;padding:4px;flex-shrink:0;}
    .urs-open:hover{color:#0f766e;}
    .urs-spacer{flex:1;}
    .urs-tog-btn{cursor:pointer;background:none;border:0;padding:0;}
    .urs-kids{position:relative;margin-left:18px;padding-left:14px;border-left:2px dashed #e2e8f0;}

    /* View / Create / Edit / Delete */
    .urs-acts{display:flex;gap:4px;flex-shrink:0;}
    .urs-act{width:58px;height:24px;border-radius:6px;border:1px solid #e2e8f0;background:#fff;color:#94a3b8;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;}
    .urs-act:hover{border-color:#94a3b8;color:#475569;}
    .urs-act.na{visibility:hidden;}
    .urs-act[data-a="v"].on{background:#0f766e;border-color:#0f766e;color:#fff;}
    .urs-act[data-a="c"].on{background:#2563eb;border-color:#2563eb;color:#fff;}
    .urs-act[data-a="e"].on{background:#d97706;border-color:#d97706;color:#fff;}
    .urs-act[data-a="d"].on{background:#dc2626;border-color:#dc2626;color:#fff;}
    .urs-act.part{border-style:dashed;color:#475569;background:#f8fafc;}
    .urs-acts-head{display:flex;gap:4px;}
    .urs-acts-head span{width:58px;text-align:center;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#94a3b8;}

    /* access card */
    .urs-card{position:sticky;top:12px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 2px 7px rgba(15,45,63,.04);display:flex;flex-direction:column;max-height:calc(100vh - 160px);}
    .urs-card-top{padding:14px 14px 10px;border-bottom:1px solid #e2e8f0;}
    .urs-person{display:flex;align-items:center;gap:10px;}
    .urs-ava{width:36px;height:36px;border-radius:999px;background:#f1f5f9;border:1px dashed #cbd5e1;color:#94a3b8;display:flex;align-items:center;justify-content:center;}
    .urs-person b{display:block;font-size:13px;}
    .urs-person small{font-size:11.5px;color:#64748b;}
    .urs-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:10px;}
    .urs-stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:6px 8px;}
    .urs-stat b{display:block;font-size:17px;font-weight:750;font-variant-numeric:tabular-nums;}
    .urs-stat span{font-size:10.5px;color:#64748b;}
    .urs-card-body{padding:10px 14px;overflow:auto;flex:1;min-height:0;}
    .urs-card-h{font-size:10.5px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:#94a3b8;margin:4px 0 6px;}
    .urs-empty-note{font-size:12.5px;color:#64748b;line-height:1.5;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px;padding:12px;}
    .urs-sum-co{margin-bottom:10px;}
    .urs-sum-co-name{font-size:12px;font-weight:700;color:#0f766e;margin-bottom:4px;display:flex;align-items:center;gap:6px;}
    .urs-sum-mod{display:flex;align-items:center;justify-content:space-between;gap:6px;font-size:12.5px;padding:3px 0;border-bottom:1px dashed #f1f5f9;}
    .urs-letters{display:flex;gap:3px;}
    .urs-letters i{font-style:normal;width:18px;height:18px;border-radius:5px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;background:#f1f5f9;color:#cbd5e1;}
    .urs-letters i.v{background:#ccfbf1;color:#0f766e;} .urs-letters i.c{background:#dbeafe;color:#1d4ed8;}
    .urs-letters i.e{background:#fef3c7;color:#b45309;} .urs-letters i.d{background:#fee2e2;color:#b91c1c;}
    .urs-sugg{display:flex;flex-wrap:wrap;gap:6px;}
    .urs-sugg button{height:26px;padding:0 9px;border-radius:999px;border:1px dashed #0f766e;background:#f0fdfa;color:#0f766e;font-size:11.5px;font-weight:600;cursor:pointer;}
    .urs-sugg button:hover{background:#ccfbf1;}
    .urs-card-foot{padding:10px 14px;border-top:1px solid #e2e8f0;display:flex;gap:8px;}
    .urs-card-foot button{flex:1;height:32px;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;}
    .urs-clear{border:1px solid #e2e8f0;background:#fff;color:#475569;}
    .urs-save{border:0;background:#0f766e;color:#fff;}
    .urs-save:hover{background:#115e59;}
    .urs-save:disabled{background:#cbd5e1;cursor:not-allowed;}
    .urs-save.dirty{box-shadow:0 0 0 3px rgba(15,118,110,.25);}
    .urs-pick{position:relative;margin-top:10px;}
    .urs-pick > i{position:absolute;left:10px;top:9px;color:#94a3b8;font-size:12px;}
    .urs-pick input{width:100%;height:32px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px 0 30px;font-size:12.5px;outline:none;}
    .urs-pick input:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12);}
    .urs-pick-list{position:absolute;left:0;right:0;top:36px;z-index:30;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 10px 24px -6px rgba(5,22,80,.18);max-height:280px;overflow:auto;}
    .urs-pick-item{display:flex;align-items:center;gap:8px;width:100%;padding:7px 10px;border:0;background:none;text-align:left;cursor:pointer;}
    .urs-pick-item:hover,.urs-pick-item:focus{background:#f0fdfa;outline:none;}
    .urs-pick-item b{display:block;font-size:12.5px;color:#1e293b;}
    .urs-pick-item small{display:block;font-size:11px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .urs-pick-none{padding:10px;font-size:12px;color:#64748b;}
    .urs-av{width:30px;height:30px;border-radius:999px;object-fit:cover;flex-shrink:0;background:#ccfbf1;color:#0f766e;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;}
    .urs-person .urs-av{width:36px;height:36px;font-size:14px;}
    .urs-roles{display:flex;flex-wrap:wrap;gap:3px;margin-top:3px;}
    .urs-roles span{font-size:10px;font-weight:600;padding:1px 6px;border-radius:999px;background:#f1f5f9;color:#475569;}
    .urs-change{margin-left:auto;height:26px;padding:0 9px;border-radius:7px;border:1px solid #e2e8f0;background:#fff;color:#475569;font-size:11.5px;font-weight:600;cursor:pointer;flex-shrink:0;}
    .urs-saved{margin-bottom:10px;padding-bottom:10px;border-bottom:1px dashed #e2e8f0;}
    .urs-saved-list{display:flex;flex-direction:column;gap:4px;max-height:96px;overflow:auto;}
    .urs-saved-row{display:flex;align-items:center;gap:6px;padding:3px 4px 3px 6px;border-radius:8px;border:1px solid #e2e8f0;}
    .urs-saved-row.on{border-color:#0f766e;background:#f0fdfa;}
    .urs-saved-row button.urs-load{flex:1;min-width:0;display:flex;align-items:center;gap:6px;border:0;background:none;cursor:pointer;text-align:left;font-size:12px;font-weight:600;color:#1e293b;padding:0;}
    .urs-saved-row .urs-av{width:22px;height:22px;font-size:10px;}
    .urs-saved-row .urs-count{margin-left:auto;}
    .urs-saved-row .urs-del{width:24px;height:24px;border:0;border-radius:6px;background:none;color:#94a3b8;cursor:pointer;flex-shrink:0;}
    .urs-saved-row .urs-del:hover{background:#fee2e2;color:#b91c1c;}
    .urs-warn{font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 10px;margin-top:8px;}
    .urs-hidden{display:none !important;}
    .urs-lock{display:inline-flex;align-items:center;gap:6px;height:26px;padding:0 10px;border-radius:999px;background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;font-size:11.5px;font-weight:700;white-space:nowrap;}
    .urs-basic-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px;padding-top:8px;}
    .urs-basic-g{border:1px solid #e2e8f0;border-radius:10px;padding:8px 10px;background:#f8fafc;}
    .urs-basic-h{font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#64748b;margin-bottom:4px;display:flex;align-items:center;gap:6px;}
    .urs-basic-i{display:flex;align-items:center;justify-content:space-between;gap:6px;font-size:12.5px;color:#334155;padding:3px 0;}
    .urs-basic-note{font-size:12px;color:#64748b;margin:10px 0 0;display:flex;gap:6px;align-items:flex-start;}
    .urs-sum-basic{display:flex;align-items:center;gap:8px;font-size:12.5px;padding:7px 9px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;margin-bottom:10px;color:#334155;}
    .urs-sum-basic b{margin-left:auto;font-size:11px;color:#64748b;}
</style>
@endsection
@section('main-content')
<div class="urs-page">
    @include('user-role-settings.partials.head', ['active' => 'assign',
        'sub' => 'The whole ERP as one map: company → section → module → page. Give a person exactly the modules they need, from any company.'])

    <div class="urs-bar">
        <div class="urs-search"><i class="fas fa-search"></i><input type="text" id="ursSearch" placeholder="Find a module or page…" autocomplete="off"></div>
        <div class="urs-chips" id="ursChips"></div>
        <button type="button" class="urs-tool" id="ursExpand"><i class="fas fa-expand-alt"></i> Open all</button>
        <button type="button" class="urs-tool" id="ursCollapse"><i class="fas fa-compress-alt"></i> Close all</button>
    </div>

    <div class="urs-grid">
        <div>
            {{-- Basic employee access: always on, locked — each person's own data (owner, 2026-11-01) --}}
            <section class="urs-co urs-basic urs-closed" id="ursBasic" style="--urs-co:#64748b;margin-bottom:10px">
                <div class="urs-co-head" data-basic-tog>
                    <span class="urs-caret"><i class="fas fa-chevron-down"></i></span>
                    <div class="urs-co-ic"><i class="fas fa-id-badge"></i></div>
                    <div style="min-width:0"><div class="urs-co-name">Basic employee access</div>
                        <div class="urs-co-meta">{{ collect($basic)->sum(fn ($g) => count($g['items'])) }} pages · their own data only · always on for everyone set up here</div></div>
                    <span class="urs-spacer"></span>
                    <span class="urs-lock"><i class="fas fa-lock"></i> Always on</span>
                </div>
                <div class="urs-co-body">
                    <div class="urs-basic-grid">
                        @foreach($basic as $g)
                        <div class="urs-basic-g">
                            <div class="urs-basic-h"><i class="{{ $g['icon'] }}"></i> {{ $g['group'] }}</div>
                            @foreach($g['items'] as [$label, $path, $acts])
                            <div class="urs-basic-i">
                                <span>{{ $label }}</span>
                                <span class="urs-letters">@foreach(str_split($acts) as $a)<i class="{{ $a }}" title="{{ ['v' => 'View', 'c' => 'Create', 'e' => 'Edit', 'd' => 'Delete'][$a] }} — own only">{{ strtoupper($a) }}</i>@endforeach</span>
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                    <p class="urs-basic-note"><i class="fas fa-info-circle"></i> Each person sees and changes only their own attendance, leave, salary, requests, claims and tasks. Not here on purpose: viewing other users and lead projects — tick those on the map when needed.</p>
                </div>
            </section>
            <div class="urs-map" id="ursMap"></div>
        </div>

        <aside class="urs-card">
            <div class="urs-card-top">
                <div id="ursSavedWrap" class="urs-saved urs-hidden">
                    <div class="urs-card-h" style="margin-top:0">Saved settings <span id="ursSavedN"></span></div>
                    <div id="ursSaved" class="urs-saved-list"></div>
                </div>
                <div class="urs-person" id="ursPerson">
                    <div class="urs-ava"><i class="fas fa-user"></i></div>
                    <div style="min-width:0;flex:1"><b>Access card</b><small>Choose the person below</small></div>
                </div>
                <div class="urs-pick" id="ursPick">
                    <i class="fas fa-search"></i>
                    <input type="text" id="ursPickInput" placeholder="Search employee by name, email, phone, ID…" autocomplete="off" aria-label="Search employee">
                    <div class="urs-pick-list urs-hidden" id="ursPickList"></div>
                </div>
                <div class="urs-stats">
                    <div class="urs-stat"><b id="ursStatCo">0</b><span>companies</span></div>
                    <div class="urs-stat"><b id="ursStatMod">0</b><span>modules</span></div>
                    <div class="urs-stat"><b id="ursStatPg">0</b><span>pages</span></div>
                </div>
            </div>
            <div class="urs-card-body" id="ursSummary"></div>
            <div class="urs-card-foot">
                <button type="button" class="urs-clear" id="ursClear"><i class="fas fa-eraser"></i> Clear</button>
                <button type="button" class="urs-save" id="ursSave" disabled><i class="fas fa-save"></i> Save</button>
            </div>
        </aside>
    </div>
</div>
@endsection
@section('script')
{{-- the shell's task script (board-show.js) calls $ on every click; this page loads jQuery as most pages do --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function () {
    const TREE = @json($tree);
    const COMPANIES = @json((object) $companies);
    const BASE = @json(url('/'.$role));
    const READY = @json($ready);
    const URL_PEOPLE = @json(route('role.user-role-settings.people', ['role' => $role]));
    const URL_SAVED = @json(route('role.user-role-settings.saved', ['role' => $role]));
    const URL_ONE = @json(route('role.user-role-settings.show', ['role' => $role, 'user' => 0]));
    const BASIC_N = @json(collect($basic)->sum(fn ($g) => count($g['items'])));
    const BASIC_LINE = `<div class="urs-sum-basic"><i class="fas fa-lock"></i> Basic employee access <b>${BASIC_N} pages · always</b></div>`;
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const ACTS = { v: 'View', c: 'Create', e: 'Edit', d: 'Delete' };
    const COLORS = { 4: '#0f766e', 2: '#2563eb', 6: '#a86a2e', 3: '#c2410c', 1: '#7c3aed', 8: '#0891b2', 5: '#db2777', 7: '#475569' };

    const nodes = {};        // id -> node (with id, parent, leaves)
    const state = {};        // leaf id -> Set of letters
    const moduleOf = {};     // leaf id -> module node id
    const byKey = {};        // module/page key -> node id
    let seq = 0;

    const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

    // index the tree: ids, parents, leaves (anything that carries acts)
    function index(n, parent, company, mod) {
        n.id = 'n' + (++seq);
        n.parent = parent;
        n.company = company;
        nodes[n.id] = n;
        if (n.key) byKey[n.key] = n.id;
        if (n.type === 'company') { company = n; n.label = n.label || (COMPANIES[n.company_id]?.short) || ('Company #' + n.company_id); n.fullName = COMPANIES[n.company_id]?.name || n.label; }
        if (n.type === 'module') mod = n;
        n.leaves = [];
        (n.kids || []).forEach(k => { index(k, n, company, mod); n.leaves.push(...k.leaves); });
        if (n.acts && !(n.kids || []).length) { n.leaves = [n.id]; state[n.id] = new Set(); moduleOf[n.id] = mod ? mod.id : n.id; }
    }
    TREE.forEach(c => index(c, null, null, null));

    // what a group row shows for one action: on (every leaf), part (some), off, or n/a
    function agg(n, a) {
        const able = n.leaves.filter(id => nodes[id].acts.includes(a));
        if (!able.length) return 'na';
        const on = able.filter(id => state[id].has(a)).length;
        return on === 0 ? 'off' : on === able.length ? 'on' : 'part';
    }
    function setAct(n, a, turnOn) {
        n.leaves.forEach(id => {
            const leaf = nodes[id];
            if (!leaf.acts.includes(a)) return;
            const s = state[id];
            if (turnOn) { s.add(a); if (a !== 'v' && leaf.acts.includes('v')) s.add('v'); }
            else { s.delete(a); if (a === 'v') s.clear(); }
        });
    }

    function actsHtml(n) {
        return '<div class="urs-acts">' + Object.keys(ACTS).map(a =>
            `<button type="button" class="urs-act" data-n="${n.id}" data-a="${a}" title="${ACTS[a]} — ${esc(n.label)}">${ACTS[a]}</button>`).join('') + '</div>';
    }
    function openHtml(n) {
        return n.path ? `<a class="urs-open" href="${esc(BASE + '/' + n.path)}" target="_blank" rel="noopener" title="Open this page in a new tab" aria-label="Open ${esc(n.label)}"><i class="fas fa-external-link-alt"></i></a>` : '';
    }
    function pageCount(n) { return n.leaves.length; }

    function nodeHtml(n, depth) {
        const kids = n.kids || [];
        const isMod = n.type === 'module';
        const caret = kids.length ? `<button type="button" class="urs-caret urs-tog-btn" data-tog="${n.id}" aria-label="Open or close ${esc(n.label)}"><i class="fas fa-chevron-down"></i></button>` : '<span class="urs-caret"></span>';
        const icon = isMod ? `<div class="urs-mod-ic"><i class="${esc(n.icon)}"></i></div>` : '';
        const withTitle = (n.with || []).map(k => nodes[byKey[k]]?.label).filter(Boolean).join(', ');
        return `<div class="urs-node ${kids.length ? 'urs-closed' : ''}" id="${n.id}" data-search="${esc((n.label + ' ' + (n.path || '')).toLowerCase())}">
            <div class="urs-row ${isMod ? 'urs-mod-row' : 'urs-page-row'}">
                ${caret}${icon}
                <span class="urs-lbl">${esc(n.label)}</span>
                ${kids.length ? `<span class="urs-count">${pageCount(n)} page${pageCount(n) === 1 ? '' : 's'}</span>` : ''}
                ${n.soon ? '<span class="urs-soon">COMING SOON</span>' : ''}
                ${withTitle ? `<span class="urs-with" title="Usually needs: ${esc(withTitle)}"><i class="fas fa-link"></i> goes with ${(n.with || []).length}</span>` : ''}
                ${openHtml(n)}
                <span class="urs-spacer"></span>
                ${actsHtml(n)}
            </div>
            ${kids.length ? `<div class="urs-kids">${kids.map(k => nodeHtml(k, depth + 1)).join('')}</div>` : ''}
        </div>`;
    }

    function companyHtml(c) {
        const info = COMPANIES[c.company_id] || {};
        const color = COLORS[c.company_id] || '#0f766e';
        const mods = c.kids.reduce((t, s) => t + s.kids.length, 0);
        const logo = info.logo ? `<img class="urs-co-logo" src="${esc(info.logo)}" alt="" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'urs-co-ic',innerHTML:'<i class=&quot;${esc(c.icon)}&quot;></i>'}))">` : `<div class="urs-co-ic"><i class="${esc(c.icon)}"></i></div>`;
        const title = c.company_id === 4 ? 'Group-wide — shared by every company' : c.fullName;
        return `<section class="urs-co" id="${c.id}" style="--urs-co:${color}" data-company="${c.company_id}">
            <div class="urs-co-head" data-tog="${c.id}">
                <span class="urs-caret"><i class="fas fa-chevron-down"></i></span>
                ${logo}
                <div style="min-width:0"><div class="urs-co-name">${esc(title)}</div>
                    <div class="urs-co-meta">${mods} module${mods === 1 ? '' : 's'} · ${c.leaves.length} page${c.leaves.length === 1 ? '' : 's'} · <span data-co-sum="${c.id}">no access</span></div></div>
                <span class="urs-spacer"></span>
                <div class="urs-acts-head">${Object.values(ACTS).map(a => `<span>${a}</span>`).join('')}</div>
            </div>
            <div class="urs-co-body">${c.kids.map(s => `<div class="urs-sec" id="${s.id}"><div class="urs-sec-label">${esc(s.label)}</div>${s.kids.map(m => nodeHtml(m, 0)).join('')}</div>`).join('')}</div>
        </section>`;
    }

    const map = document.getElementById('ursMap');
    map.innerHTML = TREE.map(companyHtml).join('');
    // Travels and Group-wide open; the smaller companies start closed
    TREE.forEach(c => { if (![2, 4].includes(c.company_id)) document.getElementById(c.id).classList.add('urs-closed'); });

    // chips: All + one per company
    const chips = document.getElementById('ursChips');
    let only = null;
    chips.innerHTML = `<button type="button" class="urs-chip on" data-only="">All</button>` + TREE.map(c => {
        const info = COMPANIES[c.company_id] || {};
        const mods = c.kids.reduce((t, s) => t + s.kids.length, 0);
        const img = info.logo ? `<img src="${esc(info.logo)}" alt="" onerror="this.remove()">` : `<i class="${esc(c.icon)}"></i>`;
        return `<button type="button" class="urs-chip" data-only="${c.company_id}">${img}${esc(c.company_id === 4 ? 'Group-wide' : c.label)} <b>${mods}</b></button>`;
    }).join('');

    function refresh() {
        document.querySelectorAll('.urs-act').forEach(b => {
            const s = agg(nodes[b.dataset.n], b.dataset.a);
            b.classList.toggle('na', s === 'na');
            b.classList.toggle('on', s === 'on');
            b.classList.toggle('part', s === 'part');
        });
        document.querySelectorAll('.urs-mod-row').forEach(r => {
            const n = nodes[r.parentElement.id];
            r.classList.toggle('urs-has', n.leaves.some(id => state[id].size));
        });
        TREE.forEach(c => {
            const got = c.leaves.filter(id => state[id].size).length;
            const el = document.querySelector(`[data-co-sum="${c.id}"]`);
            if (el) el.textContent = got ? `${got} page${got === 1 ? '' : 's'} given` : 'no access';
        });
        summary();
    }

    function summary() {
        const box = document.getElementById('ursSummary');
        const chosen = Object.keys(state).filter(id => state[id].size);
        const mods = [...new Set(chosen.map(id => moduleOf[id]))];
        const cos = [...new Set(mods.map(id => nodes[id].company.id))];
        document.getElementById('ursStatCo').textContent = cos.length;
        document.getElementById('ursStatMod').textContent = mods.length;
        document.getElementById('ursStatPg').textContent = chosen.length;
        if (!chosen.length) {
            box.innerHTML = `<div class="urs-card-h">What this person gets</div>${BASIC_LINE}<div class="urs-empty-note">Nothing yet. Press <b>View</b>, <b>Create</b>, <b>Edit</b> or <b>Delete</b> on a module (gives every page in it) or on one page. Mix modules from any company — Travels' Contract Flight with Group-wide Party Management, for example.</div>`;
            return;
        }
        let html = '<div class="urs-card-h">What this person gets</div>' + BASIC_LINE;
        cos.forEach(cid => {
            const c = nodes[cid];
            html += `<div class="urs-sum-co"><div class="urs-sum-co-name"><i class="${esc(c.icon)}"></i>${esc(c.company_id === 4 ? 'Group-wide' : c.label)}</div>`;
            mods.filter(m => nodes[m].company.id === cid).forEach(m => {
                const n = nodes[m];
                const got = n.leaves.filter(id => state[id].size).length;
                const letters = Object.keys(ACTS).map(a => { const s = agg(n, a); return s === 'na' ? '' : `<i class="${s === 'off' ? '' : a}" title="${ACTS[a]}: ${s === 'on' ? 'all pages' : s === 'part' ? 'some pages' : 'none'}">${a.toUpperCase()}</i>`; }).join('');
                html += `<div class="urs-sum-mod"><span>${esc(n.label)} <span class="urs-count">${got}/${n.leaves.length}</span></span><span class="urs-letters">${letters}</span></div>`;
            });
            html += '</div>';
        });
        // modules that usually go with what was picked, not given yet
        const sugg = [...new Set(mods.flatMap(m => nodes[m].with || []))]
            .map(k => nodes[byKey[k]]).filter(n => n && !n.leaves.some(id => state[id].size));
        if (sugg.length) {
            html += `<div class="urs-card-h" style="margin-top:12px">Usually needed with these</div><div class="urs-sugg">` +
                sugg.map(n => `<button type="button" data-sugg="${n.id}" title="Give View on every page of ${esc(n.label)}">+ ${esc(n.label)} · View</button>`).join('') + '</div>';
        }
        box.innerHTML = html;
    }

    // clicks: an action, a caret / company head, a suggestion
    document.addEventListener('click', e => {
        const act = e.target.closest('.urs-act');
        if (act && map.contains(act)) {
            const n = nodes[act.dataset.n];
            setAct(n, act.dataset.a, agg(n, act.dataset.a) !== 'on');
            refresh();
            return;
        }
        const tog = e.target.closest('[data-tog]');
        if (tog && map.contains(tog)) { document.getElementById(tog.dataset.tog).classList.toggle('urs-closed'); return; }
        const sg = e.target.closest('[data-sugg]');
        if (sg) { setAct(nodes[sg.dataset.sugg], 'v', true); refresh(); return; }
        const chip = e.target.closest('.urs-chip');
        if (chip) {
            only = chip.dataset.only || null;
            chips.querySelectorAll('.urs-chip').forEach(x => x.classList.toggle('on', x === chip));
            document.querySelectorAll('.urs-co').forEach(c => {
                c.classList.toggle('urs-hidden', !!only && c.dataset.company !== only);
                if (only && c.dataset.company === only) c.classList.remove('urs-closed');
            });
        }
    });
    document.querySelector('[data-basic-tog]').addEventListener('click', () => document.getElementById('ursBasic').classList.toggle('urs-closed'));
    document.getElementById('ursClear').addEventListener('click', () => { Object.values(state).forEach(s => s.clear()); refresh(); });

    // ---- the person, the saved list, Save ----
    let person = null;          // {id, name, …}
    let savedSnap = '{}';       // what is stored for him, to tell unsaved changes
    const oneUrl = id => URL_ONE.replace(/\/0$/, '/' + id);
    const initials = n => String(n || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join('');
    const avatar = p => p.image ? `<img class="urs-av" src="${esc(p.image)}" alt="" onerror="this.outerHTML='<span class=&quot;urs-av&quot;>${esc(initials(p.name))}</span>'">` : `<span class="urs-av">${esc(initials(p.name))}</span>`;
    const ask = (o) => window.Swal ? Swal.fire(o) : Promise.resolve({ isConfirmed: confirm(o.text || o.title) });
    const tell = (icon, title, text) => window.Swal ? Swal.fire({ icon, title, text, timer: icon === 'success' ? 1800 : undefined, showConfirmButton: icon !== 'success' }) : alert(title + (text ? '\n' + text : ''));

    function snapshot() {
        const out = {};
        Object.keys(state).forEach(id => { if (state[id].size) out[nodes[id].key] = [...'vced'].filter(a => state[id].has(a)).join(''); });
        return out;
    }
    const dirty = () => !!person && JSON.stringify(snapshot()) !== savedSnap;
    const saveBtn = document.getElementById('ursSave');
    function paintSave() {
        saveBtn.disabled = !person || !READY;
        saveBtn.classList.toggle('dirty', dirty());
        saveBtn.innerHTML = dirty() ? '<i class="fas fa-save"></i> Save changes' : '<i class="fas fa-check"></i> Saved';
        if (!person) saveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
        saveBtn.title = !READY ? 'Not installed yet — the migration has not run' : !person ? 'Choose the person first' : '';
    }
    const _refresh = refresh;
    refresh = function () { _refresh(); paintSave(); };

    function paintPerson() {
        const box = document.getElementById('ursPerson');
        const pick = document.getElementById('ursPick');
        if (!person) {
            box.innerHTML = `<div class="urs-ava"><i class="fas fa-user"></i></div><div style="min-width:0;flex:1"><b>Access card</b><small>Choose the person below</small></div>`;
            pick.classList.remove('urs-hidden');
            return;
        }
        box.innerHTML = `${avatar(person)}<div style="min-width:0;flex:1"><b style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(person.name)}</b>
            <small>${esc([person.emp_no, person.email].filter(Boolean).join(' · '))}</small>
            <div class="urs-roles">${(person.roles || []).map(r => `<span title="Current role — unchanged">${esc(r)}</span>`).join('')}</div></div>
            <button type="button" class="urs-change" id="ursChange">Change</button>`;
        pick.classList.add('urs-hidden');
    }

    async function load(id) {
        if (dirty()) {
            const r = await ask({ icon: 'question', title: 'Leave without saving?', text: person.name + ' has unsaved changes.', showCancelButton: true, confirmButtonText: 'Leave them', cancelButtonText: 'Stay' });
            if (!r.isConfirmed) return;
        }
        const res = await fetch(oneUrl(id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) { tell('error', 'Could not load that person'); return; }
        const d = await res.json();
        person = d.person;
        Object.values(state).forEach(s => s.clear());
        Object.entries(d.access || {}).forEach(([k, letters]) => { const id2 = byKey[k]; if (id2 && state[id2]) [...letters].forEach(a => state[id2].add(a)); });
        savedSnap = JSON.stringify(snapshot());
        paintPerson();
        refresh();
        paintSaved();
    }

    let savedList = [];
    async function loadSaved() {
        try {
            const d = await (await fetch(URL_SAVED, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
            savedList = d.saved || [];
        } catch (e) { savedList = []; }
        paintSaved();
    }
    function paintSaved() {
        const wrap = document.getElementById('ursSavedWrap');
        wrap.classList.toggle('urs-hidden', !savedList.length);
        document.getElementById('ursSavedN').textContent = savedList.length ? '(' + savedList.length + ')' : '';
        document.getElementById('ursSaved').innerHTML = savedList.map(p => `<div class="urs-saved-row ${person && person.id === p.id ? 'on' : ''}">
            <button type="button" class="urs-load" data-load="${p.id}" title="Open ${esc(p.name)}'s setting">${avatar(p)}<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.name)}</span>
            <span class="urs-count">${p.modules} mod · ${p.pages} pg</span></button>
            <button type="button" class="urs-del" data-del="${p.id}" title="Remove ${esc(p.name)}'s setting" aria-label="Remove ${esc(p.name)}'s setting"><i class="fas fa-times"></i></button></div>`).join('');
    }

    // employee search
    const pickIn = document.getElementById('ursPickInput');
    const pickList = document.getElementById('ursPickList');
    let pickTimer = null, pickSeq = 0;
    function searchPeople() {
        const q = pickIn.value.trim();
        const my = ++pickSeq;
        fetch(URL_PEOPLE + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json()).then(d => {
                if (my !== pickSeq) return;
                const ppl = d.people || [];
                pickList.innerHTML = ppl.length ? ppl.map(p => `<button type="button" class="urs-pick-item" data-load="${p.id}">${avatar(p)}<span style="min-width:0"><b>${esc(p.name)}</b>
                    <small>${esc([p.emp_no, (p.roles || []).join(', '), p.email].filter(Boolean).join(' · '))}</small></span></button>`).join('')
                    : '<div class="urs-pick-none">Nobody found.</div>';
                pickList.classList.remove('urs-hidden');
            }).catch(() => {});
    }
    pickIn.addEventListener('input', () => { clearTimeout(pickTimer); pickTimer = setTimeout(searchPeople, 220); });
    pickIn.addEventListener('focus', searchPeople);
    document.addEventListener('click', e => {
        if (!e.target.closest('#ursPick')) pickList.classList.add('urs-hidden');
        const ld = e.target.closest('[data-load]');
        if (ld) { pickList.classList.add('urs-hidden'); pickIn.value = ''; load(ld.dataset.load); return; }
        if (e.target.closest('#ursChange')) {
            (async () => {
                if (dirty()) {
                    const r = await ask({ icon: 'question', title: 'Leave without saving?', text: person.name + ' has unsaved changes.', showCancelButton: true, confirmButtonText: 'Leave them', cancelButtonText: 'Stay' });
                    if (!r.isConfirmed) return;
                }
                person = null; savedSnap = '{}';
                Object.values(state).forEach(s => s.clear());
                paintPerson(); refresh(); paintSaved();
                pickIn.focus();
            })();
            return;
        }
        const del = e.target.closest('[data-del]');
        if (del) {
            const p = savedList.find(x => String(x.id) === del.dataset.del);
            ask({ icon: 'warning', title: 'Remove ' + (p ? p.name : 'this') + '\'s setting?', text: 'Only this setting goes. Their role and current access stay as they are.', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: '#dc2626' })
                .then(r => {
                    if (!r.isConfirmed) return;
                    fetch(oneUrl(del.dataset.del), { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, credentials: 'same-origin' })
                        .then(() => {
                            if (person && String(person.id) === del.dataset.del) { savedSnap = '{}'; }
                            loadSaved(); refresh();
                        });
                });
        }
    });

    saveBtn.addEventListener('click', async () => {
        if (!person) return;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        try {
            const res = await fetch(oneUrl(person.id), { method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ access: snapshot() }) });
            const d = await res.json();
            if (!res.ok || !d.success) { tell('error', 'Not saved', d.message || 'Something went wrong.'); }
            else {
                Object.values(state).forEach(s => s.clear());
                Object.entries(d.access || {}).forEach(([k, letters]) => { const id2 = byKey[k]; if (id2) [...letters].forEach(a => state[id2].add(a)); });
                savedSnap = JSON.stringify(snapshot());
                tell('success', 'Saved', d.message);
                loadSaved();
            }
        } catch (e) { tell('error', 'Not saved', e.message); }
        refresh();
    });
    window.addEventListener('beforeunload', e => { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });

    if (!READY) document.getElementById('ursPerson').insertAdjacentHTML('afterend', '<div class="urs-warn"><i class="fas fa-exclamation-triangle"></i> Not installed yet: the migration has not run, so Save is off.</div>');
    loadSaved();
    // opened from Employees with Roles → Edit (?user=ID)
    const startUser = new URLSearchParams(location.search).get('user');
    if (startUser && /^\d+$/.test(startUser)) load(startUser);
    document.getElementById('ursExpand').addEventListener('click', () => document.querySelectorAll('.urs-co,.urs-node').forEach(x => { if (x.querySelector('.urs-kids,.urs-co-body')) x.classList.remove('urs-closed'); }));
    document.getElementById('ursCollapse').addEventListener('click', () => document.querySelectorAll('.urs-node').forEach(x => { if (x.querySelector('.urs-kids')) x.classList.add('urs-closed'); }));

    // search: show matching rows with their parents, open the way to them
    document.getElementById('ursSearch').addEventListener('input', e => {
        const q = e.target.value.trim().toLowerCase();
        document.querySelectorAll('.urs-row.urs-hit').forEach(r => r.classList.remove('urs-hit'));
        if (!q) {
            document.querySelectorAll('.urs-node,.urs-sec').forEach(x => x.classList.remove('urs-hidden'));
            document.querySelectorAll('.urs-co').forEach(c => c.classList.toggle('urs-hidden', !!only && c.dataset.company !== only));
            return;
        }
        const keep = new Set();
        Object.values(nodes).forEach(n => {
            if (!['module', 'page'].includes(n.type)) return;
            if (!(n.label + ' ' + (n.path || '')).toLowerCase().includes(q)) return;
            document.querySelector('#' + n.id + ' > .urs-row')?.classList.add('urs-hit');
            for (let p = n; p; p = p.parent) keep.add(p.id);
            (function down(x) { keep.add(x.id); (x.kids || []).forEach(down); })(n);
        });
        document.querySelectorAll('.urs-node,.urs-sec,.urs-co').forEach(x => {
            const show = keep.has(x.id) && !(only && x.classList.contains('urs-co') && x.dataset.company !== only);
            x.classList.toggle('urs-hidden', !show);
            if (show && x.querySelector('.urs-hit')) x.classList.remove('urs-closed');
        });
    });

    refresh();
})();
</script>
@endsection
