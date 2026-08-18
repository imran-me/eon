/**
 * Task Sub-work items — Loading, rendering, and row-level actions for the
 * "Sub-work items" list inside the task drawer.
 *
 * Row-level state/priority/date/assignee controls reuse the exact same
 * data-role/data-context markup as the Kanban card's <x-ui.dropdown> footer,
 * so the existing DropdownEngine, dropdown:change listener (board-show.js)
 * and task-card.js update helpers work on them unmodified. Edit/Delete reuse
 * the same components as the rest of the app (openEditModal, task-card's
 * deleteTask) rather than duplicating that logic here.
 */
import { TaskApi } from './task-api';
import { escapeHtml, formatDateDisplay, formatDatetimeLocal } from './task-utils';

const STATE_BADGE_COLORS = {
    1: { bg: 'bg-gray-100',    text: 'text-gray-600' },
    2: { bg: 'bg-blue-100',    text: 'text-blue-600' },
    3: { bg: 'bg-amber-100',   text: 'text-amber-600' },
    4: { bg: 'bg-emerald-100', text: 'text-emerald-600' },
    5: { bg: 'bg-red-100',     text: 'text-red-600' },
};

const SUBTASK_RING_CIRCUMFERENCE = 2 * Math.PI * 6; // r=6 (see the SVG circle in show.blade.php)

/* ═══════════ Load + render ═══════════ */

export function loadSubtasks(config, taskId) {
    const $list = $('#drawerSubtasksList');
    $list.html('<div class="px-3 py-4 text-center text-xs text-gray-400">Loading…</div>');

    TaskApi.getSubtasks(config.role, taskId)
        .then(data => {
            if (!data.success) return;
            renderSubtasks(data.subtasks, data.total, data.done, config, taskId);
        })
        .catch(() => {
            $list.html('<div class="px-3 py-4 text-center text-xs text-red-400">Failed to load sub-tasks</div>');
        });
}

function renderSubtasks(subtasks, total, done, config, taskId) {
    const $count = $('#drawerSubtasksCount');
    const $list  = $('#drawerSubtasksList');
    const $prog  = $('#drawerSubtasksProgress');

    $count.text(total ? `${done}/${total}` : '0');

    if (total > 0) {
        const pct = done / total;
        $prog.removeClass('hidden').addClass('flex');
        $prog.find('.subtask-ring').css('stroke-dashoffset', SUBTASK_RING_CIRCUMFERENCE * (1 - pct));
        $prog.find('.subtask-pct').text(`${done}/${total}`);
    } else {
        $prog.addClass('hidden').removeClass('flex');
    }

    if (!subtasks || subtasks.length === 0) {
        $list.html('<div class="px-3 py-6 text-center text-xs text-gray-400">No sub-tasks yet. Click + to create one.</div>');
        return;
    }

    $list.html(subtasks.map(sub => renderSubtaskRow(sub, config, taskId)).join(''));
}

/* ═══════════ Row-level live components ═══════════ */

function stateItemIcon(position) {
    switch (position) {
        case 2: return '<span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span>';
        case 3: return '<span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span>';
        case 4: return '<svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
        case 5: return '<svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
        default: return '<span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>';
    }
}

function dropdownItem(value, selected, innerHtml, attrs = '') {
    return `<div data-role="dropdown-item" data-value="${value}" ${attrs} class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-xs ${selected ? 'bg-gray-100' : ''}">
        ${innerHtml}
        <svg data-role="dropdown-check" class="w-3 h-3 ml-auto text-gray-400 ${selected ? '' : 'hidden'}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    </div>`;
}

function buildStateDropdown(sub, config) {
    const colors = STATE_BADGE_COLORS[sub.column_position] || STATE_BADGE_COLORS[1];
    const items = (config.columns || []).map(col =>
        dropdownItem(col.id, String(col.id) === String(sub.column_id), `${stateItemIcon(col.position)}<span>${escapeHtml(col.name)}</span>`, `data-position="${col.position}"`)
    ).join('');

    return `<div class="relative" data-dropdown="state" data-context="card" data-dropdown-id="substate-${sub.id}" data-task-id="${sub.id}" data-multi="false" style="overflow: visible;">
        <input type="hidden" data-role="dropdown-selected" value="${sub.column_id ?? ''}">
        <div data-role="dropdown-toggle" class="inline-block w-full">
            <button type="button" class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium hover:opacity-80 transition ${colors.bg} ${colors.text}">
                <span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>
                <span data-role="dropdown-label">${escapeHtml(sub.column_name)}</span>
            </button>
        </div>
        <div id="panel-substate-${sub.id}" data-role="dropdown-panel" class="hidden fixed w-44 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999]">
            <div class="p-2 border-b border-gray-100"><input type="text" data-role="dropdown-search" placeholder="Search..." class="w-full text-xs px-2 py-1 border rounded focus:outline-none focus:border-blue-400"></div>
            <div data-role="dropdown-items" class="py-1 overflow-y-auto" style="max-height: 280px; min-height: 50px;">${items}</div>
        </div>
    </div>`;
}

function buildPriorityDropdown(sub) {
    const colorClass = { high: 'text-red-500', medium: 'text-yellow-500', low: 'text-blue-500' }[sub.priority] || 'text-gray-400';
    const options = [
        { value: 'high', label: 'High', icon: '<i class="fa-solid fa-signal text-red-500"></i>' },
        { value: 'medium', label: 'Medium', icon: '<i class="fa-solid fa-signal text-yellow-500"></i>' },
        { value: 'low', label: 'Low', icon: '<i class="fa-solid fa-signal text-blue-500"></i>' },
        { value: '', label: 'None', icon: '<i class="fa-solid fa-minus text-gray-400"></i>' },
    ];
    const items = options.map(o => dropdownItem(o.value, (sub.priority || '') === o.value, `${o.icon}<span>${o.label}</span>`)).join('');

    return `<div class="relative" data-dropdown="priority" data-context="card" data-dropdown-id="subpriority-${sub.id}" data-task-id="${sub.id}" data-multi="false" style="overflow: visible;">
        <input type="hidden" data-role="dropdown-selected" value="${sub.priority ?? ''}">
        <div data-role="dropdown-toggle" class="inline-block w-full">
            <button type="button" class="p-1.5 rounded hover:bg-gray-100 transition" title="Priority: ${sub.priority ? escapeHtml(sub.priority.charAt(0).toUpperCase() + sub.priority.slice(1)) : 'None'}">
                <i id="priority-icon-${sub.id}" class="fa-solid fa-signal ${colorClass}"></i>
            </button>
        </div>
        <div id="panel-subpriority-${sub.id}" data-role="dropdown-panel" class="hidden fixed w-32 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999]">
            <div data-role="dropdown-items" class="py-1 overflow-y-auto" style="max-height: 280px; min-height: 50px;">${items}</div>
        </div>
    </div>`;
}

function buildDateButton(sub, type) {
    const isStart = type === 'start_date';
    // Prefixed with "sub" so these ids never collide with the Kanban card's own
    // startdate-/duedate- elements when the same task is rendered as a card AND
    // as a sub-work item row at the same time (see dropdown.js for the matching
    // positioning/closing special-cases).
    const prefix = isStart ? 'substartdate' : 'subduedate';
    const raw = isStart ? sub.start_date : sub.due_date;
    const iconColor = isStart ? 'text-blue-500' : 'text-red-500';
    const label = raw ? formatDateDisplay(raw) : '';
    const inputVal = raw ? formatDatetimeLocal(raw) : '';
    const quickFn = isStart ? 'setQuickStartDate' : 'setQuickDueDate';

    return `<div class="relative overflow-visible">
        <button onclick="event.stopPropagation(); toggleDropdown('${prefix}-${sub.id}')"
                class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 cursor-pointer transition text-[11px] text-gray-600"
                id="${prefix}-btn-${sub.id}" data-dropdown-toggle="${prefix}-${sub.id}" title="${isStart ? 'Start' : 'Due'} Date">
            <svg class="w-3.5 h-3.5 ${iconColor}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
            <span id="${prefix}-label-${sub.id}">${label}</span>
            ${raw ? `<span onclick="event.stopPropagation(); updateTaskField(${sub.id}, '${type}', '', null);" class="text-gray-400 hover:text-red-500 ml-0.5">&times;</span>` : ''}
        </button>
        <div id="${prefix}-${sub.id}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] p-3">
            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">${isStart ? 'Start' : 'Due'} Date</label>
            <input type="datetime-local" value="${inputVal}" class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-blue-400" onclick="event.stopPropagation()" onblur="event.stopPropagation(); updateTaskFieldNoClose(${sub.id}, '${type}', this.value, null);">
            <div class="flex gap-2 mt-2">
                <button onclick="event.stopPropagation(); ${quickFn}(${sub.id}, 'today')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Today</button>
                <button onclick="event.stopPropagation(); ${quickFn}(${sub.id}, 'tomorrow')" class="flex-1 text-[10px] px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">Tomorrow</button>
            </div>
        </div>
    </div>`;
}

function buildAssigneesDropdown(sub, config) {
    const assignees = sub.assignees || [];
    const selectedCsv = assignees.map(a => a.id).join(',');
    const selectedIds = new Set(assignees.map(a => String(a.id)));

    let toggleHtml;
    if (assignees.length) {
        const maxAvatars = assignees.length <= 3 ? assignees.length : 2;
        const extra = assignees.length > 3 ? assignees.length - 2 : 0;
        const avatars = assignees.slice(0, maxAvatars).map(a =>
            a.image
                ? `<img src="${escapeHtml(a.image)}" alt="${escapeHtml(a.name)}" class="w-5 h-5 rounded-full object-cover ring-2 ring-white -ml-2 first:ml-0" title="${escapeHtml(a.name)}">`
                : `<div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white -ml-2 first:ml-0" title="${escapeHtml(a.name)}">${escapeHtml(a.initial)}</div>`
        ).join('');
        const extraHtml = extra > 0 ? `<span class="ml-1 text-[10px] font-semibold text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded-full">+${extra}</span>` : '';
        toggleHtml = `<div class="flex items-center"><div class="flex -space-x-2" data-role="dropdown-label">${avatars}</div>${extraHtml}</div>`;
    } else {
        toggleHtml = `<div class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white" title="Unassigned"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg></div>`;
    }

    const items = (config.users || []).map(u => {
        const selected = selectedIds.has(String(u.id));
        const avatar = u.image
            ? `<img src="${escapeHtml(u.image)}" alt="${escapeHtml(u.name)}" class="w-full h-full object-cover rounded-full">`
            : escapeHtml(u.name.charAt(0).toUpperCase());
        return dropdownItem(
            u.id, selected,
            `<div class="w-6 h-6 rounded-full bg-indigo-500 flex items-center justify-center text-xs text-white font-semibold">${avatar}</div><span>${escapeHtml(u.name)}</span>`,
            `data-name="${escapeHtml(u.name)}" data-image="${u.image ? escapeHtml(u.image) : ''}"`
        );
    }).join('');

    return `<div class="relative" data-dropdown="assignees" data-context="card" data-dropdown-id="subassignees-${sub.id}" data-task-id="${sub.id}" data-multi="true" style="overflow: visible;">
        <input type="hidden" data-role="dropdown-selected" value="${selectedCsv}">
        <div data-role="dropdown-toggle" class="inline-block w-full">
            <button type="button" class="cursor-pointer inline-flex items-center">${toggleHtml}</button>
        </div>
        <div id="panel-subassignees-${sub.id}" data-role="dropdown-panel" class="hidden fixed w-44 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999]">
            <div class="p-2 border-b border-gray-100"><input type="text" data-role="dropdown-search" placeholder="Search members..." class="w-full text-xs px-2 py-1 border rounded focus:outline-none focus:border-blue-400"></div>
            <div data-role="dropdown-items" class="py-1 overflow-y-auto" style="max-height: 280px; min-height: 50px;">${items}</div>
        </div>
    </div>`;
}

function buildSubtaskMenu(sub, parentTaskId) {
    // "sub-task-menu-" (not "task-menu-") so this never collides with the Kanban
    // card's own 3-dot menu id when the same task renders as a card AND a row at once.
    return `<div class="relative shrink-0">
        <button id="sub-task-menu-btn-${sub.id}" onclick="event.stopPropagation(); toggleDropdown('sub-task-menu-${sub.id}')" class="p-1 rounded hover:bg-gray-100 text-gray-500 opacity-0 group-hover:opacity-100 transition-opacity" title="More">
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/></svg>
        </button>
        <div id="sub-task-menu-${sub.id}" class="hidden fixed w-48 bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] py-1">
            <button onclick="event.stopPropagation(); closeAllDropdowns(); window.editSubtaskItem && window.editSubtaskItem(${sub.id})" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/></svg>
                Edit work item
            </button>
            <button onclick="event.stopPropagation(); closeAllDropdowns(); window.copySubtaskLink && window.copySubtaskLink(${sub.id})" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M12.586 2.586a2 2 0 012.828 0l2 2a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0 1 1 0 011.414-1.414.5.5 0 00.707 0l5-5a.5.5 0 000-.707l-2-2a.5.5 0 00-.707 0l-2 2A1 1 0 019.172 4l2.414-2.414z"/><path d="M7.414 7.414a2 2 0 012.828 0 1 1 0 11-1.414 1.414.5.5 0 00-.707 0l-5 5a.5.5 0 000 .707l2 2a.5.5 0 00.707 0l2-2A1 1 0 019.828 16l-2.414 2.414a2 2 0 01-2.828 0l-2-2a2 2 0 010-2.828l5-5z"/></svg>
                Copy work item link
            </button>
            <button onclick="event.stopPropagation(); closeAllDropdowns(); window.removeSubtaskFromParent && window.removeSubtaskFromParent(${sub.id}, ${parentTaskId})" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                Remove work item
            </button>
            <button onclick="event.stopPropagation(); closeAllDropdowns(); window.deleteTask && window.deleteTask(${sub.id})" class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                Delete work item
            </button>
        </div>
    </div>`;
}

function renderSubtaskRow(sub, config, parentTaskId) {
    return `
    <div class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 group cursor-pointer" data-subtask-id="${sub.id}"
        onclick="if (!event.target.closest('button, input, [data-role], a')) { window.openSubtaskDrawer && window.openSubtaskDrawer(${sub.id}); }">
        <div class="flex-1 min-w-0">
            <span class="text-xs font-medium text-gray-400 mr-1.5">TASK-${sub.id}</span>
            <span class="text-sm text-gray-800">${escapeHtml(sub.title)}</span>
        </div>
        <div class="flex items-center gap-1 shrink-0">
            ${buildStateDropdown(sub, config)}
            ${buildPriorityDropdown(sub)}
            ${buildDateButton(sub, 'start_date')}
            ${buildDateButton(sub, 'due_date')}
            ${buildAssigneesDropdown(sub, config)}
            ${buildSubtaskMenu(sub, parentTaskId)}
        </div>
    </div>`;
}

/* ═══════════ Row actions ═══════════ */

export function openSubtaskDrawer(config, subtaskId) {
    // Open drawer with minimal info; the drawer fetches activities/attachments/links itself
    TaskApi.fetchTask(config.role, subtaskId)
        .then(data => {
            if (data.success && data.task) {
                window.openTaskDrawer(
                    subtaskId, data.task.title || 'Sub-task', data.task.description || '',
                    data.task.assignee_ids || [], data.task.start_date || null, data.task.due_date || null,
                    data.task.priority || null, data.task.column_id || null, data.task.label_ids || [],
                    data.task.created_by_name || '', data.task.parent_id || null, data.task.parent_title || null
                );
            }
        })
        .catch(err => { console.error(err); alert('Failed to open sub-work item.'); });
}

export function openParentDrawer(config, parentId) {
    TaskApi.fetchTask(config.role, parentId)
        .then(data => {
            if (data.success && data.task) {
                window.openTaskDrawer(
                    parentId, data.task.title || 'Task', data.task.description || '',
                    data.task.assignee_ids || [], data.task.start_date || null, data.task.due_date || null,
                    data.task.priority || null, data.task.column_id || null, data.task.label_ids || [],
                    data.task.created_by_name || '', data.task.parent_id || null, data.task.parent_title || null
                );
            }
        })
        .catch(err => { console.error(err); alert('Failed to open parent work item.'); });
}

export function editSubtaskItem(config, taskId) {
    // Reuses the existing "Edit work item" modal component (task-modal.js), the same one
    // used for regular Kanban cards — just prefilled with this sub-task's data.
    TaskApi.fetchTask(config.role, taskId)
        .then(data => {
            if (data.success && data.task) {
                window.openEditModal(
                    taskId, data.task.title || '', data.task.description || '',
                    data.task.assignee_ids || [], data.task.start_date || null, data.task.due_date || null,
                    data.task.priority || null, data.task.column_id || null, data.task.label_ids || []
                );
            }
        })
        .catch(err => { console.error(err); alert('Failed to open edit form.'); });
}

/* ── Copy link (mirrors task-link.js's copyLinkToClipboard) ── */
export function copySubtaskLink(taskId) {
    const url = `${window.location.origin}${window.location.pathname}?task=${taskId}`;
    const done = () => {
        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'success', title: 'Link copied', timer: 1200, showConfirmButton: false });
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(err => console.error('Copy failed:', err));
    } else {
        // Fallback for browsers that don't support the Clipboard API
        const textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            textArea.remove();
            done();
        } catch (err) {
            console.error('Fallback copy failed:', err);
            textArea.remove();
        }
    }
}

export function removeSubtaskFromParent(config, taskId, parentId) {
    if (!confirm('Remove this sub-work item? It will become a top-level task.')) return;

    TaskApi.updateField(config.role, taskId, { parent_id: null })
        .then(() => loadSubtasks(config, parentId))
        .catch(err => { console.error(err); alert(err.message || 'Error removing sub-work item.'); });
}
