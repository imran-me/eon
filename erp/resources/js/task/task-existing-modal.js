/**
 * Task Existing Modal — "Add existing work item" picker.
 * Used both to set a single parent for the current task (1:1, click-to-select)
 * and to link multiple existing tasks as sub-work items (multi-select + bulk add).
 */
import { TaskApi } from './task-api';

let _addParentIsSubtaskMode = false;
let _searchParentTimer = null;
let _selectedExistingIds = new Set();

export function openAddParentModal(config, taskId, subtaskMode = false) {
    _addParentIsSubtaskMode = subtaskMode;
    _selectedExistingIds = new Set();
    document.getElementById('addParentTaskId').value = taskId;
    document.getElementById('addParentMode').value = subtaskMode ? 'subtask' : 'parent';
    document.getElementById('addParentModalTitle').textContent = subtaskMode ? 'Add existing work item as sub-task' : 'Set parent work item';
    document.getElementById('addParentSearchInput').value = '';
    document.getElementById('addParentWorkspaceLevel').checked = false;
    document.getElementById('addSelectedWorkItemsBtn').classList.toggle('hidden', !subtaskMode);
    updateSelectedExistingCount();
    document.getElementById('addParentBackdrop').classList.remove('hidden');
    document.getElementById('addParentModal').classList.remove('hidden');
    document.getElementById('addParentModal').classList.add('flex');
    document.getElementById('addParentSearchInput').focus();
    searchParentTasks(config, '');
}

export function closeAddParentModal() {
    document.getElementById('addParentBackdrop').classList.add('hidden');
    document.getElementById('addParentModal').classList.add('hidden');
    document.getElementById('addParentModal').classList.remove('flex');
}

function updateSelectedExistingCount() {
    const count = _selectedExistingIds.size;
    document.getElementById('addParentSelectedCount').textContent = count === 0
        ? 'No work items selected'
        : `${count} work item${count > 1 ? 's' : ''} selected`;
    const btn = document.getElementById('addSelectedWorkItemsBtn');
    btn.disabled = count === 0;
}

export function toggleExistingSelection(id, checkboxEl) {
    if (checkboxEl.checked) _selectedExistingIds.add(id);
    else _selectedExistingIds.delete(id);
    updateSelectedExistingCount();
}

export function searchParentTasks(config, q) {
    clearTimeout(_searchParentTimer);
    _searchParentTimer = setTimeout(() => {
        const currentId = document.getElementById('addParentTaskId').value;
        const workspaceLevel = document.getElementById('addParentWorkspaceLevel').checked;

        TaskApi.searchTasks(config.role, {
            q: q || '',
            exclude_id: currentId,
            board_id: config.boardId || '',
            workspace_id: config.workspaceId || '',
            workspace_level: workspaceLevel ? '1' : '0',
        })
        .then(data => {
            const results = document.getElementById('addParentSearchResults');
            if (!data.tasks || data.tasks.length === 0) {
                results.innerHTML = '<p class="px-3 py-4 text-sm text-gray-400 text-center">No tasks found.</p>';
                return;
            }

            if (_addParentIsSubtaskMode) {
                results.innerHTML = data.tasks.map(t => `
                    <label class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" class="rounded" ${_selectedExistingIds.has(t.id) ? 'checked' : ''}
                               onchange="window.toggleExistingSelection(${t.id}, this)">
                        <span class="text-xs font-mono text-gray-400 shrink-0">TASK-${t.id}</span>
                        <span class="text-sm text-gray-800 truncate">${t.title}</span>
                    </label>
                `).join('');
            } else {
                results.innerHTML = data.tasks.map(t => `
                    <button onclick="window.selectParentTask(${t.id})"
                            class="w-full text-left flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 cursor-pointer">
                        <span class="text-xs font-mono text-gray-400 shrink-0">TASK-${t.id}</span>
                        <span class="text-sm text-gray-800 truncate">${t.title}</span>
                    </button>
                `).join('');
            }
        })
        .catch(() => {
            document.getElementById('addParentSearchResults').innerHTML = '<p class="px-3 py-4 text-sm text-red-400 text-center">Search failed.</p>';
        });
    }, 300);
}

export function selectParentTask(config, selectedId) {
    const taskId = document.getElementById('addParentTaskId').value;

    // Setting a single parent for the current task (1:1 relationship)
    TaskApi.setParent(config.role, taskId, selectedId)
        .then(data => {
            if (!data.success) { alert(data.message || 'Failed to set relationship.'); return; }
            closeAddParentModal();
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'success', title: 'Saved', timer: 1200, showConfirmButton: false });
            }
        })
        .catch(() => alert('Error saving relationship.'));
}

export function addSelectedWorkItems(config) {
    if (_selectedExistingIds.size === 0) return;
    const parentId = document.getElementById('addParentTaskId').value;

    Promise.all(Array.from(_selectedExistingIds).map(childId =>
        TaskApi.setParent(config.role, childId, parentId)
    ))
    .then(results => {
        const failed = results.filter(r => !r.success);
        closeAddParentModal();
        if (typeof window._reloadSubtasks === 'function') window._reloadSubtasks(parentId);

        if (failed.length && typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'warning', title: 'Some items could not be linked', text: failed[0].message || '' });
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: 'Sub-work items added', timer: 1500, showConfirmButton: false });
        }
    })
    .catch(() => alert('Error adding selected work items.'));
}
