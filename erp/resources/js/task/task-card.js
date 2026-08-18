/**
 * Task Card — Functions used by task cards in the Kanban board.
 * Handles card-level priority/date/assignee/label updates.
 */
import { TaskApi } from './task-api';
import { closeAllDropdowns } from '../ui/dropdown';
import { currentDrawerTaskId, taskLabelSelections, loadTaskActivities,
    updateDrawerAssigneeDisplayComponent, updateDrawerLabelsDisplayComponent,
    updateDrawerPriorityDisplayComponent, updateDrawerStateDisplayComponent
} from './task-drawer';
import { formatDateDisplay, formatDatetimeLocal } from './task-utils';
import { updateDropdownDisplay } from '../ui/dropdown-updater';
import { TaskStore } from './task-store';

const COLOR_CLASSES = {
    'gray': { badge: 'bg-gray-200', text: 'text-gray-700' },
    'blue': { badge: 'bg-blue-200', text: 'text-blue-700' },
    'purple': { badge: 'bg-purple-200', text: 'text-purple-700' },
    'green': { badge: 'bg-green-200', text: 'text-green-700' },
    'yellow': { badge: 'bg-yellow-200', text: 'text-yellow-700' },
    'red': { badge: 'bg-red-200', text: 'text-red-700' },
    'indigo': { badge: 'bg-indigo-200', text: 'text-indigo-700' },
    'pink': { badge: 'bg-pink-200', text: 'text-pink-700' },
    'orange': { badge: 'bg-orange-200', text: 'text-orange-700' },
    'teal': { badge: 'bg-teal-200', text: 'text-teal-700' },
};

// State badge color map keyed by column position (mirrors show.blade.php @switch)
const STATE_BADGE_COLORS = {
    1: { bg: 'bg-gray-100',    text: 'text-gray-600' },
    2: { bg: 'bg-blue-100',    text: 'text-blue-600' },
    3: { bg: 'bg-amber-100',   text: 'text-amber-600' },
    4: { bg: 'bg-emerald-100', text: 'text-emerald-600' },
    5: { bg: 'bg-red-100',     text: 'text-red-600' },
};
const ALL_STATE_BG   = Object.values(STATE_BADGE_COLORS).map(c => c.bg);
const ALL_STATE_TEXT = Object.values(STATE_BADGE_COLORS).map(c => c.text);

/* ── Generic field update (card-level) ── */
export function updateTaskField(config, taskId, field, value, clickedItem, options = {}) {
    TaskApi.updateField(config.role, taskId, { [field]: value })
        .then(async (response) => {
            const effectiveValue = (response && Object.prototype.hasOwnProperty.call(response, field))
                ? response[field]
                : value;

            // Update TaskStore with the latest data
            if (response && response.id) {
                TaskStore.set(response);
            } else {
                // If response doesn't return full task, patch the store
                TaskStore.patch(taskId, { [field]: effectiveValue });
            }
            
            // Update UI instantly without page reload
            if (field === 'priority') updatePriorityUI(taskId, effectiveValue, clickedItem);
            else if (field === 'start_date') updateStartDateUI(taskId, effectiveValue);
            else if (field === 'due_date') updateDueDateUI(taskId, effectiveValue);
            else if (field === 'assigned_users') refreshAssigneeButton(taskId, effectiveValue);
            else if (field === 'label_ids') refreshLabelBadges(taskId, effectiveValue);
            else if (field === 'column_id') {
                moveCardToColumn(taskId, effectiveValue);

                // A state (column) change can move a sub-task into/out of "Done", which the
                // progress ring in the parent's Sub-work items list depends on — refresh that
                // list so the ring updates instantly instead of requiring the drawer to reopen.
                const subtaskRow = document.querySelector(`[data-subtask-id="${taskId}"]`);
                if (subtaskRow && window.currentDrawerTaskId && typeof window._reloadSubtasks === 'function') {
                    window._reloadSubtasks(window.currentDrawerTaskId);
                }
            }

            // Sync to drawer and modal
            const typeMap = {
                'priority': 'priority',
                'column_id': 'state',
                'assigned_users': 'assignees',
                'label_ids': 'labels'
            };
            const type = typeMap[field];
            if (type) {
                // Update drawer if open for this task
                if (currentDrawerTaskId && String(currentDrawerTaskId) === String(taskId)) {
                    updateDropdownDisplay(type, effectiveValue, 'drawer');
                    
                    if (type === 'priority') {
                        updateDrawerPriorityDisplayComponent(effectiveValue);
                    } else if (type === 'state') {
                        updateDrawerStateDisplayComponent(effectiveValue);
                    } else if (type === 'assignees') {
                        updateDrawerAssigneeDisplayComponent(effectiveValue);
                    } else if (type === 'labels') {
                        updateDrawerLabelsDisplayComponent(effectiveValue);
                    }
                }
                
                // Update modal if open for this task
                updateModalIfOpen(type, taskId, effectiveValue);
            }

            if (currentDrawerTaskId && taskId == currentDrawerTaskId) {
                loadTaskActivities(config);
            }
            if (!options || options.close !== false) {
                closeAllDropdowns();
            }
        })
        .catch(err => alert('Error: ' + err.message));
}

export function updateStateBadgeColor(taskId, newColumnId) {
    // A task can render both as a Kanban card and as a sub-work item row at once —
    // both share these data-attributes, so update all matches.
    document.querySelectorAll(`[data-dropdown="state"][data-context="card"][data-task-id="${taskId}"]`).forEach(wrapper => {
        let position = 1;
        wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach((item) => {
            if (String(item.dataset.value) === String(newColumnId)) {
                position = parseInt(item.dataset.position, 10) || 1;
            }
        });

        const toggleBtn = wrapper.querySelector('[data-role="dropdown-toggle"] button');
        if (!toggleBtn) return;

        toggleBtn.classList.remove(...ALL_STATE_BG, ...ALL_STATE_TEXT);
        const colors = STATE_BADGE_COLORS[position] || STATE_BADGE_COLORS[1];
        toggleBtn.classList.add(colors.bg, colors.text);
    });
}

function updateColumnCount(columnId) {
    const columnCard = document.querySelector(`[data-column-id="${columnId}"]`);
    if (!columnCard) return;

    const taskList = columnCard.querySelector(`.task-list[data-column="${columnId}"]`);
    const badge = columnCard.querySelector('.flex.justify-between.items-center .flex.items-center.gap-2 span');

    if (taskList && badge) {
        badge.textContent = String(taskList.querySelectorAll('.task-item').length);
    }
}

function moveCardToColumn(taskId, newColumnId) {
    // Sub-task rows have a live state dropdown but no Kanban card to physically move
    // (sub-tasks are excluded from the board's column lists) — still recolor their badge.
    const card = document.querySelector(`[data-id="${taskId}"]`);
    const targetList = document.querySelector(`.task-list[data-column="${newColumnId}"]`);

    if (card && targetList) {
        const sourceList = card.closest('.task-list');
        const sourceColumnId = sourceList ? sourceList.dataset.column : null;

        if (String(sourceColumnId) !== String(newColumnId)) {
            card.style.opacity = '0.7';
            card.style.transform = 'scale(0.98)';
            targetList.prepend(card);

            requestAnimationFrame(() => {
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            });

            if (sourceColumnId) updateColumnCount(sourceColumnId);
            updateColumnCount(newColumnId);
        }
    }

    updateStateBadgeColor(taskId, newColumnId);
}

/* ── Generic field update (card-level) ── */
// export function updateDrawerField(type, taskId, value) {
//     if (currentDrawerTaskId && String(currentDrawerTaskId) === String(taskId)) {
//         updateDropdownDisplay(type, value, 'drawer');
        
//         if (type === 'assignees') {
//             updateDrawerAssigneeDisplayComponent(value);
//         } else if (type === 'labels') {
//             updateDrawerLabelsDisplayComponent(value);
//         } else if (type === 'priority') {
//             updateDrawerPriorityDisplayComponent(value);
//         } else if (type === 'state') {
//             updateDrawerStateDisplayComponent(value);
//         }
//     }
    
//     // Update modal if it's open for this task
//     updateModalIfOpen(type, taskId, value);
    
//     if (type === 'assignees') {
//         refreshAssigneeButton(taskId, value);
//     } else if (type === 'labels') {
//         refreshLabelBadges(taskId, value);
//     } else if (type === 'priority') {
//         updatePriorityUI(taskId, value, null);
//     }
// }

/* ── Update modal if it's open for this task ── */
function updateModalIfOpen(type, taskId, value) {
    // Check if modal is open
    const taskModal = document.getElementById('taskModal');
    if (!taskModal || taskModal.classList.contains('hidden')) return;
    
    // Check if it's for the same task
    const currentTaskId = document.getElementById('task_id')?.value;
    if (!currentTaskId || String(currentTaskId) !== String(taskId)) return;
    
    // Update the modal fields based on type
    if (type === 'priority') {
        const priorityInput = document.getElementById('priority');
        if (priorityInput) priorityInput.value = value ?? '';
        
        // Update modal visual display
        updateDropdownDisplay(type, value, 'modal');
        updateDrawerPriorityDisplayComponent(value ?? '', 'modal');
    } else if (type === 'state') {
        const columnInput = document.getElementById('column_id');
        if (columnInput) columnInput.value = value ?? '';
        
        // Update modal visual display
        updateDropdownDisplay(type, value, 'modal');
        updateDrawerStateDisplayComponent(value, 'modal');
    } else if (type === 'assignees') {
        const assigneesInput = document.getElementById('assigned_users');
        if (assigneesInput) assigneesInput.value = JSON.stringify(Array.isArray(value) ? value : []);
        
        // Update modal visual display
        updateDropdownDisplay(type, value, 'modal');
        updateDrawerAssigneeDisplayComponent(value || [], 'modal');
    } else if (type === 'labels') {
        const labelsInput = document.getElementById('label_ids');
        if (labelsInput) labelsInput.value = JSON.stringify(Array.isArray(value) ? value : []);
        
        // Update modal visual display
        updateDropdownDisplay(type, value, 'modal');
        updateDrawerLabelsDisplayComponent(value || [], 'modal');
    }
}

/* ── Priority ── */
function updatePriorityUI(taskId, value, clickedItem) {
    console.log('console log two');
    value = value || '';
    // A task can render both as a Kanban card and as a sub-work item row at the same
    // time, and both reuse this same id — querySelectorAll so both stay in sync
    // (getElementById would only ever touch the first one in the DOM).
    document.querySelectorAll(`[id="priority-icon-${taskId}"]`).forEach(icon => {
        icon.classList.remove('text-red-500', 'text-yellow-500', 'text-blue-500', 'text-gray-400');
        if (value === 'high') icon.classList.add('text-red-500');
        else if (value === 'medium') icon.classList.add('text-yellow-500');
        else if (value === 'low') icon.classList.add('text-blue-500');
        else icon.classList.add('text-gray-400');
    });

    const btn = document.getElementById(`priority-btn-${taskId}`);
    if (btn) btn.title = value ? `Priority: ${value.charAt(0).toUpperCase() + value.slice(1)}` : 'Priority: None';

    // Update drawer if open
    if (currentDrawerTaskId === taskId) {
        const map = {
            'high': { icon: '<i class="fa-solid fa-signal text-red-500"></i>', text: 'High' },
            'medium': { icon: '<i class="fa-solid fa-signal text-yellow-500"></i>', text: 'Medium' },
            'low': { icon: '<i class="fa-solid fa-signal text-blue-500"></i>', text: 'Low' },
            'none': { icon: '<i class="fa-solid fa-minus text-gray-400"></i>', text: 'None' },
        };
        const cfg = map[value] || map['none'];
        $('#drawerPriorityIcon').html(cfg.icon);
        $('#drawerPriorityText').text(cfg.text);
    }

    if (clickedItem) {
        const dropdown = document.getElementById(`priority-${taskId}`);
        if (dropdown) {
            dropdown.querySelectorAll('.check').forEach(c => c.classList.add('hidden'));
            clickedItem.querySelector('.check').classList.remove('hidden');
            dropdown.querySelectorAll('.priority-item').forEach(i => i.classList.remove('bg-gray-100'));
            clickedItem.classList.add('bg-gray-100');
        }
    }
}

/* ── Dates ──
 * A task can render both as a Kanban card (startdate-/duedate- ids) and as a sub-work
 * item row (substartdate-/subduedate- ids, distinct on purpose — see task-subtask.js)
 * at the same time, so both id schemes are updated here. */
export function updateStartDateUI(taskId, value) {
    console.log('console log three');
    ['startdate', 'substartdate'].forEach(prefix => {
        const label = document.getElementById(`${prefix}-label-${taskId}`);
        if (label) {
            if (value) {
                // Parse as local time: replace space with 'T' to avoid UTC interpretation
                const dateStr = value.replace(' ', 'T');
                const d = new Date(dateStr);
                label.textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            } else {
                label.textContent = '';
            }
        }

        const input = document.querySelector(`#${prefix}-${taskId} input[type="datetime-local"]`);
        if (input) {
            input.value = value ? formatDatetimeLocal(value) : '';
        }
    });

    // Update drawer if open for this task
    if (currentDrawerTaskId && String(currentDrawerTaskId) === String(taskId)) {
        if (value) {
            $('#drawerStartDateDisplay').text(formatDateDisplay(value));
            $('#drawerStartDateInput').val(formatDatetimeLocal(value));
        } else {
            $('#drawerStartDateDisplay').text('Add start date');
            $('#drawerStartDateInput').val('');
        }
    }
}

export function updateDueDateUI(taskId, value) {
    console.log('console log four');
    ['duedate', 'subduedate'].forEach(prefix => {
        const label = document.getElementById(`${prefix}-label-${taskId}`);
        if (label) {
            if (value) {
                // Parse as local time: replace space with 'T' to avoid UTC interpretation
                const dateStr = value.replace(' ', 'T');
                const d = new Date(dateStr);
                label.textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            } else {
                label.textContent = '';
            }
        }

        const input = document.querySelector(`#${prefix}-${taskId} input[type="datetime-local"]`);
        if (input) {
            input.value = value ? formatDatetimeLocal(value) : '';
        }
    });

    // Update drawer if open for this task
    if (currentDrawerTaskId && String(currentDrawerTaskId) === String(taskId)) {
        if (value) {
            $('#drawerDueDateDisplay').text(formatDateDisplay(value));
            $('#drawerDueDateInput').val(formatDatetimeLocal(value));
        } else {
            $('#drawerDueDateDisplay').text('Add due date');
            $('#drawerDueDateInput').val('');
        }
    }
}

export function setQuickStartDate(config, taskId, preset) {
    console.log('console log five');
    const today = new Date();
    let target = today;
    if (preset === 'tomorrow') {
        target = new Date(today);
        target.setDate(target.getDate() + 1);
    }
    // Use formatDatetimeLocal to format, then convert to server format (YYYY-MM-DD HH:mm:ss)
    const localDatetime = formatDatetimeLocal(target.toISOString());
    const formattedDate = localDatetime.replace('T', ' ') + ':00';

    const input = document.querySelector(`#startdate-${taskId} input[type="datetime-local"]`);
    if (input) {
        input.value = localDatetime;
    }
    const label = document.getElementById(`startdate-label-${taskId}`);
    if (label) {
        label.textContent = formatDateDisplay(formattedDate);
    }

    updateTaskField(config, taskId, 'start_date', formattedDate, null);
}

export function setQuickDueDate(config, taskId, preset) {
    console.log('console log six');
    const today = new Date();
    let target = today;
    if (preset === 'tomorrow') {
        target = new Date(today);
        target.setDate(target.getDate() + 1);
    }
    // Use formatDatetimeLocal to format, then convert to server format (YYYY-MM-DD HH:mm:ss)
    const localDatetime = formatDatetimeLocal(target.toISOString());
    const formattedDate = localDatetime.replace('T', ' ') + ':00';

    const input = document.querySelector(`#duedate-${taskId} input[type="datetime-local"]`);
    if (input) {
        input.value = localDatetime;
    }
    const label = document.getElementById(`duedate-label-${taskId}`);
    if (label) {
        label.textContent = formatDateDisplay(formattedDate);
    }

    updateTaskField(config, taskId, 'due_date', formattedDate, null);
}

// /* ── Assignees ── */
// export function toggleAssignee(config, taskId, userId, clickedItem) {
//         console.log('console log seven');
//     if (!clickedItem) return;
//     clickedItem.classList.toggle('bg-gray-100');
//     const check = clickedItem.querySelector('.check');
//     if (check) check.classList.toggle('hidden');
//     collectSelectedUsers(config, taskId);
// }

// function collectSelectedUsers(config, taskId) {
//     console.log('console log eight');
//     const dropdown = document.getElementById(`assignee-${taskId}`);
//     const selected = [];
//     dropdown.querySelectorAll('.assignee-item').forEach(item => {
//         if (!item.querySelector('.check').classList.contains('hidden')) selected.push(item.dataset.user);
//     });
//     updateTaskField(config, taskId, 'assigned_users', selected, null);
// }

export function refreshAssigneeButton(taskId, assigneeIds = null) {
    console.log('console log nine');
    // Support component dropdown system. A task can render both as a Kanban card and
    // as a sub-work item row at once — both share these data-attributes, so update all matches.
    const wrappers = document.querySelectorAll(`[data-dropdown="assignees"][data-context="card"][data-task-id="${taskId}"]`);
    if (!wrappers.length) {
        console.warn('No assignee dropdown wrapper found for task', taskId);
        return;
    }

    wrappers.forEach(wrapper => {
        const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
        const toggleBtn = wrapper.querySelector('[data-role="dropdown-toggle"]');
        if (!labelEl && !toggleBtn) return;

        // Get selected assignees from provided IDs or from dropdown checkboxes
        const users = [];
        if (assigneeIds !== null) {
            // Direct IDs provided (e.g., from drawer)
            const ids = (Array.isArray(assigneeIds) ? assigneeIds : []).map(String);
            wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
                const id = String(item.dataset.value);
                if (ids.includes(id)) {
                    const span = item.querySelector('span');
                    const name = span ? span.textContent.trim() : 'User';
                    const initial = (name[0] || 'U').toUpperCase();
                    users.push({ id, name, initial });
                }
            });
        } else {
            // Read from checkbox states
            wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
                const checkbox = item.querySelector('[data-role="checkbox"]');
                if (checkbox && checkbox.getAttribute('aria-checked') === 'true') {
                    const id = String(item.dataset.value);
                    const span = item.querySelector('span');
                    const name = span ? span.textContent.trim() : 'User';
                    const initial = (name[0] || 'U').toUpperCase();
                    users.push({ id, name, initial });
                }
            });
        }

        const count = users.length;
        let html = '';

        if (count === 0) {
            html = '<div class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white" title="Unassigned"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg></div>';
        } else {
            const maxAvatars = count <= 3 ? count : 2;
            const extra = count > 3 ? count - 2 : 0;
            const show = users.slice(0, maxAvatars);

            const avatars = show.map(u =>
                `<div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[10px] font-semibold flex items-center justify-center ring-2 ring-white" title="${u.name}">${u.initial}</div>`
            ).join('');

            const extraHtml = extra > 0 ? `<span class="ml-1 text-[10px] font-semibold text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded-full">+${extra}</span>` : '';
            html = `<div class="flex items-center"><div class="flex -space-x-2">${avatars}</div>${extraHtml}</div>`;
        }

        if (labelEl) labelEl.innerHTML = html;
        else if (toggleBtn) toggleBtn.innerHTML = html;
    });
}

/* ── Labels ── */
export function refreshLabelBadges(taskId, labelIds = null) {
    // Support component dropdown system
    const wrapper = document.querySelector(`[data-dropdown="labels"][data-context="card"][data-task-id="${taskId}"]`);
    if (!wrapper) {
        console.warn('No labels dropdown wrapper found for task', taskId);
        return;
    }

    const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
    const toggleBtn = wrapper.querySelector('[data-role="dropdown-toggle"]');
    if (!labelEl && !toggleBtn) return;

    // Get selected labels from provided IDs or from dropdown checkboxes
    const labels = [];
    if (labelIds !== null) {
        // Direct IDs provided (e.g., from drawer)
        const ids = (Array.isArray(labelIds) ? labelIds : []).map(String);
        wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
            const id = String(item.dataset.value);
            if (ids.includes(id)) {
                const name = item.dataset.name || item.querySelector('span.truncate, span:last-child')?.textContent?.trim() || 'Label';
                const color = item.dataset.color || 'blue';
                labels.push({ id, name, color });
            }
        });
    } else {
        // Read from checkbox states
        wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
            const checkbox = item.querySelector('[data-role="checkbox"]');
            if (checkbox && checkbox.getAttribute('aria-checked') === 'true') {
                const id = String(item.dataset.value);
                const name = item.dataset.name || item.querySelector('span.truncate, span:last-child')?.textContent?.trim() || 'Label';
                const color = item.dataset.color || 'blue';
                labels.push({ id, name, color });
            }
        });
    }

    const count = labels.length;
    let html = '';
    
    if (count === 0) {
        html = '<div class="w-5 h-5 rounded bg-gray-300 flex items-center justify-center text-gray-500 ring-2 ring-white"><svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/></svg></div>';
    } else if (count <= 3) {
        const badges = labels.map(l => {
            const displayName = l.name.length > 12 ? l.name.substring(0, 12) + '...' : l.name;
            const c = COLOR_CLASSES[l.color] || COLOR_CLASSES['blue'];
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium ${c.badge} ${c.text}" title="${l.name}">${displayName}</span>`;
        }).join('');
        html = `<div class="flex flex-wrap gap-1">${badges}</div>`;
    } else {
        html = `<div class="inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100 text-[11px] text-gray-600"><svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/></svg><span>${count} Labels</span></div>`;
    }

    if (labelEl) labelEl.innerHTML = html;
    else if (toggleBtn) toggleBtn.innerHTML = html;
}

/* ── Move card to different column ── */
// function moveCardToColumn(taskId, newColumnId) {
//     const card = document.querySelector(`[data-id="${taskId}"]`);
//     const targetColumn = document.querySelector(`[data-column="${newColumnId}"] .task-list`);
    
//     if (card && targetColumn) {
//         // Animate out from current position
//         card.style.opacity = '0.5';
//         card.style.transform = 'scale(0.95)';
        
//         setTimeout(() => {
//             // Move to new column
//             targetColumn.appendChild(card);
            
//             // Animate in
//             setTimeout(() => {
//                 card.style.opacity = '1';
//                 card.style.transform = 'scale(1)';
//             }, 50);
//         }, 200);
//     }
// }

/* ── Delete task (soft-delete) ── Shared by Kanban cards and sub-work item rows. */
export function deleteTask(config, taskId) {
    if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) return;

    TaskApi.deleteTask(config.role, taskId).then(data => {
        if (data.success) {
            const card = document.querySelector(`[data-id="${taskId}"]`);
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => card.remove(), 300);
            }
            if (currentDrawerTaskId === taskId) {
                // import closeTaskDrawer dynamically to avoid circular
                import('./task-drawer').then(m => m.closeTaskDrawer());
            }

            // If this task was rendered as a sub-work item row, refresh that list.
            const subtaskRow = document.querySelector(`[data-subtask-id="${taskId}"]`);
            if (subtaskRow && window.currentDrawerTaskId && typeof window._reloadSubtasks === 'function') {
                window._reloadSubtasks(window.currentDrawerTaskId);
            }
        } else {
            alert('Failed to delete task: ' + (data.message || 'Unknown error'));
        }
    }).catch(err => { console.error(err); alert('Failed to delete task'); });
}

export function copyTask(config, taskId) {
    if (!confirm('Create a duplicate copy of this task?')) return;

    TaskApi.copyTask(config.role, taskId).then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Failed to copy task: ' + (data.message || 'Unknown error'));
        }
    }).catch(err => { console.error(err); alert('Failed to copy task'); });
}
