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
    updateModalDate, setModalQuickDate, clearTaskFieldError
    // selectState, selectPriority, toggleModalAssignee, toggleModalLabel
} from '../task/task-modal';
import '../task/board-lazy';

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
window.clearTaskFieldError = clearTaskFieldError;

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
/* Board card: expand/collapse the sub-work items under a parent card. Sub-items
   are not cards of their own any more (TaskController@index), so this chip is
   the only way onto them from the board. */
window.toggleCardSubtasks = (taskId) => TaskSubtask.toggleCardSubtasks(cfg(), taskId);
window._reloadCardSubtasks = (taskId) => TaskSubtask.loadCardSubtasks(cfg(), taskId);
/* Bring a card's chip + panel in line after a sub-item is created from the board. */
window._refreshCardSubtasks = (taskId) => TaskSubtask.refreshCardSubtasks(cfg(), taskId);
window.openSubtaskDrawer = (subtaskId) => TaskSubtask.openSubtaskDrawer(cfg(), subtaskId);
window.editSubtaskItem = (taskId) => TaskSubtask.editSubtaskItem(cfg(), taskId);
window.copySubtaskLink = TaskSubtask.copySubtaskLink;
window.removeSubtaskFromParent = (taskId, parentId) => TaskSubtask.removeSubtaskFromParent(cfg(), taskId, parentId);
/* Start / Pause / Done on one sub-work item — action is 'start' | 'pause' | 'done' */
window.subtaskTimer = (subtaskId, action) => TaskSubtask.subtaskTimer(cfg(), subtaskId, action);
// The task's OWN clock, for a task with no sub-work items — same three
// endpoints, different repaint (there is no row in a list to patch).
window.ownTaskTimer = (taskId, action) => TaskSubtask.ownTaskTimer(cfg(), taskId, action);
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

        countBadge.textContent = String(window.kbcCount ? window.kbcCount(taskListEl) : taskListEl.querySelectorAll('.task-item').length);
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

    /* ── Moving a card ────────────────────────────────────────────────────
       One function performs every move, whatever gesture asked for it: the
       drag, the keyboard, a flick, a double-click, the right-click menu, undo.
       Lifted verbatim out of Sortable's onEnd when those gestures were added —
       six ways to move a card must not become six ways to post it, or the
       column counter and the state dropdown will agree with one of them and
       not the others.

       `fromEl`/`toEl` are the .task-list elements; the card is expected to be
       sitting in toEl already (Sortable puts it there, the other gestures move
       it before calling). */
    async function performMove(itemEl, fromEl, toEl, newIndex) {
        const taskId = itemEl.dataset.id;
        const newColumnId = toEl.dataset.column;
        const destinationColumnTitle = toEl
            ?.closest('[data-column-id]')
            ?.querySelector('h3')
            ?.textContent
            ?.trim() || null;

        try {
            const moveRes = await TaskApi.move(config.moveUrl, taskId, newColumnId, newIndex);
            if (!moveRes.ok) {
                throw new Error('Move failed');
            }

            const moveData = await moveRes.json().catch(() => ({}));

            // Repaint from the server's answer whatever it says — including null.
            // These used to be guarded by `if (start_date)`, which was fine while a
            // move could only ever ADD a date. Moving a card back to Note / To Do
            // now CLEARS it, and a truthy guard skips exactly that case: the server
            // wiped the date and the card went on displaying it until a refresh.
            if (moveData?.task) {
                updateStartDateUI(taskId, moveData.task.start_date ?? null);
                updateDueDateUI(taskId, moveData.task.due_date ?? null);
            }

            /* A task with nothing under it starts counting the moment it lands
               in In Progress, and stops when it reaches Done — both decided
               server side. The chip swaps between the sub-work ring and a live
               counter from this one answer, so the clock is visible the instant
               the card is dropped rather than after the next refresh. */
            TaskSubtask.paintCardTimeFor(taskId, {
                timer: moveData?.timer,
                tracked: moveData?.tracked,
                total: moveData?.tracked?.total,
            });

            // Keep task card state dropdown in sync with its new column.
            syncTaskStateAfterMove(taskId, newColumnId, destinationColumnTitle);

            // Update source and target column counters immediately.
            syncColumnCount(fromEl);
            syncColumnCount(toEl);

            return true;
        } catch (error) {
            console.error(error);
            // Fallback to full refresh so UI and server state cannot drift.
            window.location.reload();
            return false;
        }
    }

    // ── Drag-and-drop (SortableJS) ──
    if (typeof Sortable !== 'undefined') {
        document.querySelectorAll('.task-list').forEach(el => {
            new Sortable(el, {
                group: 'tasks',
                animation: 200,
                onStart() {
                    BoardMotion.dragging = true;
                    BoardMotion.trail.length = 0;
                },
                async onEnd(evt) {
                    BoardMotion.dragging = false;

                    // A flick carries the card past where the finger let go —
                    // see BoardMotion.throwTarget().
                    const thrown = BoardMotion.throwTarget();

                    if (thrown && thrown !== evt.to) {
                        BoardMotion.remember(evt.item, evt.from, evt.oldIndex);
                        thrown.appendChild(evt.item);
                        BoardMotion.flash(evt.item);
                        await performMove(evt.item, evt.from, thrown, thrown.children.length - 1);
                        BoardMotion.toast('Thrown to ' + BoardMotion.titleOf(thrown));
                        return;
                    }

                    if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;

                    BoardMotion.remember(evt.item, evt.from, evt.oldIndex);
                    await performMove(evt.item, evt.from, evt.to, evt.newIndex);
                },
            });
        });
    }

    /* ── BoardMotion: every other way to move a card ──────────────────────
       Purely additive. It adds gestures and shortcuts on top of the drag that
       was already here; it changes no existing behaviour, no markup and no
       styling of the cards themselves. Everything it does ends at
       performMove(), the same call the drag makes.

         Flick        drag fast sideways and let go — the card carries on to
                      the far column in that direction, like throwing it
         Keyboard     focus a card, then ← → to change column, ↑ ↓ to reorder,
                      Home/End to send it to the first/last column
         Double-click advance one column to the right
         Right-click  a small menu of every column, click to send it there
         Undo         Ctrl+Z, or the Undo button on the toast

       ── COST ON A BIG BOARD ───────────────────────────────────────────────
       Some boards here carry over 1,300 cards, so nothing below may walk the
       card list. It never does: every handler starts from the event's own
       target via closest(), the column lookup touches only the handful of
       .task-list elements, and the pointer trail records at most 12 samples
       and only while a drag is actually running. No listener is attached
       per-card — four on `document`, total, however many cards are on screen. */
    const BoardMotion = {
        dragging: false,
        trail: [],          // recent pointer samples, for measuring a flick
        history: [],        // undo stack: where each card came from
        focused: null,
        toastEl: null,

        lists() {
            return Array.from(document.querySelectorAll('.task-list'));
        },

        titleOf(listEl) {
            return listEl?.closest('[data-column-id]')?.querySelector('h3')?.textContent?.trim() || 'column';
        },

        /* A flick is a fast, mostly-horizontal pointer movement in the last
           ~120ms before release. Slow, deliberate dragging is left alone — the
           whole point is that placing a card precisely must still work. */
        throwTarget() {
            const now = performance.now();
            const recent = this.trail.filter(p => now - p.t < 120);
            if (recent.length < 2) return null;

            const first = recent[0];
            const last = recent[recent.length - 1];
            const dt = last.t - first.t;
            if (dt <= 0) return null;

            const vx = (last.x - first.x) / dt;          // px per ms
            const vy = Math.abs(last.y - first.y) / dt;

            // Horizontal intent, and genuinely fast. 0.9px/ms is roughly a
            // deliberate flick; ordinary dragging sits well under it.
            if (Math.abs(vx) < 0.9 || Math.abs(vx) < vy * 1.5) return null;

            const lists = this.lists();
            if (!lists.length) return null;

            return vx > 0 ? lists[lists.length - 1] : lists[0];
        },

        remember(itemEl, fromList, oldIndex) {
            this.history.push({ id: itemEl.dataset.id, list: fromList, index: oldIndex });
            if (this.history.length > 25) this.history.shift();
        },

        /* A card that moved on its own — thrown, keyed, undone — needs to say
           so, because the eye did not follow it there the way it follows a
           drag. A brief ring, then back to normal; no layout, no lasting style. */
        flash(itemEl) {
            itemEl.style.transition = 'box-shadow .18s ease, transform .18s ease';
            itemEl.style.boxShadow = '0 0 0 3px rgba(37,99,235,.55)';
            itemEl.style.transform = 'scale(1.02)';
            itemEl.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
            setTimeout(() => {
                itemEl.style.boxShadow = '';
                itemEl.style.transform = '';
            }, 420);
        },

        toast(message) {
            if (!this.toastEl) {
                const t = document.createElement('div');
                t.id = 'boardMotionToast';
                t.style.cssText = [
                    'position:fixed', 'left:50%', 'bottom:26px', 'transform:translateX(-50%)',
                    'background:#0f172a', 'color:#fff', 'font:600 12px system-ui,-apple-system,"Segoe UI",sans-serif',
                    'padding:9px 14px', 'border-radius:9px', 'box-shadow:0 8px 26px rgba(2,6,23,.32)',
                    'display:flex', 'align-items:center', 'gap:12px', 'z-index:2147483000',
                    'opacity:0', 'transition:opacity .16s ease', 'pointer-events:auto',
                ].join(';');
                document.body.appendChild(t);
                this.toastEl = t;
            }

            const t = this.toastEl;
            t.innerHTML = '';

            const label = document.createElement('span');
            label.textContent = message;
            t.appendChild(label);

            if (this.history.length) {
                const undo = document.createElement('button');
                undo.type = 'button';
                undo.textContent = 'Undo';
                undo.style.cssText = 'background:none;border:0;color:#93c5fd;font:inherit;cursor:pointer;padding:0;';
                undo.addEventListener('click', () => this.undo());
                t.appendChild(undo);

                const hint = document.createElement('span');
                hint.textContent = 'Ctrl+Z';
                hint.style.cssText = 'color:#64748b;font-weight:500;';
                t.appendChild(hint);
            }

            t.style.opacity = '1';
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { t.style.opacity = '0'; }, 4200);
        },

        async undo() {
            const last = this.history.pop();
            if (!last) return;

            const card = document.querySelector('.task-item[data-id="' + last.id + '"]');
            if (!card || !last.list || !document.body.contains(last.list)) return;

            const from = card.closest('.task-list');
            const at = last.list.children[last.index] || null;
            last.list.insertBefore(card, at);

            this.flash(card);
            await performMove(card, from, last.list, last.index);
            this.toast('Move undone');
        },

        /* Move a card to another list. `delta` is in columns; `to` is explicit.
           Index is kept where it makes sense: sending a card sideways drops it
           at the top of the new column, which is where a card you just decided
           about belongs. */
        async send(card, { delta = 0, to = null, index = 0 } = {}) {
            if (!card) return;

            const from = card.closest('.task-list');
            if (!from) return;

            const lists = this.lists();
            let target = to;

            if (!target) {
                const i = lists.indexOf(from);
                const next = i + delta;
                if (i < 0 || next < 0 || next >= lists.length) return;
                target = lists[next];
            }

            if (target === from) return;

            const oldIndex = Array.prototype.indexOf.call(from.children, card);
            this.remember(card, from, oldIndex);

            const at = target.children[index] || null;
            target.insertBefore(card, at);

            this.flash(card);
            this.focus(card);
            await performMove(card, from, target, index);
            this.toast('Moved to ' + this.titleOf(target));
        },

        /* Reorder within the same column. No column change, so the state
           dropdown cannot need syncing — but it still goes through
           performMove so the server holds the same order the screen shows. */
        async nudge(card, dir) {
            const list = card.closest('.task-list');
            if (!list) return;

            const kids = Array.prototype.slice.call(list.children);
            const i = kids.indexOf(card);
            const next = i + dir;
            if (i < 0 || next < 0 || next >= kids.length) return;

            this.remember(card, list, i);
            list.insertBefore(card, dir > 0 ? kids[next].nextSibling : kids[next]);
            this.flash(card);
            await performMove(card, list, list, next);
        },

        /* Remembers which card the keyboard acts on, and draws nothing.
           The card originally took a blue outline to show it was selected; the
           owner did not want a persistent active state on the board, so the
           selection is now invisible and the card's own hover styling is the
           only thing that reacts. Tracking still happens — the arrow keys need
           to know which card they are moving. */
        focus(card) {
            this.focused = card;
            // Deliberately no card.focus() and no tabindex either: the keyboard
            // handler reads this.focused rather than document.activeElement, so
            // real DOM focus buys nothing here and would let the browser paint
            // its own ring — the very thing being removed.
        },

        /* Right-click menu: every column, with the current one marked. Built
           fresh each time so a renamed or added column is always right. */
        menu(card, x, y) {
            this.closeMenu();

            const here = card.closest('.task-list');
            const box = document.createElement('div');
            box.id = 'boardMotionMenu';
            box.style.cssText = [
                'position:fixed', 'z-index:2147483000', 'min-width:190px',
                'max-height:70vh', 'overflow-y:auto',
                'background:#fff', 'border:1px solid #e2e8f0', 'border-radius:10px',
                'box-shadow:0 12px 34px rgba(2,6,23,.20)', 'padding:5px',
                'font:600 12px system-ui,-apple-system,"Segoe UI",sans-serif',
            ].join(';');

            const head = document.createElement('div');
            head.textContent = 'Move to';
            head.style.cssText = 'padding:6px 9px 5px;color:#94a3b8;font-size:10px;text-transform:uppercase;letter-spacing:.07em;';
            box.appendChild(head);

            this.lists().forEach(list => {
                const isHere = list === here;
                const row = document.createElement('button');
                row.type = 'button';
                row.textContent = this.titleOf(list) + (isHere ? '  · here' : '');
                row.disabled = isHere;
                row.style.cssText = [
                    'display:block', 'width:100%', 'text-align:left', 'border:0',
                    'background:none', 'padding:7px 9px', 'border-radius:6px',
                    'font:inherit', 'color:' + (isHere ? '#94a3b8' : '#0f172a'),
                    'cursor:' + (isHere ? 'default' : 'pointer'),
                ].join(';');
                if (!isHere) {
                    row.addEventListener('mouseenter', () => { row.style.background = '#eff6ff'; });
                    row.addEventListener('mouseleave', () => { row.style.background = 'none'; });
                    row.addEventListener('click', () => {
                        this.closeMenu();
                        this.send(card, { to: list });
                    });
                }
                box.appendChild(row);
            });

            document.body.appendChild(box);

            // Kept inside the window rather than running off the edge.
            const r = box.getBoundingClientRect();
            box.style.left = Math.min(x, window.innerWidth - r.width - 8) + 'px';
            box.style.top = Math.min(y, window.innerHeight - r.height - 8) + 'px';

            this.menuEl = box;
        },

        closeMenu() {
            if (this.menuEl) { this.menuEl.remove(); this.menuEl = null; }
        },
    };

    // Pointer trail — the raw material for detecting a flick. Passive, and it
    // only records while Sortable says a drag is running, so it costs nothing
    // when the pointer is simply moving across a board of 1,300 cards.
    document.addEventListener('pointermove', (e) => {
        if (!BoardMotion.dragging) return;
        BoardMotion.trail.push({ x: e.clientX, y: e.clientY, t: performance.now() });
        if (BoardMotion.trail.length > 12) BoardMotion.trail.shift();
    }, { passive: true });

    /* The on-card < > arrows: grey out whichever has nowhere to go. Computed on
       hover rather than at render time, because a card's column changes as it
       moves and server-rendered arrows would go stale after the first move.

       Guarded so the work happens once per card entered, not on every mousemove
       within it — on a board of 1,300 cards that difference matters. The guard
       keys on the card AND the column it is currently in, not the card alone:
       keyed on the card alone it never recomputed after a move, so a card sent
       to the last column kept offering a live "next" arrow and a dead "previous"
       one for as long as it stayed on screen. */
    let _lastMoveHover = null;
    let _lastMoveList = null;
    document.addEventListener('mouseover', (e) => {
        const card = e.target.closest?.('.task-item');
        if (!card) return;

        const list = card.closest('.task-list');
        if (card === _lastMoveHover && list === _lastMoveList) return;
        _lastMoveHover = card;
        _lastMoveList = list;

        const box = card.querySelector('[data-role="card-move"]');
        if (!box) return;

        const lists = BoardMotion.lists();
        const here = lists.indexOf(list);

        box.querySelectorAll('button[data-move]').forEach(btn => {
            const step = parseInt(btn.dataset.move, 10);
            const to = here + step;
            btn.disabled = here < 0 || to < 0 || to >= lists.length;
        });
    });

    // Clicking a card focuses it for the keyboard. Deliberately does not
    // preventDefault or stopPropagation, so the card's own click behaviour —
    // opening the drawer, the 3-dot menu — is untouched.
    document.addEventListener('click', (e) => {
        BoardMotion.closeMenu();
        const card = e.target.closest?.('.task-item');
        if (card) BoardMotion.focus(card);
    });

    // Double-click advances one column. Ignored on controls inside the card so
    // it cannot fire while someone is using the menu or a dropdown, and ignored
    // inside an expanded sub-work panel: two clicks there are two clicks on a
    // sub-item, not an instruction to move the parent card.
    document.addEventListener('dblclick', (e) => {
        const card = e.target.closest?.('.task-item');
        if (!card || e.target.closest('button, a, select, input, textarea, [data-subtask-panel]')) return;
        e.preventDefault();
        BoardMotion.send(card, { delta: 1 });
    });

    document.addEventListener('contextmenu', (e) => {
        const card = e.target.closest?.('.task-item');
        if (!card || e.target.closest('[data-subtask-panel]')) return;
        e.preventDefault();
        BoardMotion.focus(card);
        BoardMotion.menu(card, e.clientX, e.clientY);
    });

    document.addEventListener('keydown', (e) => {
        // Never steal a key from someone typing, and never while dragging.
        const typing = e.target.closest?.('input, textarea, select, [contenteditable="true"]');
        if (typing || BoardMotion.dragging) return;

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
            if (!BoardMotion.history.length) return;
            e.preventDefault();
            BoardMotion.undo();
            return;
        }

        const card = BoardMotion.focused;
        if (!card || !document.body.contains(card)) return;

        switch (e.key) {
            case 'ArrowRight': e.preventDefault(); BoardMotion.send(card, { delta: 1 }); break;
            case 'ArrowLeft':  e.preventDefault(); BoardMotion.send(card, { delta: -1 }); break;
            case 'ArrowDown':  e.preventDefault(); BoardMotion.nudge(card, 1); break;
            case 'ArrowUp':    e.preventDefault(); BoardMotion.nudge(card, -1); break;
            case 'Home':       e.preventDefault(); BoardMotion.send(card, { to: BoardMotion.lists()[0] }); break;
            case 'End':        e.preventDefault(); BoardMotion.send(card, { to: BoardMotion.lists().slice(-1)[0] }); break;
            case 'Escape':     BoardMotion.closeMenu(); BoardMotion.focus(null); break;
            default: break;
        }
    });

    // Exposed so the board's own <> arrow buttons can call the same path
    // rather than posting a move of their own.
    window.BoardMotion = BoardMotion;

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
            // Half the old 200px — the box opens small, and the user drags it
            // taller with summernote's own resize handle when they need to.
            height: 100,
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

    /* ── Start the clocks the server has already drawn ───────────────────
     *
     * The board arrives with its running counters rendered by Blade — a card
     * that was counting before the page loaded shows its time straight away.
     * But the ticker only ever started as a SIDE EFFECT of something the user
     * did (opening a sub-work panel, pressing Start), so on a page nobody
     * touched the numbers stood still until a refresh, and the refresh only
     * ever showed one more frozen frame.
     *
     * syncTicker() also stamps the anchor those server-rendered counters lack
     * — the server cannot know the viewer's clock — so this is what makes them
     * count at all, not merely what makes them count sooner.
     */
    TaskSubtask.syncTicker();

    // ── Deep-link: open a task (and tab) from a notification link, e.g. ?task=123&tab=activity ──
    const deepLinkParams = new URLSearchParams(window.location.search);
    const deepLinkTaskId = deepLinkParams.get('task');
    if (deepLinkTaskId) {
        const deepLinkTab = deepLinkParams.get('tab');
        const titleEl = document.querySelector(`[data-id="${deepLinkTaskId}"] .cursor-pointer`);

        if (titleEl) {
            titleEl.click();
            if (deepLinkTab) {
                TaskDrawer.showDrawerTab(deepLinkTab);
            }
        } else if (config.viewMode !== 'my-tasks') {
            // No card for it — a notification can point at a sub-work item, and
            // those are not cards any more. Open it straight from the server
            // instead of dropping the link on the floor.
            TaskSubtask.openSubtaskDrawer(cfg(), deepLinkTaskId)
                .then(() => { if (deepLinkTab) TaskDrawer.showDrawerTab(deepLinkTab); })
                .catch(() => { /* openSubtaskDrawer has already reported it */ });
        }
    }
});
