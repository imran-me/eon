/**
 * Task Subtask Modal — The "Create sub-work item" modal (mirrors the main
 * Create/Edit work item modal's fields: description, state, priority, assignees,
 * labels, dates), scoped under a parent task via TaskApi.createSubtask.
 */
import { TaskApi } from './task-api';
import { setComponentDropdownSelection } from '../ui/dropdown-selection';
import {
    updateDrawerStateDisplayComponent,
    updateDrawerPriorityDisplayComponent,
    updateDrawerAssigneeDisplayComponent,
    updateDrawerLabelsDisplayComponent,
} from './task-drawer';

export function openCreateSubtaskModal(parentTaskId) {
    document.getElementById('createSubtaskParentId').value = parentTaskId;
    document.getElementById('createSubtaskTitle').value = '';
    document.getElementById('createSubtaskColumnId').value = '';
    document.getElementById('createSubtaskPriority').value = '';
    document.getElementById('createSubtaskAssignedUsers').value = '[]';
    document.getElementById('createSubtaskLabelIds').value = '[]';
    document.getElementById('createSubtaskStartDate').value = '';
    document.getElementById('createSubtaskDueDate').value = '';
    document.getElementById('createSubtaskStartDateDisplay').textContent = 'Add start date';
    document.getElementById('createSubtaskDueDateDisplay').textContent = 'Add due date';
    document.getElementById('createSubtaskCreateMore').checked = false;

    setComponentDropdownSelection('state', '', 'State', 'subtask');
    setComponentDropdownSelection('priority', '', 'Priority', 'subtask');
    setComponentDropdownSelection('assignees', [], 'Assignees', 'subtask');
    setComponentDropdownSelection('labels', [], 'Labels', 'subtask');
    updateDrawerStateDisplayComponent('', 'subtask');
    updateDrawerPriorityDisplayComponent('', 'subtask');
    updateDrawerAssigneeDisplayComponent([], 'subtask');
    updateDrawerLabelsDisplayComponent([], 'subtask');

    initSubtaskSummernote('');

    document.getElementById('createSubtaskBackdrop').classList.remove('hidden');
    document.getElementById('createSubtaskModal').classList.remove('hidden');
    document.getElementById('createSubtaskModal').classList.add('flex');
    document.getElementById('createSubtaskTitle').focus();
}

export function closeCreateSubtaskModal() {
    document.getElementById('createSubtaskBackdrop').classList.add('hidden');
    document.getElementById('createSubtaskModal').classList.add('hidden');
    document.getElementById('createSubtaskModal').classList.remove('flex');
    if (typeof $ !== 'undefined' && $.fn.summernote) $('#createSubtaskDescription').summernote('destroy');
}

function initSubtaskSummernote(content) {
    if (typeof $ === 'undefined' || !$.fn.summernote) return;
    $('#createSubtaskDescription').summernote('destroy');
    $('#createSubtaskDescription').summernote({
        height: 120,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough']],
            ['view', ['codeview']],
        ],
        placeholder: 'Click to add description',
    });
    $('#createSubtaskDescription').summernote('code', content || '');
}

function formatSubtaskDateDisplay(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    let hours = d.getHours();
    const minutes = d.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    return `${months[d.getMonth()]} ${d.getDate()} ${d.getFullYear()} ${hours}:${String(minutes).padStart(2, '0')}${ampm}`;
}

export function updateSubtaskDate(dateType) {
    const inputId = dateType === 'start_date' ? 'createSubtaskStartDate' : 'createSubtaskDueDate';
    const displayId = dateType === 'start_date' ? 'createSubtaskStartDateDisplay' : 'createSubtaskDueDateDisplay';
    const input = document.getElementById(inputId);
    const display = document.getElementById(displayId);
    display.textContent = input.value ? formatSubtaskDateDisplay(input.value) : (dateType === 'start_date' ? 'Add start date' : 'Add due date');
}

export function setSubtaskQuickDate(dateType, days) {
    const inputId = dateType === 'start_date' ? 'createSubtaskStartDate' : 'createSubtaskDueDate';
    const displayId = dateType === 'start_date' ? 'createSubtaskStartDateDisplay' : 'createSubtaskDueDateDisplay';
    const input = document.getElementById(inputId);
    const display = document.getElementById(displayId);

    if (days === null) {
        input.value = '';
        display.textContent = dateType === 'start_date' ? 'Add start date' : 'Add due date';
    } else {
        const date = new Date();
        date.setDate(date.getDate() + days);
        const pad = n => String(n).padStart(2, '0');
        const formatted = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
        input.value = formatted;
        display.textContent = formatSubtaskDateDisplay(formatted);
    }
    const pickerId = dateType === 'start_date' ? 'createSubtaskStartDatePicker' : 'createSubtaskDueDatePicker';
    document.getElementById(pickerId).classList.add('hidden');
}

export function submitCreateSubtask(config) {
    const parentId = document.getElementById('createSubtaskParentId').value;
    const title    = document.getElementById('createSubtaskTitle').value.trim();
    if (!title) { document.getElementById('createSubtaskTitle').focus(); return; }

    const description = (typeof $ !== 'undefined' && $.fn.summernote)
        ? $('#createSubtaskDescription').summernote('code')
        : '';

    const columnId  = document.getElementById('createSubtaskColumnId').value || null;
    const priority  = document.getElementById('createSubtaskPriority').value || null;
    const startDate = document.getElementById('createSubtaskStartDate').value || null;
    const dueDate   = document.getElementById('createSubtaskDueDate').value || null;

    let assignedUsers = [];
    let labelIds = [];
    try { assignedUsers = JSON.parse(document.getElementById('createSubtaskAssignedUsers').value || '[]'); } catch (e) {}
    try { labelIds = JSON.parse(document.getElementById('createSubtaskLabelIds').value || '[]'); } catch (e) {}

    TaskApi.createSubtask(config.role, parentId, {
        title, description,
        priority, column_id: columnId,
        assigned_users: assignedUsers, label_ids: labelIds,
        start_date: startDate, due_date: dueDate,
    })
    .then(data => {
        if (!data.ok || !data.success) {
            const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
            alert(firstError || data.message || 'Failed to create sub-task.');
            return;
        }

        // Close (and reopen if "Create more") BEFORE any follow-up work, so a failure
        // in the reload/toast below can never leave the modal stuck open.
        const createMore = document.getElementById('createSubtaskCreateMore')?.checked;
        closeCreateSubtaskModal();
        if (createMore) {
            setTimeout(() => openCreateSubtaskModal(parentId), 100);
        }

        // Reload subtasks section in the drawer
        if (typeof window._reloadSubtasks === 'function') window._reloadSubtasks(parentId);

        // Show success toast if available
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: 'Sub-task created', timer: 1500, showConfirmButton: false });
        }
    })
    .catch(err => {
        console.error('Error creating sub-task:', err);
        alert('Error creating sub-task.');
    });
}
