{{-- Shared by every Contract Flight section through its navigation band. --}}
<style>
    .cf-list-print{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:7px;background:#fff;color:#334155;font-size:12px;white-space:nowrap;cursor:pointer}
</style>
@unless(request()->routeIs('role.contract-flights.index', 'role.contract-flights.dashboard'))
<div style="display:flex;justify-content:flex-end;margin-bottom:10px">
    <button type="button" class="cf-list-print" onclick="cfPrintList()"><i class="fas fa-print"></i> Print</button>
</div>
@endunless
@php
    $printCompany = $company ?? null;
    $printCompanyLogo = optional($printCompany)->logo;
    $printCompanyLogo = $printCompanyLogo && file_exists(public_path($printCompanyLogo))
        ? $printCompanyLogo
        : 'logo.png';
    $printCompanyPayload = [
        'name' => optional($printCompany)->name ?? config('app.name'),
        'address' => optional($printCompany)->address ?? '',
        'phone' => optional($printCompany)->phone ?? '',
        'email' => optional($printCompany)->email ?? '',
        'logo' => asset($printCompanyLogo),
    ];
    $printRole = request()->route('role')
        ?: \Illuminate\Support\Str::slug(auth()->user()?->getRoleNames()->first() ?? '');
    $printPreviewUrl = route('role.contract-flights.print-preview', [
        'role' => $printRole,
        'company' => \App\Services\CompanyFunds::COMPANY_ID,
    ]);
@endphp
<script>
(() => {
const PS_STUDIO_KEY = 'cf-print-designer-v1';
const PS_ME = @json(auth()->user()?->name ?? '');
const PS_PRINT_PREVIEW_URL = @json($printPreviewUrl);
const PS_COMPANY = @json($printCompanyPayload);
const PS_SIGS = {prepared:'Prepared By',checked:'Checked By',authorized:'Authorized Signatory',received:'Received By (Agent)'};
const PS_KIND = {};
let PS_COLS = {};
const cfPicks = new Set();
let cfPeriodOverride = null;
let cfCanPick = true;
let cfTitleOverride = '';
    function psStudioSaved() { try { return JSON.parse(localStorage.getItem(PS_STUDIO_KEY) || '{}') || {}; } catch (_) { return {}; } }
    // Signature images (drawn or uploaded) live in this browser only, keyed by
    // slot; the print sheet (same origin) reads them back by the same key.
    const PS_SIG_KEY = 'cf-print-signatures-v1';
    function psSigImages() { try { return JSON.parse(localStorage.getItem(PS_SIG_KEY) || '{}') || {}; } catch (_) { return {}; } }
    function psSigStore(slot, dataUrl) {
        const all = psSigImages();
        if (dataUrl) all[slot] = dataUrl; else delete all[slot];
        try { localStorage.setItem(PS_SIG_KEY, JSON.stringify(all)); return true; } catch (_) { return false; }
    }

    function psStatementStudio(prefix) {
        const cfg = cfConfig;
        const picks = cfPicks, n = picks.size;
        const period = cfg.period() || {};
        const today = psYmd(new Date());
        const shownLabel = period.from && period.to
            ? (period.from === period.to ? psDMY(period.from) : `${psDMY(period.from)} – ${psDMY(period.to)}`)
            : (period.from ? `from ${psDMY(period.from)}` : period.to ? `up to ${psDMY(period.to)}` : 'Currently loaded records / page');
        const sv = psStudioSaved();
        const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

        // Categories = the entry names in the loaded statement, several types per name.
        const groups = {};
        cfRows().map(r => ({ type: r.category })).forEach(t => {
            const label = PS_KIND[t.type] || String(t.type || 'Other').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            (groups[label] = groups[label] || { types: new Set(), count: 0 }).types.add(t.type);
            groups[label].count++;
        });
        const catHtml = Object.keys(groups).sort().map(l => `
            <label class="pz-chip"><input type="checkbox" class="pz-cat" value="${esc([...groups[l].types].join(','))}" checked>
            <span>${esc(l)} <em>${groups[l].count}</em></span></label>`).join('') || '<span class="pz-hint">No entries loaded.</span>';

        const validSaved = (sv.cols || []).filter(k => PS_COLS[k]);
        const colsOn = validSaved.length ? validSaved : Object.keys(PS_COLS).filter(k => PS_COLS[k].on);
        const studioOrient = colsOn.length >= 6 ? 'landscape' : (sv.orient === 'landscape' ? 'landscape' : 'portrait');
        const colHtml = Object.keys(PS_COLS).map(k => `
            <label class="pz-chip"><input type="checkbox" class="pz-col" value="${k}" ${colsOn.includes(k) ? 'checked' : ''}><span>${esc(PS_COLS[k].label)}</span></label>`).join('');
        const sigsOn = sv.sigs || ['authorized'];
        const sigImgs = psSigImages();
        const sigHtml = Object.keys(PS_SIGS).map(k => `
            <span class="pz-sigwrap"><label class="pz-chip"><input type="checkbox" class="pz-sig" value="${k}" ${sigsOn.includes(k) ? 'checked' : ''}><span>${esc(PS_SIGS[k])}</span></label><button type="button" class="pz-sigpen ${sigImgs[k] ? 'has' : ''}" data-sigpen="${k}" title="${sigImgs[k] ? 'Change signature' : 'Add signature'}"><i class="fas fa-${sigImgs[k] ? 'signature' : 'pen-nib'}"></i></button></span>`).join('');
        const stamps = { '': 'None', paid: 'PAID', received: 'RECEIVED', due: 'PAYMENT DUE', original: 'ORIGINAL', copy: 'COPY', verified: 'VERIFIED', custom: 'Custom…' };
        const stampHtml = Object.keys(stamps).map(k => `
            <label class="pz-chip"><input type="radio" name="pzStamp" value="${k}" ${(sv.stamp || '') === k ? 'checked' : ''}><span>${stamps[k]}</span></label>`).join('');
        const accents = ['#4f46e5', '#0f766e', '#1d4ed8', '#b45309', '#be123c', '#334155'];
        const accentHtml = accents.map(c => `
            <label class="pz-sw" style="--c:${c}"><input type="radio" name="pzAccent" value="${c}" ${(sv.accent || '#4f46e5') === c ? 'checked' : ''}><span></span></label>`).join('');
        const tog = (id, label, on) => `<label class="pz-tog"><input type="checkbox" id="${id}" ${on ? 'checked' : ''}><span>${label}</span></label>`;
        const on = (k, d = true) => sv[k] === undefined ? d : !!sv[k];
        const mode0 = n ? 'selected' : 'shown';
        const opt = (value, label, hint, extra = '') => `
            <label class="pd-opt"><input type="radio" name="pdMode" value="${value}" ${value === mode0 ? 'checked' : ''}>
            <span class="pd-body"><span class="pd-label">${label}</span>${hint ? `<span class="pd-hint">${hint}</span>` : ''}${extra}</span></label>`;
        const keys = Object.keys(cfg.noteTemplates || {});

        Swal.fire({
            title: 'Print Contract Flight',
            width: 'min(1180px, calc(100vw - 32px))',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-print"></i> Print',
            confirmButtonColor: '#4f46e5',
            focusConfirm: false,
            html: `
            <style>
                .pz { display:grid; grid-template-columns: 1fr 1.25fr 220px; gap:16px; text-align:left; max-height: calc(100vh - 220px); }
                .pz > section { overflow:auto; padding-right:4px; }
                .pz h5 { font-size:10.5px; font-weight:800; color:#4f46e5; text-transform:uppercase; letter-spacing:.07em; margin:12px 0 6px; display:flex; justify-content:space-between; align-items:center; }
                .pz h5:first-child { margin-top:0; }
                .pz h5 a { font-size:10px; color:#64748b; text-transform:none; letter-spacing:0; font-weight:600; cursor:pointer; margin-left:8px; }
                .pz .pd-opt { display:flex; gap:10px; padding:8px 10px; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; margin-bottom:6px; }
                .pz .pd-opt:has(input:checked) { border-color:#6366f1; background:#f5f6ff; }
                .pz .pd-opt input { accent-color:#4f46e5; margin-top:3px; }
                .pz .pd-body { display:flex; flex-direction:column; gap:3px; flex:1; }
                .pz .pd-label { font-size:13px; font-weight:700; color:#1e293b; }
                .pz .pd-hint, .pz-hint { font-size:11px; color:#64748b; }
                .pz .pd-dates { display:flex; gap:6px; align-items:center; font-size:11px; color:#64748b; }
                .pz input[type=date], .pz input[type=text], .pz select, .pz textarea { padding:6px 9px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px; color:#1e293b; font-family:inherit; box-sizing:border-box; background:#fff; }
                .pz input[type=text], .pz select, .pz textarea { width:100%; }
                .pz textarea { min-height:62px; resize:vertical; margin-top:6px; }
                .pz-chips { display:flex; flex-wrap:wrap; gap:6px; }
                .pz-chip { position:relative; cursor:pointer; }
                .pz-chip input { position:absolute; opacity:0; pointer-events:none; }
                .pz-chip span { display:inline-flex; gap:5px; align-items:center; padding:5px 10px; border:1px solid #e2e8f0; border-radius:999px; font-size:11.5px; font-weight:600; color:#475569; background:#fff; }
                .pz-chip span em { font-style:normal; font-size:10px; color:#94a3b8; }
                .pz-chip input:checked + span { border-color:#6366f1; background:#eef2ff; color:#3730a3; }
                .pz-tog { display:flex; align-items:center; gap:8px; font-size:12px; color:#334155; padding:4px 0; cursor:pointer; }
                .pz-tog input { accent-color:#4f46e5; width:15px; height:15px; }
                .pz-row { display:grid; grid-template-columns:1fr 1fr; gap:4px 10px; align-items:center; }
                .pz-sw { position:relative; cursor:pointer; }
                .pz-sw input { position:absolute; opacity:0; pointer-events:none; }
                .pz-sw span { display:block; width:22px; height:22px; border-radius:50%; background:var(--c); box-shadow:0 0 0 2px #fff, 0 0 0 3px #e2e8f0; }
                .pz-sw input:checked + span { box-shadow:0 0 0 2px #fff, 0 0 0 4px var(--c); }
                .pz-seg { display:inline-flex; border:1px solid #e2e8f0; border-radius:7px; overflow:hidden; }
                .pz-seg label { cursor:pointer; } .pz-seg input { display:none; }
                .pz-seg span { display:block; padding:5px 12px; font-size:11.5px; font-weight:600; color:#475569; }
                .pz-seg input:checked + span { background:#4f46e5; color:#fff; }
                .pz-paper { position:relative; background:#fff; border:1px solid #e2e8f0; border-radius:4px; box-shadow:0 6px 18px rgba(15,23,42,.08); padding:12px 10px; aspect-ratio: 1 / 1.414; overflow:hidden; font-size:6px; color:#64748b; }
                .pz-paper.land { aspect-ratio: 1.414 / 1; }
                .pz-p-hd { display:flex; justify-content:space-between; font-weight:800; color:#1e293b; font-size:7px; }
                .pz-p-bar { margin:6px 0; text-align:center; font-weight:800; font-size:6.5px; padding:3px; border-top:1.5px solid var(--pa); color:var(--pa); background:#f5f7ff; text-transform:uppercase; letter-spacing:.06em; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
                .pz-p-sum { height:12px; border:1px solid #e2e8f0; border-radius:2px; margin-bottom:5px; }
                .pz-p-th { display:flex; gap:1px; background:var(--pa); color:#fff; font-weight:700; font-size:4.6px; padding:2px; }
                .pz-p-th span { flex:1; overflow:hidden; white-space:nowrap; }
                .pz-p-tr { height:5px; border-bottom:1px solid #eef1f6; }
                .pz-p-stamp { position:absolute; top:22%; right:10%; transform:rotate(-14deg); border:2px double currentColor; border-radius:4px; padding:2px 6px; font-weight:800; font-size:8px; letter-spacing:.08em; }
                .pz-p-wm { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; transform:rotate(-30deg); font-weight:800; font-size:18px; color:rgba(79,70,229,.08); }
                .pz-p-sigs { display:flex; justify-content:flex-end; gap:6px; margin-top:8px; }
                .pz-p-sigs span { flex:0 1 30%; border-top:1px solid #333; padding-top:1px; font-size:4.5px; text-align:center; }
                .pz-p-gen { margin-top:5px; text-align:center; font-size:4.5px; color:#94a3b8; }
                .pz-sigwrap { display:inline-flex; align-items:center; gap:2px; }
                .pz-sigpen { width:26px; height:26px; border:1px dashed #cbd5e1; border-radius:50%; background:#fff; color:#64748b; font-size:11px; cursor:pointer; }
                .pz-sigpen:hover { border-color:#6366f1; color:#4f46e5; }
                .pz-sigpen.has { border-style:solid; border-color:#10b981; color:#059669; background:#ecfdf5; }
                .pz-pad { margin-top:8px; border:1px solid #c7d2fe; border-radius:8px; padding:8px; background:#f8faff; }
                .pz-pad-hd { display:flex; justify-content:space-between; align-items:baseline; font-size:12px; color:#1e293b; margin-bottom:6px; }
                .pz-pad canvas { width:100%; height:auto; aspect-ratio: 720 / 200; background:#fff; border:1px dashed #cbd5e1; border-radius:6px; touch-action:none; cursor:crosshair; display:block;
                    background-image: linear-gradient(#fff, #fff), linear-gradient(#e2e8f0, #e2e8f0); background-size: 100% 100%, 80% 1px; background-position: 0 0, 50% 78%; background-repeat:no-repeat; }
                .pz-pad-bar { display:flex; gap:6px; align-items:center; margin-top:6px; flex-wrap:wrap; }
                .pz-btn { display:inline-flex; gap:5px; align-items:center; padding:5px 10px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; font-size:11.5px; font-weight:600; color:#334155; cursor:pointer; }
                .pz-btn.primary { background:#4f46e5; border-color:#4f46e5; color:#fff; }
                .pz-btn.danger { color:#dc2626; }
                .pz-p-seal { position:absolute; bottom:9%; left:42%; width:22px; height:22px; border:1.5px double #1d4ed8; border-radius:50%; color:#1d4ed8; font-size:4px; font-weight:800; display:flex; align-items:center; justify-content:center; transform:rotate(-12deg); opacity:.8; }
                .pz-p-seal.img { border:none; object-fit:contain; }
                .pz-p-sigs img { display:block; max-height:9px; max-width:90%; margin:0 auto 1px; }
                @media (max-width: 900px) { .pz { grid-template-columns:1fr; max-height:none; } .pz-prev { display:none; } }
            </style>
            <div class="pz">
                <section>
                    <h5>What to print</h5>
                    ${opt('range', 'Departure date range', 'From the currently loaded records', `<div class="pd-dates"><input type="date" id="pdFrom" value="${period.from || today}"> to <input type="date" id="pdTo" value="${period.to || today}"></div>`)}
                    ${cfCanPick ? opt('selected', n ? `Selected records (${n})` : 'Selected records', n ? 'Only the rows you ticked' : 'Tick the rows you want in the table') : ''}
                    ${opt('shown', 'What the table shows now', shownLabel)}
                    <h5>Categories <span><a data-all="1">All</a><a data-all="0">None</a></span></h5>
                    <div class="pz-chips">${catHtml}</div>
                    <h5>Note on the report</h5>
                    <select id="pdNoteTemplate">${keys.map(k => `<option value="${k}">${esc(cfg.noteTemplateLabels[k] || k)}</option>`).join('')}<option value="custom">Custom — write your own</option></select>
                    <textarea id="pdNote">${esc(keys.length ? cfg.noteTemplates[keys[0]] : '')}</textarea>
                </section>
                <section>
                    <h5>Title &amp; look</h5>
                    <input type="text" id="pzTitle" maxlength="80" placeholder="Contract Flight Report" value="${esc(cfTitleOverride || sv.title || '')}">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;gap:10px;flex-wrap:wrap;">
                        <div class="pz-seg"><label><input type="radio" name="pzOrient" value="portrait" ${studioOrient === 'portrait' ? 'checked' : ''}><span>Portrait</span></label><label><input type="radio" name="pzOrient" value="landscape" ${studioOrient === 'landscape' ? 'checked' : ''}><span>Landscape</span></label></div>
                        <div class="pz-chips">${accentHtml}</div>
                    </div>
                    <h5>Columns <span><a data-cols="all">All</a><a data-cols="default">Default</a></span></h5>
                    <div class="pz-chips">${colHtml}</div>
                    <h5>Stamp</h5>
                    <div class="pz-chips">${stampHtml}</div>
                    <input type="text" id="pzStampText" maxlength="24" placeholder="Stamp text, e.g. SETTLED" value="${esc(sv.stamp_text || '')}" style="margin-top:6px;">
                    <h5>Signatures <span class="pz-hint" style="text-transform:none;letter-spacing:0;font-weight:500;">pen = draw or upload</span></h5>
                    <div class="pz-chips">${sigHtml}</div>
                    <div class="pz-pad" id="pzPad" hidden>
                        <div class="pz-pad-hd"><b id="pzPadTitle">Signature</b><span class="pz-hint">Draw with mouse / finger, or upload an image</span></div>
                        <canvas id="pzPadCanvas" width="720" height="200"></canvas>
                        <div class="pz-pad-bar">
                            <label class="pz-btn"><i class="fas fa-upload"></i> Upload<input type="file" id="pzPadFile" accept="image/png,image/jpeg,image/webp" hidden></label>
                            <button type="button" class="pz-btn" id="pzPadClear"><i class="fas fa-eraser"></i> Clear</button>
                            <button type="button" class="pz-btn danger" id="pzPadRemove"><i class="fas fa-trash"></i> Remove saved</button>
                            <span style="flex:1"></span>
                            <button type="button" class="pz-btn" id="pzPadClose">Close</button>
                            <button type="button" class="pz-btn primary" id="pzPadSave"><i class="fas fa-check"></i> Use signature</button>
                        </div>
                    </div>
                    <h5>Company seal</h5>
                    <div class="pz-chips" id="pzSealOpts">
                        <label class="pz-chip"><input type="radio" name="pzSeal" value="" ${!sv.seal ? 'checked' : ''}><span>None</span></label>
                        <label class="pz-chip"><input type="radio" name="pzSeal" value="auto" ${sv.seal === 'auto' ? 'checked' : ''}><span><i class="fas fa-circle-notch"></i> Round seal (company name)</span></label>
                        <label class="pz-chip"><input type="radio" name="pzSeal" value="image" ${sv.seal === 'image' ? 'checked' : ''}><span><i class="fas fa-stamp"></i> My seal image</span></label>
                        <label class="pz-btn" title="PNG with a transparent background looks best"><i class="fas fa-upload"></i> <span id="pzSealUpLabel">${sigImgs.seal ? 'Replace seal image' : 'Upload seal image'}</span><input type="file" id="pzSealFile" accept="image/png,image/jpeg,image/webp" hidden></label>
                        <img id="pzSealThumb" src="${sigImgs.seal || ''}" alt="" style="height:30px;${sigImgs.seal ? '' : 'display:none;'}">
                    </div>
                    <h5>Generated by</h5>
                    <div class="pz-row">
                        <input type="text" id="pzGen" maxlength="60" placeholder="Name on the sheet" value="${esc(sv.gen ?? PS_ME)}">
                        ${tog('pzGenOn', 'Show generator name', on('genOn'))}
                    </div>
                    <h5>Extras</h5>
                    <div class="pz-row">
                        ${tog('pzSummary', 'Summary strip', on('summary'))}
                        <input type="checkbox" id="pzWords" hidden>
                        <input type="checkbox" id="pzLogo" hidden>
                        <input type="checkbox" id="pzBank" hidden>
                    </div>
                    <input type="text" id="pzWm" maxlength="20" placeholder="Watermark (optional), e.g. CONFIDENTIAL" value="${esc(sv.watermark || '')}" style="margin-top:6px;">
                    <div style="display:flex;justify-content:space-between;margin-top:10px;">
                        ${tog('pzRemember', 'Remember these settings', sv.remember !== false)}
                        <a id="pzReset" style="font-size:11px;color:#64748b;cursor:pointer;align-self:center;">Reset to classic</a>
                    </div>
                </section>
                <section class="pz-prev"><h5>Preview</h5><div class="pz-paper" id="pzPaper"></div></section>
            </div>`,
            didOpen: () => {
                const $ = id => document.getElementById(id);
                const all = sel => Array.from(document.querySelectorAll(sel));
                const radio = document.querySelector('input[name=pdMode][value=selected]');
                if (radio && !n) radio.addEventListener('change', () => { if (radio.checked) { Swal.close(); psStartPicking(prefix); } });
                $('pdNoteTemplate').addEventListener('change', e => { const k = e.target.value; $('pdNote').value = k === 'custom' ? '' : (cfg.noteTemplates[k] || ''); if (k === 'custom') $('pdNote').focus(); });

                const paint = () => {
                    const accent = (document.querySelector('input[name=pzAccent]:checked') || {}).value || '#4f46e5';
                    const stampV = (document.querySelector('input[name=pzStamp]:checked') || {}).value || '';
                    $('pzStampText').style.display = stampV === 'custom' ? '' : 'none';
                    const stampT = stampV === 'custom' ? ($('pzStampText').value || 'STAMP').toUpperCase() : (stampV ? stamps[stampV] : '');
                    const stampC = ['paid', 'received', 'verified'].includes(stampV) ? '#059669' : stampV === 'due' ? '#dc2626' : '#4f46e5';
                    const cols = all('.pz-col:checked').map(c => PS_COLS[c.value].label.replace(' (৳)', ''));
                    const sealV = (document.querySelector('input[name=pzSeal]:checked') || {}).value || '';
                    const paper = $('pzPaper');
                    paper.classList.toggle('land', (document.querySelector('input[name=pzOrient]:checked') || {}).value === 'landscape');
                    paper.style.setProperty('--pa', accent);
                    paper.innerHTML = `
                        ${$('pzWm').value.trim() ? `<div class="pz-p-wm">${esc($('pzWm').value.toUpperCase())}</div>` : ''}
                        <div class="pz-p-hd"><span>${$('pzLogo').checked ? '▣ LOGO' : 'Company'}</span><span>Epal ERP</span></div>
                        <div class="pz-p-bar">${esc($('pzTitle').value.trim() || 'Contract Flight Report')}</div>
                        ${$('pzSummary').checked ? '<div class="pz-p-sum"></div>' : ''}
                        <div class="pz-p-th">${cols.map(c => `<span>${esc(c)}</span>`).join('')}</div>
                        ${'<div class="pz-p-tr"></div>'.repeat(9)}
                        ${$('pzWords').checked ? '<div style="margin-top:4px;">In words: …</div>' : ''}
                        ${$('pzBank').checked ? '<div style="margin-top:3px;">Pay to: our bank accounts</div>' : ''}
                        <div class="pz-p-sigs" style="${all('.pz-sig:checked').length > 1 ? 'justify-content:space-between' : ''}">${all('.pz-sig:checked').map(c => `<span>${psSigImages()[c.value] ? `<img src="${psSigImages()[c.value]}">` : ''}${esc(PS_SIGS[c.value])}</span>`).join('')}</div>
                        ${sealV === 'auto' ? '<div class="pz-p-seal">SEAL</div>' : (sealV === 'image' && psSigImages().seal ? `<img class="pz-p-seal img" src="${psSigImages().seal}">` : '')}
                        ${$('pzGenOn').checked ? `<div class="pz-p-gen">Generated by ${esc($('pzGen').value)}</div>` : ''}
                        ${stampT ? `<div class="pz-p-stamp" style="color:${stampC}">${esc(stampT)}</div>` : ''}`;
                };
                all('[data-all]').forEach(a => a.addEventListener('click', () => { all('.pz-cat').forEach(c => c.checked = a.dataset.all === '1'); }));
                all('[data-cols]').forEach(a => a.addEventListener('click', () => { all('.pz-col').forEach(c => c.checked = a.dataset.cols === 'all' || PS_COLS[c.value].on); paint(); }));
                $('pzReset').addEventListener('click', () => { try { localStorage.removeItem(PS_STUDIO_KEY); } catch (_) {} Swal.close(); setTimeout(() => psStatementStudio(prefix), 250); });
                document.querySelector('.pz').addEventListener('input', paint);
                document.querySelector('.pz').addEventListener('change', paint);
                paint();

                // An uploaded image, shrunk to at most 600×300 so it fits browser storage.
                const readImage = (file, done) => {
                    if (!file || !/^image\//.test(file.type)) return;
                    const fr = new FileReader();
                    fr.onload = () => {
                        const im = new Image();
                        im.onload = () => {
                            const k = Math.min(1, 600 / im.width, 300 / im.height);
                            const c = document.createElement('canvas');
                            c.width = Math.round(im.width * k); c.height = Math.round(im.height * k);
                            c.getContext('2d').drawImage(im, 0, 0, c.width, c.height);
                            done(c.toDataURL('image/png'), im, c);
                        };
                        im.src = fr.result;
                    };
                    fr.readAsDataURL(file);
                };
                const saved = (slot, url) => {
                    if (!psSigStore(slot, url)) { Swal.showValidationMessage('Browser storage is full or blocked — could not keep the image.'); return false; }
                    return true;
                };
                const refreshPen = slot => {
                    const b = document.querySelector(`[data-sigpen="${slot}"]`);
                    if (!b) return;
                    const has = !!psSigImages()[slot];
                    b.classList.toggle('has', has);
                    b.title = has ? 'Change signature' : 'Add signature';
                    b.innerHTML = `<i class="fas fa-${has ? 'signature' : 'pen-nib'}"></i>`;
                };

                // Seal: upload switches the choice to "My seal image".
                $('pzSealFile').addEventListener('change', e => readImage(e.target.files[0], url => {
                    if (!saved('seal', url)) return;
                    $('pzSealThumb').src = url; $('pzSealThumb').style.display = '';
                    $('pzSealUpLabel').textContent = 'Replace seal image';
                    document.querySelector('input[name=pzSeal][value=image]').checked = true;
                    paint();
                }));

                // Signature pad: one canvas, opened for whichever slot's pen was pressed.
                const pad = $('pzPad'), cv = $('pzPadCanvas'), ctx = cv.getContext('2d');
                let slot = null, drawing = false, dirty = false, last = null;
                const resetPad = () => { ctx.clearRect(0, 0, cv.width, cv.height); dirty = false; };
                const pos = e => { const r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * cv.width / r.width, (e.clientY - r.top) * cv.height / r.height]; };
                cv.addEventListener('pointerdown', e => { drawing = true; last = pos(e); cv.setPointerCapture(e.pointerId); });
                cv.addEventListener('pointermove', e => {
                    if (!drawing) return;
                    const p = pos(e);
                    ctx.strokeStyle = '#0b2a6f'; ctx.lineWidth = 3.2; ctx.lineCap = ctx.lineJoin = 'round';
                    ctx.beginPath(); ctx.moveTo(last[0], last[1]); ctx.lineTo(p[0], p[1]); ctx.stroke();
                    last = p; dirty = true;
                });
                ['pointerup', 'pointercancel', 'pointerleave'].forEach(ev => cv.addEventListener(ev, () => { drawing = false; }));
                all('[data-sigpen]').forEach(b => b.addEventListener('click', () => {
                    slot = b.dataset.sigpen;
                    $('pzPadTitle').textContent = PS_SIGS[slot] + ' — signature';
                    resetPad();
                    const existing = psSigImages()[slot];
                    if (existing) { const im = new Image(); im.onload = () => { const k = Math.min(cv.width / im.width, cv.height / im.height); ctx.drawImage(im, (cv.width - im.width * k) / 2, (cv.height - im.height * k) / 2, im.width * k, im.height * k); }; im.src = existing; }
                    pad.hidden = false;
                    const tick = document.querySelector(`.pz-sig[value="${slot}"]`);
                    if (tick) tick.checked = true;
                    pad.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }));
                $('pzPadFile').addEventListener('change', e => readImage(e.target.files[0], (url, im) => {
                    resetPad();
                    const k = Math.min(cv.width / im.width, cv.height / im.height);
                    ctx.drawImage(im, (cv.width - im.width * k) / 2, (cv.height - im.height * k) / 2, im.width * k, im.height * k);
                    dirty = true; e.target.value = '';
                }));
                $('pzPadClear').addEventListener('click', resetPad);
                $('pzPadClose').addEventListener('click', () => { pad.hidden = true; });
                $('pzPadRemove').addEventListener('click', () => { if (slot) { saved(slot, null); refreshPen(slot); } resetPad(); pad.hidden = true; paint(); });
                $('pzPadSave').addEventListener('click', () => {
                    if (!slot) return;
                    if (!dirty) { Swal.showValidationMessage('Draw or upload a signature first.'); return; }
                    // Trim the empty margins so the signature sits tight above its line.
                    const d = ctx.getImageData(0, 0, cv.width, cv.height).data;
                    let x0 = cv.width, y0 = cv.height, x1 = 0, y1 = 0;
                    for (let y = 0; y < cv.height; y++) for (let x = 0; x < cv.width; x++) if (d[(y * cv.width + x) * 4 + 3] > 10) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
                    if (x1 <= x0) { Swal.showValidationMessage('Draw or upload a signature first.'); return; }
                    const out = document.createElement('canvas');
                    out.width = x1 - x0 + 8; out.height = y1 - y0 + 8;
                    out.getContext('2d').drawImage(cv, x0 - 4, y0 - 4, out.width, out.height, 0, 0, out.width, out.height);
                    if (!saved(slot, out.toDataURL('image/png'))) return;
                    Swal.resetValidationMessage();
                    refreshPen(slot); pad.hidden = true; paint();
                });
            },
            preConfirm: () => {
                const $ = id => document.getElementById(id);
                const all = sel => Array.from(document.querySelectorAll(sel));
                const mode = (document.querySelector('input[name=pdMode]:checked') || {}).value;
                let from = '', to = '', ids = [];
                if (mode === 'range') {
                    from = $('pdFrom').value; to = $('pdTo').value;
                    if (!from && !to) { Swal.showValidationMessage('Pick a start or an end date.'); return false; }
                    if (from && to && from > to) { const t = from; from = to; to = t; }
                } else if (mode === 'selected') ids = Array.from(picks);
                const cats = all('.pz-cat');
                const catsOn = cats.filter(c => c.checked);
                if (cats.length && !catsOn.length) { Swal.showValidationMessage('Tick at least one category.'); return false; }
                const cols = all('.pz-col:checked').map(c => c.value);
                if (!cols.length) { Swal.showValidationMessage('Keep at least one column.'); return false; }
                const st = {
                    title: $('pzTitle').value.trim(), cols, sigs: all('.pz-sig:checked').map(c => c.value),
                    stamp: (document.querySelector('input[name=pzStamp]:checked') || {}).value || '', stamp_text: $('pzStampText').value.trim(),
                    gen: $('pzGen').value.trim(), genOn: $('pzGenOn').checked,
                    summary: $('pzSummary').checked, words: $('pzWords').checked, logo: $('pzLogo').checked, bank: $('pzBank').checked,
                    watermark: $('pzWm').value.trim(), orient: (document.querySelector('input[name=pzOrient]:checked') || {}).value,
                    accent: (document.querySelector('input[name=pzAccent]:checked') || {}).value, remember: $('pzRemember').checked,
                };
                try { if (st.remember) localStorage.setItem(PS_STUDIO_KEY, JSON.stringify(st)); else localStorage.removeItem(PS_STUDIO_KEY); } catch (_) {}
                const imgs = psSigImages();
                st.seal = (document.querySelector('input[name=pzSeal]:checked') || {}).value || '';
                if (st.seal === 'image' && !imgs.seal) { Swal.showValidationMessage('Upload the seal image first, or pick the round seal.'); return false; }
                try { if (st.remember) localStorage.setItem(PS_STUDIO_KEY, JSON.stringify(st)); } catch (_) {}
                return { mode, from, to, ids, cats: catsOn.map(c => c.value), settings: st, note: $('pdNote').value.trim() };
            },
        }).then(res => {
            if (!res.isConfirmed) return;
            const v = res.value;
            if (v.mode === 'selected' && !v.ids.length) { psStartPicking(prefix); return; }
            cfRenderReport(v);
        });
    }

const cfEsc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const psYmd = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
const psDMY = value => value.split('-').reverse().join('/');
const cfVisible = node => !!node.getClientRects().length && getComputedStyle(node).visibility !== 'hidden';
let cfRecords = [];
let cfHeaders = [];
const cfRows = () => cfRecords;
const cfConfig = {
    period: () => cfPeriodOverride || ({from: document.getElementById('mfpFrom')?.value || '', to: document.getElementById('mfpTo')?.value || ''}),
    noteTemplates: {general: 'Please check the passenger, route and ticket details before departure.', custom: ''},
    noteTemplateLabels: {general: 'Passenger details reminder', custom: 'No note'},
};
function cfCapture() {
    cfCanPick = true;
    cfPeriodOverride = null;
    cfTitleOverride = '';
    cfRecords = [];
    cfHeaders = [];
    const tables = [...document.querySelectorAll('main table')].filter(cfVisible);
    tables.forEach((table, tableIndex) => {
        const headers = [...table.querySelectorAll('thead tr:last-child th')];
        if (!headers.length) return;
        const columns = headers.map((cell,index) => ({key:`t${tableIndex}c${index}`, label:cell.textContent.trim().replace(/\s+/g,' '), index}))
            .filter(c => c.label && !/^actions?$/i.test(c.label) && !c.label.includes('Print selection'));
        const records = [...table.querySelectorAll('tbody > tr')].filter(cfVisible)
            .filter(row => row.children.length >= headers.length && !row.matches('[data-sort-attach], .fdl-route-row, .mfp-journey-row'));
        if (!records.length) return;
        cfHeaders.push(...columns);
        records.forEach((row,index) => {
            const cells = {};
            columns.forEach(column => {
                const copy = row.children[column.index]?.cloneNode(true);
                if (!copy) return;
                copy.querySelectorAll('button,input,select,textarea,script,style,[hidden]').forEach(n => n.remove());
                copy.querySelectorAll('*').forEach(n => {
                    [...n.attributes].forEach(a => n.removeAttribute(a.name));
                    if (n.tagName === 'IMG') n.remove();
                });
                cells[column.key] = copy.innerHTML;
            });
            cfRecords.push({id:row.id || `table${tableIndex}-row${index}`, row, cells, table:tableIndex,
                date:row.dataset.printDate || '', category:row.dataset.printCategory || 'Records'});
        });
    });
    // The Passenger Board has cards rather than table rows.
    if (!cfRecords.length) {
        cfHeaders = [{key:'passenger',label:'Passenger / Details'}, {key:'stage',label:'Stage'}];
        document.querySelectorAll('main .kb-card').forEach((card,index) => {
            if (!cfVisible(card)) return;
            const stage = card.closest('.kb-col')?.querySelector('.kb-col-h')?.textContent.trim() || 'Passenger';
            cfRecords.push({id:`board-${index}`,row:card,cells:{passenger:cfEsc(card.innerText),stage:cfEsc(stage)},table:0,date:'',category:stage});
        });
    }
    PS_COLS = Object.fromEntries(cfHeaders.map(c => [c.key, {label:c.label,on:true}]));
    // Column positions can change as dashboard panels are opened.
    for (const id of cfPicks) if (!cfRecords.some(r => r.id === id)) cfPicks.delete(id);
}
function cfCaptureProvided(payload) {
    cfCanPick = false;
    cfPicks.clear();
    cfPeriodOverride = payload.period || {from: '', to: ''};
    cfTitleOverride = payload.title || '';
    cfHeaders = Array.isArray(payload.headers) ? payload.headers : [];
    cfRecords = Array.isArray(payload.records) ? payload.records : [];
    PS_COLS = Object.fromEntries(cfHeaders.map(column => [column.key, {
        label: column.label,
        on: column.on !== false,
    }]));
}
window.cfPrintList = (payload = null) => {
    if (payload?.records && payload?.headers) cfCaptureProvided(payload);
    else cfCapture();
    if (!cfRecords.length) { Swal.fire('No records', 'Load the Contract Flight records you want to print first.', 'info'); return; }
    psStatementStudio('cf');
};
function psStartPicking() {
    document.querySelectorAll('.cf-print-pick').forEach(n => n.remove());
    cfRecords.forEach(record => {
        const tick = document.createElement('input');
        tick.type = 'checkbox'; tick.className = 'cf-print-pick'; tick.checked = cfPicks.has(record.id);
        tick.setAttribute('aria-label', 'Select record for printing');
        tick.style.cssText = 'margin-right:6px;accent-color:#4f46e5;width:16px;height:16px';
        tick.addEventListener('click', e => e.stopPropagation());
        tick.addEventListener('change', () => tick.checked ? cfPicks.add(record.id) : cfPicks.delete(record.id));
        (record.row.matches('tr') ? record.row.firstElementChild : record.row).prepend(tick);
    });
    document.getElementById('cfPrintPicking')?.remove();
    const bar = document.createElement('div'); bar.id = 'cfPrintPicking';
    bar.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:white;border:1px solid #c7d2fe;border-radius:10px;padding:12px;z-index:1000;box-shadow:0 4px 20px #0002;display:flex;gap:12px;align-items:center';
    bar.innerHTML = '<span>Tick records to print</span><button type="button" class="cf-list-print">Continue to print</button><button type="button" class="cf-list-print">Cancel</button>';
    const cleanup = () => { document.querySelectorAll('.cf-print-pick').forEach(n => n.remove()); bar.remove(); };
    bar.querySelectorAll('button')[0].onclick = () => { cleanup(); window.cfPrintList(); };
    bar.querySelectorAll('button')[1].onclick = () => { cleanup(); cfPicks.clear(); };
    document.body.append(bar);
}
function cfRenderReport(value) {
    const settings = value.settings;
    const records = cfRecords.filter(record => value.cats.includes(record.category))
        .filter(record => value.mode !== 'selected' || value.ids.includes(record.id))
        .filter(record => value.mode !== 'range' || (record.date && (!value.from || record.date >= value.from) && (!value.to || record.date <= value.to)));
    if (!records.length) { Swal.fire('No matching records', 'No loaded records match your print choices. Departure ranges require a recorded departure date.', 'info'); return; }
    const columns = cfHeaders.filter(c => settings.cols.includes(c.key));
    if (!columns.length) { Swal.fire('Choose columns', 'Reopen Print and choose columns for this table.', 'info'); return; }
    const accent = /^#[0-9a-f]{6}$/i.test(settings.accent) ? settings.accent : '#4f46e5';
    const stamp = settings.stamp === 'custom' ? settings.stamp_text : ({paid:'PAID',received:'RECEIVED',due:'PAYMENT DUE',original:'ORIGINAL',copy:'COPY',verified:'VERIFIED'}[settings.stamp] || '');
    const images = psSigImages();
    const safeImage = image => /^data:image\/(png|jpeg|webp);base64,[a-z0-9+/=]+$/i.test(image || '') ? image : '';
    const signatures = settings.sigs.map(key => `<div class="signature signature-${key}">${safeImage(images[key]) ? `<img src="${safeImage(images[key])}">` : ''}<span>${cfEsc(PS_SIGS[key])}</span>${key === 'authorized' ? `<small>For, ${cfEsc(PS_COMPANY.name)}</small>` : ''}</div>`).join('');
    const columnWeight = column => {
        const label = column.label.toLowerCase();
        if (/^sl\.?$/.test(label)) return 4;
        if (label.includes('passenger')) return 18;
        if (label.includes('agent')) return 19;
        if (label.includes('ticket') || label.includes('pnr')) return 16;
        if (label.includes('route') || label.includes('departure')) return 14;
        if (label.includes('charge') || label.includes('breakdown')) return 18;
        if (label.includes('cost') || label.includes('profit') || label.includes('due')) return 12;
        if (label.includes('flight') || label.includes('airline')) return 16;
        return 14;
    };
    const tables = [...new Set(records.map(r => r.table))].map(table => {
        const rows = records.filter(r => r.table === table);
        const cols = columns.filter(c => Object.hasOwn(rows[0].cells,c.key));
        if (!cols.length) return '';
        const weights = cols.map(columnWeight);
        const totalWeight = weights.reduce((sum, weight) => sum + weight, 0);
        const colgroup = `<colgroup>${weights.map(weight => `<col style="width:${(weight / totalWeight * 100).toFixed(2)}%">`).join('')}</colgroup>`;
        return `<table>${colgroup}<thead><tr>${cols.map(c => `<th>${cfEsc(c.label)}</th>`).join('')}</tr></thead><tbody>${rows.map(r => `<tr>${cols.map(c => `<td>${r.cells[c.key] || ''}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
    }).join('');
    const title = settings.title || 'Contract Flight Report';
    const categories = [...new Set(records.map(record => record.category))];
    const periodText = value.mode === 'range' ? `${value.from || 'Any'} to ${value.to || 'Any'}` : (cfPeriodOverride?.from || cfPeriodOverride?.to ? `${cfPeriodOverride.from || 'Any'} to ${cfPeriodOverride.to || 'Any'}` : 'Current loaded records');
    const cellText = (record, column) => {
        const box = document.createElement('div');
        box.innerHTML = record.cells[column?.key] || '';
        return box.textContent.replace(/\s+/g, ' ').trim();
    };
    const amounts = text => [...text.matchAll(/(?:à§³|৳)?\s*(-?[\d,]+(?:\.\d+)?)/g)].map(match => Number(match[1].replace(/,/g, ''))).filter(Number.isFinite);
    const chargeColumn = columns.find(column => /charge/i.test(column.label));
    const costColumn = columns.find(column => /cost\s*\/\s*profit/i.test(column.label));
    const travelCostColumn = columns.find(column => /travel cost/i.test(column.label));
    let totalCharge = 0, totalCost = 0, totalProfit = 0, hasFinancials = false;
    records.forEach(record => {
        const chargeText = cellText(record, chargeColumn);
        const chargeMatch = chargeText.match(/charge\s*(?:à§³|৳)?\s*([\d,]+(?:\.\d+)?)/i);
        if (chargeMatch) { totalCharge += Number(chargeMatch[1].replace(/,/g, '')); hasFinancials = true; }
        const costValues = amounts(cellText(record, costColumn));
        if (costValues.length) { totalCost += costValues[0]; totalProfit += costValues[1] || 0; hasFinancials = true; }
        if (!costValues.length) {
            const travelValues = amounts(cellText(record, travelCostColumn));
            if (travelValues.length) { totalCost += travelValues[0]; hasFinancials = true; }
        }
    });
    if (hasFinancials && !totalProfit) totalProfit = totalCharge - totalCost;
    const money = amount => `৳${Number(amount || 0).toLocaleString('en-US', {maximumFractionDigits: 2})}`;
    const closingSummary = hasFinancials
        ? `<span><small>Total Charge</small>${money(totalCharge)}</span><span><small>Total Cost</small>${money(totalCost)}</span><span class="net"><small>Net Profit</small>${money(totalProfit)}</span>`
        : `<span><small>Categories</small>${categories.length}</span><span class="net"><small>Passenger Entries</small>${records.length}</span>`;
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>${cfEsc(title)}</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet"><style>
        @page{size:A4 ${settings.orient === 'landscape' ? 'landscape' : 'portrait'};margin:12mm}
        :root{--tc:${accent};--tbg:#eef2ff;--thd:${accent};--bdr:#e2e8f0;--txt:#1e293b;--tf:"Montserrat",sans-serif;--ff:"Inter",sans-serif;--nf:"Space Mono",monospace}
        *,*:before,*:after{box-sizing:border-box;margin:0;padding:0;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
        body{padding:28px;background:#f0f2f5;color:var(--txt);font:12px var(--ff)}.btn-row{display:flex;justify-content:flex-end;gap:8px;max-width:${settings.orient === 'landscape' ? '1120px' : '794px'};margin:0 auto 12px}.btn-row button{padding:7px 18px;border:0;border-radius:4px;background:var(--thd);color:#fff;font:600 12px var(--tf);cursor:pointer}.btn-row .back{background:#6b7280}.btn-row .outline{border:1px solid var(--thd);background:#fff;color:var(--thd)}
        .card{max-width:${settings.orient === 'landscape' ? '1120px' : '794px'};margin:auto;padding:40px;background:#fff;border-radius:4px;box-shadow:0 4px 20px #0000000f}.hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.hdr img{max-width:80px;max-height:82px;object-fit:contain}.co-info{text-align:right;font-size:12px;line-height:1.7}.co-info strong{display:block;font:700 16px var(--tf)}
        .label-bar{margin-bottom:24px;padding:10px;border-top:2px solid var(--tc);background:var(--tbg);color:var(--tc);text-align:center;text-transform:uppercase;letter-spacing:2px;font:700 11px var(--tf)}.meta-row{display:flex;justify-content:space-between;align-items:flex-start;gap:40px;margin-bottom:20px}.scope{max-width:62%}.scope h4{margin-bottom:5px;color:var(--tc);text-transform:uppercase;letter-spacing:.05em;font:700 11px var(--tf)}.scope p{margin:1px 0;font-size:12px;line-height:1.55}.scope .muted{color:#64748b;font-size:10px}.meta-tbl{width:auto;flex:none}.meta-tbl td{padding:2px 0 2px 18px;border:0;font-size:11px;font-weight:600;white-space:nowrap}.meta-tbl td:first-child{padding-left:0}.meta-tbl .nf{font:10px var(--nf)}
        .summary-strip{display:flex;overflow:hidden;margin-bottom:20px;border:1px solid var(--bdr);border-radius:6px}.sum-card{flex:1;padding:10px 14px;border-right:1px solid var(--bdr)}.sum-card:last-child{border:0;background:var(--thd)}.sum-label{margin-bottom:3px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;font-size:8px;font-weight:600}.sum-card:last-child .sum-label{color:#c7d2fe}.sum-value{color:var(--txt);font:700 11px var(--nf)}.sum-card:last-child .sum-value{color:#fff;font-size:13px}
        table{width:100%;border-collapse:collapse}.ledger{margin-bottom:20px;font-size:9.5px}.ledger table{table-layout:fixed}.ledger th{padding:7px 6px;background:var(--thd);color:#fff;text-align:left;text-transform:uppercase;letter-spacing:.04em;font:700 8px var(--tf)}.ledger td{padding:5px 6px;border-bottom:1px solid var(--bdr);vertical-align:top;overflow-wrap:anywhere;line-height:1.3}.ledger tbody tr:nth-child(even) td{background:#f8f9ff}.ledger thead{display:table-header-group}.ledger tr{break-inside:avoid;page-break-inside:avoid}
        .closing-strip{display:flex;align-items:stretch;min-height:30px;background:var(--thd);color:#fff;font:700 8px var(--tf)}.closing-title{display:flex;align-items:center;flex:1;padding:7px 10px}.closing-values{display:flex;align-items:stretch}.closing-values span{display:flex;align-items:flex-end;justify-content:center;flex-direction:column;min-width:115px;padding:5px 10px;border-left:1px solid #ffffff38;font:700 9px var(--nf)}.closing-values small{margin-bottom:2px;color:#c7d2fe;text-transform:uppercase;letter-spacing:.04em;font:600 6.5px var(--tf)}.closing-values .net{background:#3730a3;color:#dcfce7}.footer-bottom{display:grid;grid-template-columns:1fr auto;gap:36px;align-items:end;min-height:84px;padding-top:14px}.footer-note{max-width:680px;color:#64748b;font-size:8px;line-height:1.7}.footer-note .custom-note{display:block;margin-bottom:4px;color:#475569}.signatures{display:flex;justify-content:flex-end;align-items:flex-end;gap:36px}.signature{width:160px;text-align:center;break-inside:avoid}.signature span{display:block;padding-top:4px;border-top:1px solid #333;font:700 8px var(--tf)}.signature small{display:block;margin-top:3px;font-size:7.5px}.signature img{height:30px;max-width:120px;object-fit:contain}.watermark{position:fixed;top:40%;left:10%;width:80%;text-align:center;transform:rotate(-30deg);font-size:60px;opacity:.07;pointer-events:none}.stamp{display:inline-block;margin:12px;padding:6px 12px;border:3px double currentColor;color:var(--tc);transform:rotate(-12deg);font:700 18px var(--tf)}.seal{width:75px;height:75px;margin:12px;object-fit:contain}.round-seal{display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border:4px double var(--tc);border-radius:50%;color:var(--tc);text-align:center;font-size:9px;font-weight:bold}
        @media print{body{padding:0;background:#fff}.btn-row{display:none}.card{max-width:none;padding:0;box-shadow:none}.label-bar{padding:7px;margin-bottom:14px}.hdr{margin-bottom:18px}.meta-row,.summary-strip{margin-bottom:14px}.ledger{margin-bottom:0}.ledger th{padding:6px 5px}.ledger td{padding:4px 5px}.footer-bottom{break-inside:avoid;page-break-inside:avoid}}
        </style></head><body><div class="btn-row"><button class="back" onclick="window.close()">← Back</button><button onclick="window.print()">Print</button><button class="outline" onclick="window.print()">Download PDF</button></div><div class="card">
        ${settings.watermark ? `<div class="watermark">${cfEsc(settings.watermark)}</div>` : ''}
        <div class="hdr"><img src="${cfEsc(PS_COMPANY.logo)}" alt=""><div class="co-info"><strong>${cfEsc(PS_COMPANY.name)}</strong>${PS_COMPANY.address ? `${cfEsc(PS_COMPANY.address)}<br>` : ''}${cfEsc([PS_COMPANY.phone,PS_COMPANY.email].filter(Boolean).join(' | '))}</div></div>
        <div class="label-bar">${cfEsc(title)}</div>
        <div class="meta-row"><div class="scope"><h4>Scope</h4><p><strong>${cfEsc(PS_COMPANY.name)} · ${records.length} passenger ${records.length === 1 ? 'entry' : 'entries'}</strong></p><p class="muted">${cfEsc(categories.join(' · ') || 'All categories')}</p></div><table class="meta-tbl"><tr><td>Showing</td><td class="nf">${cfEsc(periodText)}</td></tr><tr><td>Entries</td><td class="nf">${records.length}</td></tr><tr><td>Generated</td><td class="nf">${cfEsc(new Date().toLocaleString())}</td></tr><tr><td>Printed By</td><td>${cfEsc(settings.gen || PS_ME)}</td></tr></table></div>
        ${settings.summary ? `<div class="summary-strip"><div class="sum-card"><div class="sum-label">Report</div><div class="sum-value">Contract Flight</div></div><div class="sum-card"><div class="sum-label">Categories</div><div class="sum-value">${categories.length}</div></div><div class="sum-card"><div class="sum-label">Date Scope</div><div class="sum-value">${cfEsc(periodText)}</div></div><div class="sum-card"><div class="sum-label">Passenger Entries</div><div class="sum-value">${records.length}</div></div></div>` : ''}
        <div class="ledger">${tables}</div>
        <div class="closing-strip"><div class="closing-title">Closing Summary — ${cfEsc(periodText)}</div><div class="closing-values">${closingSummary}</div></div>
        ${stamp ? `<div class="stamp">${cfEsc(stamp)}</div>` : ''}
        ${settings.seal === 'auto' ? `<div class="round-seal">${cfEsc(PS_COMPANY.name)}<br>Contract Flight</div>` : settings.seal === 'image' && safeImage(images.seal) ? `<img class="seal" src="${safeImage(images.seal)}">` : ''}
        <div class="footer-bottom"><div class="footer-note">${value.note ? `<span class="custom-note">${cfEsc(value.note)}</span>` : ''}This sheet was generated on ${cfEsc(new Date().toLocaleString())} and lists the selected Contract Flight passenger records for ${cfEsc(periodText)}. Amounts reflect the information recorded against these entries at the time of printing.${settings.genOn ? ` Printed by ${cfEsc(settings.gen || PS_ME)}.` : ''}</div><div class="signatures">${signatures}</div></div><script>window.addEventListener('load',()=>Promise.all([document.fonts?.ready||Promise.resolve(),...[...document.images].map(image=>image.complete?Promise.resolve():new Promise(resolve=>{image.onload=resolve;image.onerror=resolve}))]).then(()=>{window.focus();window.print()}));<\/script></div></body></html>`;
    const token = `${Date.now().toString(36)}-${crypto.getRandomValues(new Uint32Array(2)).join('-')}`;
    const storageKey = `cf-print-preview:${token}`;
    try { localStorage.setItem(storageKey, html); }
    catch (_) { Swal.fire('Print unavailable', 'The browser could not prepare this print page. Clear site storage and try again.', 'error'); return; }
    const separator = PS_PRINT_PREVIEW_URL.includes('?') ? '&' : '?';
    const popup = window.open(`${PS_PRINT_PREVIEW_URL}${separator}print_token=${encodeURIComponent(token)}`, '_blank');
    if (!popup) {
        localStorage.removeItem(storageKey);
        Swal.fire('Allow pop-ups', 'Allow pop-ups for this site to open the Contract Flight print page.', 'info');
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const actions = document.querySelector('#mfpFilterForm .mfp-filter-actions');
    if (!actions) return;
    const button = document.createElement('button'); button.type = 'button'; button.className = 'cf-list-print';
    button.innerHTML = '<i class="fas fa-print"></i> Print'; button.addEventListener('click', window.cfPrintList); actions.append(button);
});
})();
</script>
