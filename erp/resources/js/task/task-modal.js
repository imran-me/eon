/**
 * Task Modal — Create / Edit modal logic (UI dropdown component version)
 */
import { TaskApi } from './task-api';
import { getDropdownItemText, setComponentDropdownSelection } from '../ui/dropdown-selection';
import {
    updateDrawerStateDisplayComponent,
    updateDrawerPriorityDisplayComponent,
    updateDrawerAssigneeDisplayComponent,
    updateDrawerLabelsDisplayComponent,
} from './task-drawer';
import { TaskStore } from './task-store';

function setVal(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

function setJson(id, arr) {
    const el = document.getElementById(id);
    if (el) el.value = JSON.stringify(Array.isArray(arr) ? arr : []);
}

function getJson(id) {
    const el = document.getElementById(id);
    try { return JSON.parse(el?.value || '[]'); } catch { return []; }
}

async function refreshBoardListsInPlace() {
    const response = await fetch(window.location.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });

    if (!response.ok) {
        throw new Error('Failed to refresh board');
    }

    const html = await response.text();
    const parser = new DOMParser();
    const nextDoc = parser.parseFromString(html, 'text/html');

    const currentLists = document.querySelectorAll('.task-list[data-column]');
    currentLists.forEach((listEl) => {
        const columnId = listEl.dataset.column;
        if (!columnId) return;

        const nextListEl = nextDoc.querySelector(`.task-list[data-column="${columnId}"]`);
        if (!nextListEl) return;

        listEl.innerHTML = nextListEl.innerHTML;

        const currentColumnCard = listEl.closest('[data-column-id]');
        if (currentColumnCard) {
            const countBadge = currentColumnCard.querySelector('.flex.justify-between.items-center .flex.items-center.gap-2 span');
            if (countBadge) {
                countBadge.textContent = String(window.kbcCount ? window.kbcCount(listEl) : listEl.querySelectorAll('.task-item').length);
            }
        }
    });

    // The refresh brought fresh infinite-scroll sentinels along; watch them.
    if (window.kbcObserve) window.kbcObserve();
}

/**
 * Format datetime string to display format: "Mar 3 2026 12:12AM"
 */
function formatDateDisplay(dateStr) {
    if (!dateStr) return '';
    // Parse as local time: replace space with 'T' to avoid UTC interpretation
    const normalizedStr = dateStr.replace(' ', 'T');
    const d = new Date(normalizedStr);
    if (isNaN(d.getTime())) return '';
    
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[d.getMonth()];
    const day = d.getDate();
    const year = d.getFullYear();
    let hours = d.getHours();
    const minutes = d.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    
    return `${month} ${day} ${year} ${hours}:${minutes.toString().padStart(2, '0')}${ampm}`;
}

/**
 * Format datetime to input value format: "YYYY-MM-DDTHH:MM"
 */
function formatDatetimeLocal(dateStr) {
    if (!dateStr) return '';
    // Parse as local time: replace space with 'T' to avoid UTC interpretation
    const normalizedStr = dateStr.replace(' ', 'T');
    const d = new Date(normalizedStr);
    if (isNaN(d.getTime())) return '';
    
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

export function openCreateModal(columnId) {
    clearTaskFieldError('title');
    const taskModal = document.getElementById('taskModal');
    document.getElementById('modalTitle').innerText = 'Create new work item';

    setVal('task_id', '');
    setVal('column_id', columnId);
    setVal('priority', '');
    setVal('start_date', '');
    setVal('due_date', '');
    setVal('title', '');
    setJson('assigned_users', []);
    setJson('label_ids', []);

    document.getElementById('create_more').checked = false;

    initSummernote('');

    taskModal.classList.replace('hidden', 'flex');

    // Unbind taskId from modal dropdowns (create mode = no live updates)
    unbindModalTaskId();

    const stateName = getDropdownItemText('state', columnId, 'modal') || 'State';
    setComponentDropdownSelection('state', columnId, stateName, 'modal');

    setComponentDropdownSelection('priority', '', 'Priority', 'modal');
    setComponentDropdownSelection('assignees', [], 'Assignees', 'modal');
    setComponentDropdownSelection('labels', [], 'Labels', 'modal');

    // Update visual displays
    updateDrawerStateDisplayComponent(columnId, 'modal');
    updateDrawerPriorityDisplayComponent('', 'modal');
    updateDrawerAssigneeDisplayComponent([], 'modal');
    updateDrawerLabelsDisplayComponent([], 'modal');

    // Update date displays
    document.getElementById('modalStartDateDisplay').textContent = 'Add start date';
    document.getElementById('modalDueDateDisplay').textContent = 'Add due date';

    // updateStartDateUI();

}

export function openEditModal(id, title, description, assignedUsers, startDate, dueDate, priority, columnId, labelIds) {
    const taskModal = document.getElementById('taskModal');
    document.getElementById('modalTitle').innerText = 'Edit work item';

    // Check TaskStore for latest data first
    const storedTask = TaskStore.get(id);
    
    // Use stored data if available, otherwise use passed parameters
    if (storedTask) {
        title = storedTask.title ?? title;
        description = storedTask.description ?? description;
        assignedUsers = storedTask.assignee_ids ?? storedTask.assigned_to ?? assignedUsers;
        startDate = storedTask.start_date ?? startDate;
        dueDate = storedTask.due_date ?? dueDate;
        priority = storedTask.priority ?? priority;
        columnId = storedTask.column_id ?? columnId;
        labelIds = storedTask.label_ids ?? labelIds;
    }

    setVal('task_id', id);
    setVal('column_id', columnId);
    setVal('priority', priority ?? '');
    setVal('start_date', startDate ?? '');
    setVal('due_date', dueDate ?? '');
    setVal('title', title ?? '');

    // IMPORTANT: ensure numeric ids stay numeric OR stay string consistently.
    // If your API expects integers, convert:
    const assignees = (assignedUsers || []).map(Number);
    const labels = (labelIds || []).map(Number);

    setJson('assigned_users', assignees);
    setJson('label_ids', labels);

    const stateName = getDropdownItemText('state', columnId, 'modal') || 'State';
    setComponentDropdownSelection('state', columnId, stateName, 'modal');

    const prioNameMap = { 'high': 'High', 'medium': 'Medium', 'low': 'Low', '': 'None', null: 'None', undefined: 'None' };
    setComponentDropdownSelection('priority', priority ?? '', prioNameMap[priority] ?? 'Priority', 'modal');

    const assigneesArr = (assignedUsers || []).map(String);
    setComponentDropdownSelection('assignees', assigneesArr, assigneesArr.length ? `${assigneesArr.length} assignees` : 'Assignees', 'modal');

    const labelsArr = (labelIds || []).map(String);
    setComponentDropdownSelection('labels', labelsArr, labelsArr.length ? `${labelsArr.length} labels` : 'Labels', 'modal');

    // updateStartDateUI();

    // Update visual displays
    updateDrawerStateDisplayComponent(columnId, 'modal');
    updateDrawerPriorityDisplayComponent(priority ?? '', 'modal');
    updateDrawerAssigneeDisplayComponent(assignees, 'modal');
    updateDrawerLabelsDisplayComponent(labels, 'modal');

    // Bind taskId to modal dropdowns for live updates
    bindModalTaskId(id);

    document.getElementById('create_more').checked = false;

    
    // Update date displays
    const startDisplay = document.getElementById('modalStartDateDisplay');
    const dueDisplay = document.getElementById('modalDueDateDisplay');
    
    if (startDate) {
        const startInput = document.getElementById('start_date');
        startInput.value = formatDatetimeLocal(startDate);
        startDisplay.textContent = formatDateDisplay(startDate);
    } else {
        startDisplay.textContent = 'Add start date';
    }
    
    if (dueDate) {
        const dueInput = document.getElementById('due_date');
        dueInput.value = formatDatetimeLocal(dueDate);
        dueDisplay.textContent = formatDateDisplay(dueDate);
    } else {
        dueDisplay.textContent = 'Add due date';
    }
    initSummernote(description || '');

    taskModal.classList.replace('hidden', 'flex');
}

export function closeTaskModal() {
    clearTaskFieldError('title');
    const taskModal = document.getElementById('taskModal');
    taskModal.classList.replace('flex', 'hidden');
    if (typeof $ !== 'undefined' && $.fn.summernote) $('#description').summernote('destroy');
    unbindModalTaskId();
}

/**
 * Bind taskId to modal dropdowns for live server updates in edit mode
 */
function bindModalTaskId(taskId) {
    console.log('Binding modal dropdowns to taskId:', taskId);
    document
        .querySelectorAll('[data-dropdown][data-context="modal"]')
        .forEach(w => {
            w.dataset.taskId = String(taskId);
        });
}

/**
 * Unbind taskId from modal dropdowns (create mode)
 */
function unbindModalTaskId() {
    console.log('Unbinding modal dropdowns from taskId');
    document
        .querySelectorAll('[data-dropdown][data-context="modal"]')
        .forEach(w => {
            delete w.dataset.taskId;
        });
}

/**
 * Update modal fields if modal is currently open for the given task
 */
export function updateModalIfOpen(task) {
    const taskModal = document.getElementById('taskModal');
    if (!taskModal || taskModal.classList.contains('hidden')) return;
    
    const currentTaskId = document.getElementById('task_id')?.value;
    if (!currentTaskId || String(currentTaskId) !== String(task.id)) return;
    
    console.log('Updating modal for task:', task.id);
    
    // Update priority
    if (task.priority !== undefined) {
        setVal('priority', task.priority ?? '');
        const prioNameMap = { 'high': 'High', 'medium': 'Medium', 'low': 'Low', '': 'None', null: 'None', undefined: 'None' };
        setComponentDropdownSelection('priority', task.priority ?? '', prioNameMap[task.priority] ?? 'Priority', 'modal');
        updateDrawerPriorityDisplayComponent(task.priority ?? '', 'modal');
    }
    
    // Update state/column
    if (task.column_id !== undefined) {
        setVal('column_id', task.column_id);
        const stateName = getDropdownItemText('state', task.column_id, 'modal') || 'State';
        setComponentDropdownSelection('state', task.column_id, stateName, 'modal');
        updateDrawerStateDisplayComponent(task.column_id, 'modal');
    }
    
    // Update assignees
    if (task.assignee_ids !== undefined) {
        const assignees = (task.assignee_ids || []).map(Number);
        setJson('assigned_users', assignees);
        const assigneesArr = assignees.map(String);
        setComponentDropdownSelection('assignees', assigneesArr, assigneesArr.length ? `${assigneesArr.length} assignees` : 'Assignees', 'modal');
        updateDrawerAssigneeDisplayComponent(assignees, 'modal');
    }
    
    // Update labels
    if (task.label_ids !== undefined) {
        const labels = (task.label_ids || []).map(Number);
        setJson('label_ids', labels);
        const labelsArr = labels.map(String);
        setComponentDropdownSelection('labels', labelsArr, labelsArr.length ? `${labelsArr.length} labels` : 'Labels', 'modal');
        updateDrawerLabelsDisplayComponent(labels, 'modal');
    }
    
    // Update dates
    if (task.start_date !== undefined) {
        const startInput = document.getElementById('start_date');
        const startDisplay = document.getElementById('modalStartDateDisplay');
        if (task.start_date) {
            if (startInput) startInput.value = formatDatetimeLocal(task.start_date);
            if (startDisplay) startDisplay.textContent = formatDateDisplay(task.start_date);
        } else {
            if (startInput) startInput.value = '';
            if (startDisplay) startDisplay.textContent = 'Add start date';
        }
    }
    
    if (task.due_date !== undefined) {
        const dueInput = document.getElementById('due_date');
        const dueDisplay = document.getElementById('modalDueDateDisplay');
        if (task.due_date) {
            if (dueInput) dueInput.value = formatDatetimeLocal(task.due_date);
            if (dueDisplay) dueDisplay.textContent = formatDateDisplay(task.due_date);
        } else {
            if (dueInput) dueInput.value = '';
            if (dueDisplay) dueDisplay.textContent = 'Add due date';
        }
    }
}

/* ── Save (create or update) ── */
export function saveTask(config) {
    const { role, boardId, workspaceId, projectId } = config;
    const id = document.getElementById('task_id')?.value;

    const payload = {
        title: document.getElementById('title')?.value || '',
        description: (typeof $ !== 'undefined' && $.fn.summernote)
            ? $('#description').summernote('code')
            : (document.getElementById('description')?.value || ''),

        column_id: document.getElementById('column_id')?.value || null,

        // ✅ arrays from hidden JSON inputs (NOT Sets)
        assigned_users: getJson('assigned_users'),
        label_ids: getJson('label_ids'),

        // ✅ empty string => null (so backend doesn’t get "null" string)
        priority: document.getElementById('priority')?.value || null,

        start_date: document.getElementById('start_date')?.value || null,
        due_date: document.getElementById('due_date')?.value || null,

        board_id: boardId,
        workspace_id: workspaceId,
        project_id: projectId,
    };

    //   console.log('Saving task with payload:', payload);
    //   return; // Remove this line to enable saving

    // Title is the one required field: say so on the field itself, before any
    // request goes out.
    clearTaskFieldError('title');
    if (!payload.title.trim()) {
        showTaskFieldError('title', 'Title is required');
        return;
    }

    // Spinner + lock while the request is in flight, so a slow save never looks
    // frozen and a second click cannot create the same task twice.
    const saveBtn = document.getElementById('taskSaveBtn');
    if (saveBtn) {
        if (saveBtn.disabled) return;
        saveBtn.dataset.label = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.classList.add('opacity-70', 'cursor-not-allowed');
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving…';
    }
    const resetSaveBtn = () => {
        if (!saveBtn) return;
        saveBtn.disabled = false;
        saveBtn.classList.remove('opacity-70', 'cursor-not-allowed');
        if (saveBtn.dataset.label) saveBtn.innerHTML = saveBtn.dataset.label;
    };

    TaskApi.save(role, { id: id || null, ...payload })
        .then(async () => {
            resetSaveBtn();
            const createMore = document.getElementById('create_more')?.checked;
            if (createMore && !id) {
                const colId = document.getElementById('column_id')?.value;
                closeTaskModal();
                setTimeout(() => openCreateModal(colId), 100);
            } else {
                closeTaskModal();
                await refreshBoardListsInPlace();

                // If we just edited a sub-work item (rendered in the currently open drawer's
                // sub-work items list), refresh that list so it reflects the change immediately.
                if (id && document.querySelector(`[data-subtask-id="${id}"]`) && window.currentDrawerTaskId && typeof window._reloadSubtasks === 'function') {
                    window._reloadSubtasks(window.currentDrawerTaskId);
                }
            }
        })
        .catch(err => {
            resetSaveBtn();
            console.error(err);

            // Server-side validation: the title's message goes under the title.
            const titleMsg = err?.errors?.title?.[0];
            if (titleMsg) {
                showTaskFieldError('title', titleMsg);
                return;
            }
            alert('Error: ' + (err?.message || err));
        });
}

/** Red outline + message under a field (title only has a slot today: #title_error). */
export function showTaskFieldError(field, message) {
    const input = document.getElementById(field);
    const box = document.getElementById(`${field}_error`);
    if (input) {
        input.classList.remove('border-gray-300');
        input.classList.add('border-red-500', 'ring-1', 'ring-red-500');
        input.setAttribute('aria-invalid', 'true');
        input.focus();
    }
    if (box) {
        box.querySelector('span').textContent = message;
        box.classList.remove('hidden');
    }
}

export function clearTaskFieldError(field) {
    const input = document.getElementById(field);
    const box = document.getElementById(`${field}_error`);
    if (input) {
        input.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
        input.classList.add('border-gray-300');
        input.removeAttribute('aria-invalid');
    }
    if (box) box.classList.add('hidden');
}

/* ── Summernote init ── */
function initSummernote(content) {
    console.log('Initializing Summernote with content length:', content.length);
    if (typeof $ === 'undefined' || !$.fn.summernote) return;

    $('#description').summernote('destroy');
    $('#description').summernote({
        height: 120,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough']],
            ['view', ['codeview']],
        ],
        placeholder: 'Click to add description',
    });
    $('#description').summernote('code', content);
}

/**
 * Update modal date display after input change
 */
export function updateModalDate(dateType) {
    console.log('Updating modal date display for:', dateType);
    const input = document.getElementById(dateType);
    const displayId = dateType === 'start_date' ? 'modalStartDateDisplay' : 'modalDueDateDisplay';
    const display = document.getElementById(displayId);
    
    if (input.value) {
        display.textContent = formatDateDisplay(input.value);
    } else {
        display.textContent = dateType === 'start_date' ? 'Add start date' : 'Add due date';
    }
    
    // Close the dropdown after selection (optional - provides smoother UX)
    // const pickerId = dateType === 'start_date' ? 'modalStartDatePicker' : 'modalDueDatePicker';
    // $(`#${pickerId}`).addClass('hidden');
}

/**
 * Set quick date for modal (Today, Tomorrow, Clear)
 */
export function setModalQuickDate(dateType, days) {
    console.log('Setting modal quick date for:', { dateType, days });
    const input = document.getElementById(dateType);
    const displayId = dateType === 'start_date' ? 'modalStartDateDisplay' : 'modalDueDateDisplay';
    const display = document.getElementById(displayId);
    
    if (days === null) {
        // Clear
        input.value = '';
        display.textContent = dateType === 'start_date' ? 'Add start date' : 'Add due date';
    } else {
        // Today or Tomorrow - use formatDatetimeLocal utility
        const date = new Date();
        date.setDate(date.getDate() + days);
        const formatted = formatDatetimeLocal(date.toISOString());
        
        input.value = formatted;
        display.textContent = formatDateDisplay(formatted);
    }
    
    // Close dropdown using jQuery for consistency
    const pickerId = dateType === 'start_date' ? 'modalStartDatePicker' : 'modalDueDatePicker';
    $(`#${pickerId}`).addClass('hidden');
}