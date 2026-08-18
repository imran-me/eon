/**
 * board-show.js — Entry point for the Board Show page.
 *
 * Imports all modules and exposes necessary functions to `window`
 * so that inline onclick handlers in Blade templates can call them.
 *
 * The Blade file provides a <script> block with:
 *   window.__boardConfig = { role, boardId, workspaceId, projectId, moveUrl, ... }
 */

// ── Modules ──
import {
    DropdownEngine, toggleDropdown, closeAllDropdowns,initDropdownListeners,
    // filterDropdown,toggleModalDropdown,
    // filterAssigneesModal, filterLabelsModal, filterStatesModal, filterPrioritiesModal,
} from '../ui/dropdown';

import {
    openCreateModal, openEditModal, closeTaskModal, saveTask,
    updateModalDate, setModalQuickDate
    // selectState, selectPriority, toggleModalAssignee, toggleModalLabel
} from '../task/task-modal';

import * as TaskDrawer from '../task/task-drawer';
import * as TaskSubtask from '../task/task-subtask';

import {
    openCreateSubtaskModal, closeCreateSubtaskModal, submitCreateSubtask,
    updateSubtaskDate, setSubtaskQuickDate,
} from '../task/task-subtask-modal';

import {
    openAddParentModal, closeAddParentModal, toggleExistingSelection,
    searchParentTasks, selectParentTask, addSelectedWorkItems,
} from '../task/task-existing-modal';

import {
    updateDrawerStateDisplayComponent,
    updateDrawerPriorityDisplayComponent,
    updateDrawerAssigneeDisplayComponent,
    updateDrawerLabelsDisplayComponent
} from '../task/task-drawer';

import {
    updateTaskField, deleteTask, copyTask, setQuickStartDate, setQuickDueDate, updateStateBadgeColor, updateStartDateUI, updateDueDateUI
    // updateTaskField, updateDrawerField, setQuickStartDate, setQuickDueDate,
    // toggleAssignee, deleteTask
} from '../task/task-card';

import { updateDropdownDisplay } from '../ui/dropdown-updater';

import {
    openAddLinkModal, closeAddLinkModal, submitAddLink,
    openEditLinkModal, deleteLink, copyLinkToClipboard
} from '../task/task-link';

import { TaskApi } from '../task/task-api';

// ── Helper to get config ──
function cfg() {
    return window.__boardConfig || {};
}

// ══════════════════════════════════════════════════════════════
// Expose every function that Blade onclick="" attributes call.
// ══════════════════════════════════════════════════════════════

/* ── Dropdown / UI ── */
window.toggleDropdown = toggleDropdown;
window.closeAllDropdowns = closeAllDropdowns;
// window.filterDropdown = filterDropdown;
// window.toggleModalDropdown = toggleModalDropdown;
// window.filterAssignees = filterAssigneesModal;
// window.filterLabels = filterLabelsModal;
// window.filterStates = filterStatesModal;
// window.filterPriorities = filterPrioritiesModal;

/* ── Task Modal ── */
window.openCreateModal = openCreateModal;
window.openEditModal = openEditModal;
window.closeTaskModal = closeTaskModal;
window.saveTask = () => saveTask(cfg());
window.updateModalDate = updateModalDate;
window.setModalQuickDate = setModalQuickDate;

/* ── Task Drawer ── */
window.openTaskDrawer = (taskId, title, desc, assignedTo, startDate, dueDate, priority, columnId, labelIds, createdBy, parentId = null, parentTitle = null) =>
    TaskDrawer.openTaskDrawer(cfg(), taskId, title, desc, assignedTo, startDate, dueDate, priority, columnId, labelIds, createdBy, parentId, parentTitle);
window.closeTaskDrawer = TaskDrawer.closeTaskDrawer;
window.showDrawerTab = TaskDrawer.showDrawerTab;
window.toggleSection = TaskDrawer.toggleSection;
window.updateDrawerDate = (taskId, dateType) => TaskDrawer.updateDrawerDate(cfg(), taskId, dateType);
window.setDrawerQuickDate = (taskId, dateType, days) => TaskDrawer.setDrawerQuickDate(cfg(), taskId, dateType, days);
window.handleAttachmentUpload = (event) => TaskDrawer.handleAttachmentUpload(cfg(), event);
window.deleteAttachment = (attId, taskId) => TaskDrawer.deleteAttachment(cfg(), attId, taskId);
window.submitComment = () => TaskDrawer.submitComment(cfg());
window.autoSaveDescription = () => TaskDrawer.autoSaveDescription(cfg());

/* ── Task Card ── */
window.updateTaskField = (taskId, field, value, clickedItem) => updateTaskField(cfg(), taskId, field, value, clickedItem);
// Variant that does not close dropdowns after update (useful for inline inputs)
window.updateTaskFieldNoClose = (taskId, field, value, clickedItem) => updateTaskField(cfg(), taskId, field, value, clickedItem, { close: false });
window.setQuickStartDate = (taskId, preset) => setQuickStartDate(cfg(), taskId, preset);
window.setQuickDueDate = (taskId, preset) => setQuickDueDate(cfg(), taskId, preset);
// window.toggleAssignee = (taskId, userId, el) => toggleAssignee(cfg(), taskId, userId, el);
window.deleteTask = (taskId) => deleteTask(cfg(), taskId);
window.copyTask = (taskId) => copyTask(cfg(), taskId);

/* ── Sub-work items ── (rendering/actions live in ../task/task-subtask; Edit and Delete
   reuse the same components as regular tasks — openEditModal above and deleteTask here) */
window._reloadSubtasks = (taskId) => TaskSubtask.loadSubtasks(cfg(), taskId);
window.openSubtaskDrawer = (subtaskId) => TaskSubtask.openSubtaskDrawer(cfg(), subtaskId);
window.editSubtaskItem = (taskId) => TaskSubtask.editSubtaskItem(cfg(), taskId);
window.copySubtaskLink = TaskSubtask.copySubtaskLink;
window.removeSubtaskFromParent = (taskId, parentId) => TaskSubtask.removeSubtaskFromParent(cfg(), taskId, parentId);
window._openParentDrawer = (parentId) => TaskSubtask.openParentDrawer(cfg(), parentId);

window.removeTaskParent = function(taskId) {
    if (!confirm('Remove parent link? The task will become a top-level task.')) return;

    TaskApi.updateField(cfg().role, taskId, { parent_id: null })
        .then(() => {
            $('#drawerTaskCode').text('TASK-' + taskId);
            $('#drawerParentValue').html(`<button onclick="window.openAddParentModal && window.openAddParentModal(${taskId})" class="text-sm text-gray-400 hover:text-blue-600">Add parent work item</button>`);
        })
        .catch(err => alert(err.message || 'Failed to remove parent link.'));
};

/* ── Create sub-work item modal ── */
window.openCreateSubtaskModal = openCreateSubtaskModal;
window.closeCreateSubtaskModal = closeCreateSubtaskModal;
window.submitCreateSubtask = () => submitCreateSubtask(cfg());
window.updateSubtaskDate = updateSubtaskDate;
window.setSubtaskQuickDate = setSubtaskQuickDate;

/* ── Add existing work item modal ── */
window.openAddParentModal = (taskId, subtaskMode = false) => openAddParentModal(cfg(), taskId, subtaskMode);
window.closeAddParentModal = closeAddParentModal;
window.toggleExistingSelection = toggleExistingSelection;
window.searchParentTasks = (q) => searchParentTasks(cfg(), q);
window.selectParentTask = (selectedId) => selectParentTask(cfg(), selectedId);
window.addSelectedWorkItems = () => addSelectedWorkItems(cfg());

/* ── Links ── */
window.openAddLinkModal = openAddLinkModal;
window.closeAddLinkModal = closeAddLinkModal;
window.submitAddLink = () => submitAddLink(cfg());
window.openEditLinkModal = openEditLinkModal;
window.deleteLink = (linkId, taskId) => deleteLink(cfg(), linkId, taskId);
window.copyLinkToClipboard = copyLinkToClipboard;

// Expose currentDrawerTaskId as a getter on window
Object.defineProperty(window, 'currentDrawerTaskId', {
    get() { return TaskDrawer.currentDrawerTaskId; },
});

// ══════════════════════════════════════════════════════════════
// Helper Functions
// ══════════════════════════════════════════════════════════════

/**
 * Update modal property display with rich visuals (icons, avatars, badges)
 * Uses drawer display functions but targets modal context
 */
function updateModalPropertyDisplay(type, value, context = 'modal') {
    if (type === 'state') {
        updateDrawerStateDisplayComponent(value, context);
    } else if (type === 'priority') {
        updateDrawerPriorityDisplayComponent(value, context);
    } else if (type === 'assignees') {
        updateDrawerAssigneeDisplayComponent(value, context);
    } else if (type === 'labels') {
        updateDrawerLabelsDisplayComponent(value, context);
    }
}

// ══════════════════════════════════════════════════════════════
// DOM-Ready Initialisation
// ══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {
    const config = cfg();

    // ── "My Tasks" view: populate the state dropdown per-task with its own board's
    // columns (boardColumnsMap), since tasks here can each belong to a different board. ──
    if (config.viewMode === 'my-tasks') {
        // Build a column dropdown item element string from a column object {id, name, position}
        const buildColItem = (col) => {
            const icons = {
                1: '<span class="inline-block w-3 h-3 rounded-full border-2 border-gray-400"></span>',
                2: '<span class="inline-block w-3 h-3 rounded-full border-2 border-blue-500"></span>',
                3: '<span class="w-3 h-3 rounded-full border-2 border-amber-500 flex items-center justify-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span></span>',
                4: '<svg class="w-3 h-3 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>',
                5: '<svg class="w-3 h-3 text-red-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>',
            };
            const icon = icons[col.position] || icons[1];
            const check = '<svg data-role="dropdown-check" class="w-3 h-3 ml-auto text-gray-400 hidden" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
            return `<div data-role="dropdown-item" data-value="${col.id}" data-name="${col.name}" class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-xs">${icon}<span class="text-gray-700">${col.name}</span>${check}</div>`;
        };

        // Populate the state dropdown for the given context (drawer|modal) with the board's columns
        const populateStateDropdown = (context, boardId) => {
            const cols = (config.boardColumnsMap || {})[boardId] || [];
            const wrapper = document.querySelector(`[data-dropdown="state"][data-context="${context}"]`);
            if (!wrapper) return;
            const items = wrapper.querySelector('[data-role="dropdown-items"]');
            if (items) items.innerHTML = cols.map(buildColItem).join('');
        };

        // Intercept openTaskDrawer — populate drawer state dropdown with this task's board columns
        const _origDrawer = window.openTaskDrawer;
        window.openTaskDrawer = function (taskId, title, desc, assignedTo, startDate, dueDate, priority, columnId, labelIds, createdBy, boardId) {
            if (boardId) populateStateDropdown('drawer', boardId);
            _origDrawer(taskId, title, desc, assignedTo, startDate, dueDate, priority, columnId, labelIds, createdBy);
        };

        // Intercept openEditModal — populate modal state dropdown with this task's board columns
        const _origEditModal = window.openEditModal;
        window.openEditModal = function (taskId, title, desc, assignedUsers, startDate, dueDate, priority, columnId, labelIds, boardId) {
            if (boardId) populateStateDropdown('modal', boardId);
            _origEditModal(taskId, title, desc, assignedUsers, startDate, dueDate, priority, columnId, labelIds);
        };
    }

    function syncColumnCount(taskListEl) {
        if (!taskListEl) return;
        const columnCard = taskListEl.closest('[data-column-id]');
        if (!columnCard) return;

        const countBadge = columnCard.querySelector('.flex.justify-between.items-center .flex.items-center.gap-2 span');
        if (!countBadge) return;

        countBadge.textContent = String(taskListEl.querySelectorAll('.task-item').length);
    }

    function syncTaskStateAfterMove(taskId, newColumnId, fallbackLabelText = null) {
        const wrapper = document.querySelector(`[data-dropdown="state"][data-context="card"][data-task-id="${taskId}"]`);
        if (!wrapper) return;

        const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');
        if (hidden) hidden.value = String(newColumnId);

        let selectedText = null;
        wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach((item) => {
            const isSelected = String(item.dataset.value || '') === String(newColumnId);
            item.classList.toggle('bg-gray-100', isSelected);
            item.classList.toggle('bg-blue-50', false);

            const check = item.querySelector('[data-role="dropdown-check"]');
            if (check) check.classList.toggle('hidden', !isSelected);

            if (isSelected) {
                selectedText = item.querySelector('span:last-child')?.textContent?.trim() || null;
            }
        });

        // Keep existing icon in toggle and update only the label text to avoid duplicate icons.
        const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
        if (labelEl) {
            labelEl.textContent = selectedText || fallbackLabelText || labelEl.textContent;
        }

        // Update the badge background/text color to match the new column
        updateStateBadgeColor(taskId, newColumnId);
    }

    // ── Initialize DropdownEngine for <x-ui.dropdown> components ──
    const engine = new DropdownEngine();

    // Listen for component dropdown changes and delegate to updateTaskField
    document.addEventListener('dropdown:change', (e) => {
        
        const { type, taskId, value, wrapper } = e.detail;
        const context = wrapper?.dataset?.context;
        
        // ✅ Card/Drawer/Modal-Edit dropdowns (taskId exists) -> update server and sync UI
        if (taskId) {
            const fieldMap = {
                state: 'column_id',
                priority: 'priority',
                assignees: 'assigned_users',
                labels: 'label_ids',
            };

            const field = fieldMap[type];
            if (field) {
                // updateTaskField now handles syncing to drawer and modal automatically
                updateTaskField(cfg(), taskId, field, value, null);
            }
            return;
        }

        // Modal create mode (no taskId) - just update form fields
        if (context === 'modal') {
            // Update dropdown checkmarks
            updateDropdownDisplay(type, value, 'modal');

            // Update rich visual displays for modal
            updateModalPropertyDisplay(type, value);

            // Update hidden form fields
            if (type === 'state') document.getElementById('column_id').value = value ?? '';
            if (type === 'priority') document.getElementById('priority').value = value ?? '';
            if (type === 'assignees') document.getElementById('assigned_users').value = JSON.stringify(value ?? []);
            if (type === 'labels') document.getElementById('label_ids').value = JSON.stringify(value ?? []);
            return;
        }

        // Create sub-work item modal (no taskId) - same pattern as "modal", targeting the subtask fields
        if (context === 'subtask') {
            updateDropdownDisplay(type, value, 'subtask');
            updateModalPropertyDisplay(type, value, 'subtask');

            if (type === 'state') document.getElementById('createSubtaskColumnId').value = value ?? '';
            if (type === 'priority') document.getElementById('createSubtaskPriority').value = value ?? '';
            if (type === 'assignees') document.getElementById('createSubtaskAssignedUsers').value = JSON.stringify(value ?? []);
            if (type === 'labels') document.getElementById('createSubtaskLabelIds').value = JSON.stringify(value ?? []);
        }
    });

    // ── Dropdown click-outside listeners (legacy) ──
    initDropdownListeners();

    // ── Drag-and-drop (SortableJS) ──
    if (typeof Sortable !== 'undefined') {
        document.querySelectorAll('.task-list').forEach(el => {
            new Sortable(el, {
                group: 'tasks',
                animation: 200,
                async onEnd(evt) {
                    if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;

                    const taskId = evt.item.dataset.id;
                    const newColumnId = evt.to.dataset.column;
                    const destinationColumnTitle = evt.to
                        ?.closest('[data-column-id]')
                        ?.querySelector('h3')
                        ?.textContent
                        ?.trim() || null;

                    try {
                        const moveRes = await TaskApi.move(config.moveUrl, taskId, newColumnId, evt.newIndex);
                        if (!moveRes.ok) {
                            throw new Error('Move failed');
                        }

                        const moveData = await moveRes.json().catch(() => ({}));
                        if (moveData?.task?.start_date) {
                            updateStartDateUI(taskId, moveData.task.start_date);
                        }

                        if (moveData?.task?.due_date) {
                            updateDueDateUI(taskId, moveData.task.due_date);
                        }

                        // Keep task card state dropdown in sync with its new column.
                        syncTaskStateAfterMove(taskId, newColumnId, destinationColumnTitle);

                        // Update source and target column counters immediately.
                        syncColumnCount(evt.from);
                        syncColumnCount(evt.to);
                    } catch (error) {
                        console.error(error);
                        // Fallback to full refresh so UI and server state cannot drift.
                        window.location.reload();
                    }
                },
            });
        });
    }

    // ── Close date pickers on drawer scroll ──
    const drawerContent = document.querySelector('#taskDrawer .overflow-y-auto');
    if (drawerContent) {
        drawerContent.addEventListener('scroll', () => {
            $('#drawerStartDatePicker, #drawerDueDatePicker').addClass('hidden');
        });
    }

    // ── Close component dropdowns on column scroll ──
    document.querySelectorAll('.task-list').forEach(taskList => {
        taskList.addEventListener('scroll', () => {
            closeAllDropdowns();
        });
    });

    // ── Close dropdowns on resize ──
    window.addEventListener('resize', () => closeAllDropdowns());

    // ── Summernote for drawer description ──
    if (typeof $ !== 'undefined' && $.fn.summernote) {
        $('#drawerTaskDescription').summernote({
            height: 200,
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['font', ['strikethrough']],
                ['view', ['codeview']],
            ],
            callbacks: {
                onChange() { TaskDrawer.autoSaveDescription(cfg()); },
            },
        });
    }

    // ── Deep-link: open a task (and tab) from a notification link, e.g. ?task=123&tab=activity ──
    const deepLinkParams = new URLSearchParams(window.location.search);
    const deepLinkTaskId = deepLinkParams.get('task');
    if (deepLinkTaskId) {
        const titleEl = document.querySelector(`[data-id="${deepLinkTaskId}"] .cursor-pointer`);
        if (titleEl) {
            titleEl.click();
            const deepLinkTab = deepLinkParams.get('tab');
            if (deepLinkTab) {
                TaskDrawer.showDrawerTab(deepLinkTab);
            }
        }
    }
});
