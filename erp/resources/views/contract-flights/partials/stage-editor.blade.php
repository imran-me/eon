{{-- The stage editor: the pipeline behind every passenger's journey strip
     (reorder, rename, icon, optional, add, delete). One copy, opened by the
     ✎ on the journey card everywhere (owner, 2026-10-03: "keep the edit
     option of the stages, everywhere") — the Flight Dashboard's ledger and
     Flights & Passengers. Moved here from dashboard-ledger unchanged except
     that it no longer reaches into the ledger: after a save it calls the
     page's window.onStageEditorSaved(stages), or reloads if there is none.

     Only for users who may edit, and only while the save route exists, so a
     lagging server shows no ✎ rather than a broken one. --}}
@php
    try {
        $seCan = auth()->user()?->can('edit contract flight') && \Illuminate\Support\Facades\Route::has('role.contract-flight-stages.sync');
    } catch (\Throwable) { $seCan = false; }
@endphp
@if($seCan)
@once
<style>
    /* ── Stage editor ── */
    .fdl-se { position: fixed; inset: 0; z-index: 1200; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .fdl-se[hidden] { display: none; }
    .fdl-se-backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .45); }
    .fdl-se-panel {
        position: relative; display: flex; flex-direction: column; width: min(760px, 100%); max-height: min(88vh, 720px);
        background: #fff; border-radius: 14px; box-shadow: 0 24px 60px rgba(15, 23, 42, .28); overflow: hidden;
    }
    .fdl-se-head { display: flex; align-items: center; gap: 10px; padding: 14px 18px 10px; }
    .fdl-se-head h3 { margin: 0; font-size: 15px; font-weight: 800; color: #1e293b; }
    .fdl-se-head h3 i { color: #6366f1; margin-right: 6px; }
    .fdl-se-close { margin-left: auto; width: 30px; height: 30px; border: 0; border-radius: 8px; background: #f1f5f9; color: #475569; cursor: pointer; }
    .fdl-se-close:hover { background: #e2e8f0; }
    .fdl-se-hint { margin: 0; padding: 0 18px 10px; font-size: 12px; color: #64748b; }
    .fdl-se-list { flex: 1 1 auto; overflow-y: auto; margin: 0; padding: 4px 18px 8px; list-style: none; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
    .fdl-se-row {
        display: grid; grid-template-columns: 18px 22px 28px minmax(120px, 1fr) 150px auto auto 30px; align-items: center; gap: 8px;
        margin: 6px 0; padding: 6px 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
        transition: box-shadow .15s ease, border-color .15s ease, opacity .15s ease;
    }
    .fdl-se-row:hover { border-color: #c7d2fe; }
    .fdl-se-row.is-dragging { opacity: .45; }
    .fdl-se-row.is-over { border-color: #6366f1; box-shadow: 0 0 0 2px #c7d2fe; }
    .fdl-se-grip { color: #cbd5e1; cursor: grab; text-align: center; }
    .fdl-se-row:active .fdl-se-grip { cursor: grabbing; }
    .fdl-se-num { font-size: 11px; font-weight: 700; color: #94a3b8; text-align: right; font-variant-numeric: tabular-nums; }
    .fdl-se-pin { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #eef2ff; color: #4f46e5; font-size: 11px; }
    .fdl-se-row input[type="text"], .fdl-se-row select {
        width: 100%; min-width: 0; box-sizing: border-box; height: 32px; padding: 0 9px; border: 1px solid #cbd5e1; border-radius: 8px;
        font-size: 13px; color: #1e293b; background: #fff;
    }
    .fdl-se-row input[type="text"]:focus, .fdl-se-row select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px #e0e7ff; }
    .fdl-se-row select { text-transform: capitalize; }
    .fdl-se-opt { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; color: #475569; white-space: nowrap; cursor: pointer; }
    .fdl-se-moves { display: inline-flex; gap: 2px; }
    .fdl-se-moves button, .fdl-se-del {
        width: 26px; height: 26px; border: 1px solid #e2e8f0; border-radius: 7px; background: #fff; color: #64748b; font-size: 10px; cursor: pointer;
    }
    .fdl-se-moves button:hover:not(:disabled) { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
    .fdl-se-moves button:disabled { opacity: .35; cursor: default; }
    .fdl-se-del:hover:not(:disabled) { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .fdl-se-del:disabled { opacity: .35; cursor: not-allowed; }
    .fdl-se-row.is-system .fdl-se-num::after { content: '•'; margin-left: 2px; color: #6366f1; }
    .fdl-se-error { margin: 8px 18px 0; padding: 8px 12px; border-radius: 8px; background: #fef2f2; color: #b91c1c; font-size: 12px; font-weight: 600; }
    .fdl-se-foot { display: flex; align-items: center; gap: 8px; padding: 12px 18px 14px; }
    .fdl-se-spacer { flex: 1; }
    .fdl-se-add, .fdl-se-cancel, .fdl-se-save {
        height: 34px; padding: 0 14px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid transparent;
    }
    .fdl-se-add { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
    .fdl-se-add:hover { background: #e0e7ff; }
    .fdl-se-cancel { background: #fff; color: #475569; border-color: #cbd5e1; }
    .fdl-se-cancel:hover { background: #f1f5f9; }
    .fdl-se-save { background: #4f46e5; color: #fff; }
    .fdl-se-save:hover:not(:disabled) { background: #4338ca; }
    .fdl-se-save:disabled { opacity: .6; cursor: wait; }
    @media (max-width: 720px) {
        .fdl-se-row { grid-template-columns: 18px 22px 28px 1fr 30px; }
        .fdl-se-row select, .fdl-se-opt, .fdl-se-moves { grid-column: 4 / span 1; }
        .fdl-se-row .fdl-se-del { grid-column: 5; grid-row: 1; }
    }
</style>
<div id="fdStageEditor" class="fdl-se" hidden role="dialog" aria-modal="true" aria-labelledby="fdStageEditorTitle">
    <div class="fdl-se-backdrop" onclick="closeStageEditor()"></div>
    <div class="fdl-se-panel">
        <div class="fdl-se-head">
            <h3 id="fdStageEditorTitle"><i class="fas fa-route"></i> Passenger journey stages</h3>
            <button type="button" class="fdl-se-close" onclick="closeStageEditor()" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <p class="fdl-se-hint">Drag a row to reorder. Rename, change the icon, mark a stage optional, or delete it. Applies to every passenger on every flight.</p>
        <ol id="fdStageEditorList" class="fdl-se-list"></ol>
        <div id="fdStageEditorError" class="fdl-se-error" role="alert" hidden></div>
        <div class="fdl-se-foot">
            <button type="button" class="fdl-se-add" onclick="stageDraftAdd()"><i class="fas fa-plus"></i> Add stage</button>
            <span class="fdl-se-spacer"></span>
            <button type="button" class="fdl-se-cancel" onclick="closeStageEditor()">Cancel</button>
            <button type="button" id="fdStageEditorSave" class="fdl-se-save" onclick="saveStageEditor()"><i class="fas fa-check"></i> Save stages</button>
        </div>
    </div>
</div>
<script>
@php
    $seTuples = array_map(fn ($s) => [$s['key'], $s['label'], $s['icon'], $s['is_conditional'], $s['is_system'], $s['id']], \App\Models\ContractFlightPassenger::stageRoute());
@endphp
const SE_ICONS = @json(\App\Models\ContractFlightStage::ICONS);
const SE_SYNC_URL = @json(route('role.contract-flight-stages.sync', ['role' => $role]));
let SE_ROUTE = @json(array_values($seTuples));
function seEsc(v) {
    return String(v === null || v === undefined ? '' : v)
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
// Edits a working copy; nothing reaches the server until Save, which sends
// the whole list and gets the settled one back. Rows drag by their handle
// (and move with the arrow buttons for keyboards); a system stage keeps its
// delete disabled because code moves passengers to it by key.
let FD_STAGE_DRAFT = [];
let FD_STAGE_DRAG = null;

function openStageEditor() {
    FD_STAGE_DRAFT = SE_ROUTE.map(([key, label, icon, conditional, system, id]) => ({ key, label, icon, conditional: !!conditional, system: !!system, id }));
    const modal = document.getElementById('fdStageEditor');
    document.getElementById('fdStageEditorError').hidden = true;
    renderStageEditor();
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    modal.querySelector('.fdl-se-row input[type="text"]')?.focus({ preventScroll: true });
}

function closeStageEditor() {
    document.getElementById('fdStageEditor').hidden = true;
    document.body.style.overflow = '';
}

function stageIconOptions(selected) {
    const list = SE_ICONS.includes(selected) ? SE_ICONS : [selected, ...SE_ICONS];
    return list.map(icon => `<option value="${seEsc(icon)}"${icon === selected ? ' selected' : ''}>${seEsc(icon.replace(/^fa-/, '').replace(/-/g, ' '))}</option>`).join('');
}

function renderStageEditor() {
    const body = document.getElementById('fdStageEditorList');
    body.innerHTML = FD_STAGE_DRAFT.map((stage, index) => `
        <li class="fdl-se-row${stage.system ? ' is-system' : ''}" draggable="true" data-index="${index}">
            <span class="fdl-se-grip" title="Drag to reorder" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>
            <span class="fdl-se-num">${index + 1}</span>
            <span class="fdl-se-pin"><i class="fas ${seEsc(stage.icon || 'fa-circle')}"></i></span>
            <input type="text" value="${seEsc(stage.label)}" maxlength="80" placeholder="Stage name" aria-label="Stage ${index + 1} name" oninput="stageDraftSet(${index}, 'label', this.value)">
            <select aria-label="Stage ${index + 1} icon" onchange="stageDraftSet(${index}, 'icon', this.value)">${stageIconOptions(stage.icon || 'fa-circle')}</select>
            <label class="fdl-se-opt" title="Optional stage: shown as 'not required' once a passenger has passed it"><input type="checkbox"${stage.conditional ? ' checked' : ''} onchange="stageDraftSet(${index}, 'conditional', this.checked)"> Optional</label>
            <span class="fdl-se-moves">
                <button type="button" onclick="stageDraftMove(${index}, -1)" ${index === 0 ? 'disabled' : ''} aria-label="Move up"><i class="fas fa-chevron-up"></i></button>
                <button type="button" onclick="stageDraftMove(${index}, 1)" ${index === FD_STAGE_DRAFT.length - 1 ? 'disabled' : ''} aria-label="Move down"><i class="fas fa-chevron-down"></i></button>
            </span>
            <button type="button" class="fdl-se-del" onclick="stageDraftRemove(${index})" ${stage.system ? 'disabled title="Built-in stage: the system moves passengers here automatically, so it can be renamed or moved but not deleted"' : 'title="Delete stage"'} aria-label="Delete stage"><i class="fas fa-trash"></i></button>
        </li>`).join('');
}

function stageDraftSet(index, field, value) {
    const stage = FD_STAGE_DRAFT[index];
    if (!stage) return;
    stage[field] = value;
    if (field === 'icon') {
        const pin = document.querySelector(`.fdl-se-row[data-index="${index}"] .fdl-se-pin i`);
        if (pin) pin.className = `fas ${value}`;
    }
}

function stageDraftMove(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= FD_STAGE_DRAFT.length) return;
    [FD_STAGE_DRAFT[index], FD_STAGE_DRAFT[target]] = [FD_STAGE_DRAFT[target], FD_STAGE_DRAFT[index]];
    renderStageEditor();
    document.querySelector(`.fdl-se-row[data-index="${target}"] .fdl-se-moves button${delta < 0 ? ':first-child' : ':last-child'}`)?.focus();
}

function stageDraftRemove(index) {
    if (FD_STAGE_DRAFT[index]?.system) return;
    FD_STAGE_DRAFT.splice(index, 1);
    renderStageEditor();
}

function stageDraftAdd() {
    FD_STAGE_DRAFT.push({ key: null, label: '', icon: 'fa-circle', conditional: false, system: false, id: null });
    renderStageEditor();
    const rows = document.querySelectorAll('.fdl-se-row');
    const last = rows[rows.length - 1];
    last?.scrollIntoView({ block: 'nearest' });
    last?.querySelector('input[type="text"]')?.focus();
}

// Native drag and drop on the rows: dragging one and dropping it on another
// moves it to that slot.
document.addEventListener('dragstart', event => {
    const row = event.target.closest?.('.fdl-se-row');
    if (!row) return;
    FD_STAGE_DRAG = Number(row.dataset.index);
    row.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    try { event.dataTransfer.setData('text/plain', String(FD_STAGE_DRAG)); } catch (e) {}
});
document.addEventListener('dragover', event => {
    const row = event.target.closest?.('.fdl-se-row');
    if (!row || FD_STAGE_DRAG === null) return;
    event.preventDefault();
    document.querySelectorAll('.fdl-se-row.is-over').forEach(el => el.classList.remove('is-over'));
    if (Number(row.dataset.index) !== FD_STAGE_DRAG) row.classList.add('is-over');
});
document.addEventListener('drop', event => {
    const row = event.target.closest?.('.fdl-se-row');
    if (!row || FD_STAGE_DRAG === null) return;
    event.preventDefault();
    const to = Number(row.dataset.index);
    if (to !== FD_STAGE_DRAG) {
        const [moved] = FD_STAGE_DRAFT.splice(FD_STAGE_DRAG, 1);
        FD_STAGE_DRAFT.splice(to, 0, moved);
    }
    FD_STAGE_DRAG = null;
    renderStageEditor();
});
document.addEventListener('dragend', () => {
    FD_STAGE_DRAG = null;
    document.querySelectorAll('.fdl-se-row.is-dragging, .fdl-se-row.is-over').forEach(el => el.classList.remove('is-dragging', 'is-over'));
});

async function saveStageEditor() {
    const error = document.getElementById('fdStageEditorError');
    const button = document.getElementById('fdStageEditorSave');
    error.hidden = true;
    const blank = FD_STAGE_DRAFT.findIndex(s => !String(s.label || '').trim());
    if (blank >= 0) {
        error.textContent = `Stage ${blank + 1} needs a name.`;
        error.hidden = false;
        document.querySelector(`.fdl-se-row[data-index="${blank}"] input[type="text"]`)?.focus();
        return;
    }
    button.disabled = true;
    try {
        const response = await fetch(SE_SYNC_URL, {
            method: 'PUT', credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({ stages: FD_STAGE_DRAFT.map(s => ({ id: s.id, label: s.label.trim(), icon: s.icon, is_conditional: s.conditional })) })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            const firstValidation = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(firstValidation || data.message || 'Could not save the stages. Please try again.');
        }
        SE_ROUTE = data.stages.map(s => [s.key, s.label, s.icon, s.is_conditional, s.is_system, s.id]);
        closeStageEditor();
        // The page decides what a new list means for it: the dashboard repaints
        // its ledger in place; a server-rendered list just reloads.
        if (typeof window.onStageEditorSaved === 'function') {
            window.onStageEditorSaved(data.stages);
            if (typeof Swal !== 'undefined') Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Stages saved',
                showConfirmButton: false, timer: 2200});
        } else {
            location.reload();
        }
    } catch (e) {
        error.textContent = e.message;
        error.hidden = false;
    } finally {
        button.disabled = false;
    }
}
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !document.getElementById('fdStageEditor').hidden) closeStageEditor();
});
</script>
@endonce
@endif
