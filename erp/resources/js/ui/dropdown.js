/**
 * Dropdown helpers — toggle, close, filter, position.
 * Used by card dropdowns, drawer dropdowns, and modal dropdowns.
 *
 * Also includes the DropdownEngine class that powers the
 * <x-ui.dropdown> Blade component (data-role attribute system).
 */

// ═══════════════════════════════════════════════════════════
// Shared Positioning Function
// ═══════════════════════════════════════════════════════════

/**
 * Position a dropdown panel relative to its toggle button with viewport boundary detection
 * @param {HTMLElement} toggleButton - The button that toggles the dropdown
 * @param {HTMLElement|jQuery} panel - The dropdown panel to position (DOM element or jQuery object)
 */
export function positionDropdownPanel(toggleButton, panel) {
    if (!toggleButton || !panel) return;
    
    // Handle both jQuery objects and DOM elements
    const panelEl = panel.jquery ? panel[0] : panel;
    const $panel = panel.jquery ? panel : $(panel);
    
    const rect = toggleButton.getBoundingClientRect();
    const panelWidth = panelEl.offsetWidth || $panel.outerWidth() || 220;
    const panelHeight = panelEl.offsetHeight > 0 ? panelEl.offsetHeight : ($panel.outerHeight() || 240);
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Calculate initial position below the toggle
    let top = rect.bottom + 4;
    let left = rect.left;
    
    // Adjust if dropdown would go off-screen to the right
    if (left + panelWidth > viewportWidth - 10) {
        left = Math.max(10, viewportWidth - panelWidth - 10);
    }
    
    // Adjust if dropdown would go off-screen on the left
    if (left < 10) {
        left = 10;
    }
    
    // Adjust if dropdown would go off-screen at bottom
    if (top + panelHeight > viewportHeight - 10) {
        // Try to position above the toggle
        top = rect.top - panelHeight - 4;
        // If still off-screen, position at top with some padding
        if (top < 10) {
            top = 10;
        }
    }
    
    // Apply positioning (works for both DOM element and jQuery)
    if (panel.jquery) {
        $panel.css({ top: top + 'px', left: left + 'px' });
    } else {
        panelEl.style.top = top + 'px';
        panelEl.style.left = left + 'px';
    }
}

// ═══════════════════════════════════════════════════════════
// DropdownEngine — For <x-ui.dropdown> Blade components
// ═══════════════════════════════════════════════════════════

export class DropdownEngine {
    constructor(containerSelector = 'body') {
        this.container = document.querySelector(containerSelector) || document.body;
        this._bindEvents();
    }

    _bindEvents() {
        // Toggle button
        this.container.addEventListener('click', (e) => {
            const toggleBtn = e.target.closest('[data-role="dropdown-toggle"]');
            if (toggleBtn) {
                e.stopPropagation();
                const wrapper = toggleBtn.closest('[data-dropdown]');
                if (wrapper) this._toggle(wrapper);
                return;
            }

            // Item click
            const item = e.target.closest('[data-role="dropdown-item"]');
            if (item) {
                e.stopPropagation();
                const wrapper = item.closest('[data-dropdown]');
                if (wrapper) this._selectItem(wrapper, item);
                return;
            }

            // If click is outside any dropdown panel/toggle, close them all
            if (!e.target.closest('[data-role="dropdown-panel"]')) {
                this._closeAll();
            }
        });

        // Search input
        this.container.addEventListener('input', (e) => {
            if (e.target.matches('[data-role="dropdown-search"]')) {
                this._filterItems(e.target);
            }
        });
    }

    _toggle(wrapper) {
        const panel = wrapper.querySelector('[data-role="dropdown-panel"]');
        if (!panel) return;

        const wasHidden = panel.classList.contains('hidden');

        // Close all other component dropdowns first
        this._closeAll();

        if (wasHidden) {
            panel.classList.remove('hidden');

            // Position fixed dropdowns below the toggle button (both modal and card context)
            const context = wrapper.dataset.context;
            if (panel.classList.contains('fixed')) {
                const toggle = wrapper.querySelector('[data-role="dropdown-toggle"]');
                if (toggle) {
                    // Use requestAnimationFrame to ensure panel is rendered before positioning
                    requestAnimationFrame(() => {
                        positionDropdownPanel(toggle, panel);
                    });
                }
            }

            // Clear and focus search
            const search = panel.querySelector('[data-role="dropdown-search"]');
            if (search) {
                search.value = '';
                this._filterItems(search); // Reset filter
                setTimeout(() => search.focus(), 50);
            }
        }
    }

    _closeAll() {
        this.container.querySelectorAll('[data-role="dropdown-panel"]').forEach(p => {
            p.classList.add('hidden');
        });
    }

    _filterItems(searchInput) {
        const query = searchInput.value.toLowerCase().trim();
        const panel = searchInput.closest('[data-role="dropdown-panel"]');
        if (!panel) return;

        panel.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    }

    _selectItem(wrapper, item) {
        const multi = wrapper.dataset.multi === "true";
        const value = String(item.dataset.value);
        const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
        const check = item.querySelector('[data-role="dropdown-check"]');

        if (multi) {
            // --- MULTI SELECT ---
            item.classList.toggle("bg-blue-50");
            if (check) check.classList.toggle("hidden");

            const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');

            // ✅ selected is defined ONLY here
            const selected = new Set(
                (hidden?.value || "")
                    .split(",")
                    .map(s => s.trim())
                    .filter(Boolean)
            );

            if (selected.has(value)) selected.delete(value);
            else selected.add(value);

            if (hidden) hidden.value = Array.from(selected).join(",");

            // ✅ send ARRAY for multi
            this._dispatchChange(wrapper, {
                selectedValues: Array.from(selected),
                toggledValue: value 
            }, true);
            return;
        }

        // --- SINGLE SELECT (priority/state etc.) ---
        wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(i => {
            i.classList.remove("bg-blue-50");
            const c = i.querySelector('[data-role="dropdown-check"]');
            if (c) c.classList.add("hidden");
        });

        item.classList.add("bg-blue-50");
        if (check) check.classList.remove("hidden");

        if (labelEl) labelEl.textContent = item.textContent.trim();

        const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');
        if (hidden) hidden.value = value;

        this._closeAll();

        // ✅ send STRING for single
        this._dispatchChange(wrapper, { value }, false);
    }

    _dispatchChange(wrapper, data, multi) {
        const context = wrapper.dataset.context;

        let taskId = wrapper.dataset.taskId;
        if (!taskId && context === 'drawer' && window.currentDrawerTaskId) {
            taskId = String(window.currentDrawerTaskId);
        }

        const value = multi ? (data.selectedValues ?? []) : (data.value ?? '');
        document.dispatchEvent(new CustomEvent('dropdown:change', {
            detail: {
                type: wrapper.dataset.dropdown,
                taskId: taskId,
                value,
                wrapper
            },
        }));
    }
}


// ═══════════════════════════════════════════════════════════
// Legacy dropdown helpers — For drawer & modal dropdowns
// that use manual IDs (e.g., toggleDropdown('drawerStateDropdown'))
// ═══════════════════════════════════════════════════════════

/* ── Toggle a single dropdown by ID ── */
export function toggleDropdown(dropdownId) {
    const $dropdown = $('#' + dropdownId);
    if (!$dropdown.length) {
        console.error('Dropdown not found:', dropdownId);
        return;
    }

    const wasHidden = $dropdown.hasClass('hidden');

    // Close everything first
    closeAllDropdowns();

    if (wasHidden) {
        $dropdown.removeClass('hidden');

        // Position fixed card date-pickers next to their buttons
        if (dropdownId.startsWith('startdate-') || dropdownId.startsWith('duedate-')) {
            const buttonId = dropdownId.replace('date-', 'date-btn-');
            const button = document.getElementById(buttonId);
            if (button) {
                positionDropdownPanel(button, $dropdown);
            }
        }

        // Position fixed sub-work item row date-pickers next to their buttons
        // ("sub" prefix keeps these distinct from the Kanban card's own startdate-/duedate- ids
        // above, since a sub-task can render as both a card and a row at the same time)
        if (dropdownId.startsWith('substartdate-') || dropdownId.startsWith('subduedate-')) {
            const buttonId = dropdownId.replace('date-', 'date-btn-');
            const button = document.getElementById(buttonId);
            if (button) {
                positionDropdownPanel(button, $dropdown);
            }
        }

        // Position fixed drawer date-pickers next to their buttons
        if (dropdownId === 'drawerStartDatePicker' || dropdownId === 'drawerDueDatePicker') {
            const buttonId = dropdownId === 'drawerStartDatePicker'
                ? 'drawerStartDateButton'
                : 'drawerDueDateButton';
            const button = document.getElementById(buttonId);
            if (button) {
                positionDropdownPanel(button, $dropdown);
            }
        }

        // Position fixed modal date-pickers next to their buttons
        if (dropdownId === 'modalStartDatePicker' || dropdownId === 'modalDueDatePicker') {
            const buttonId = dropdownId === 'modalStartDatePicker'
                ? 'modalStartDateButton'
                : 'modalDueDateButton';
            const button = document.getElementById(buttonId);
            if (button) {
                positionDropdownPanel(button, $dropdown);
            }
        }

        // Position fixed "Create sub-work item" modal date-pickers next to their buttons
        if (dropdownId === 'createSubtaskStartDatePicker' || dropdownId === 'createSubtaskDueDatePicker') {
            const buttonId = dropdownId === 'createSubtaskStartDatePicker'
                ? 'createSubtaskStartDateButton'
                : 'createSubtaskDueDateButton';
            const button = document.getElementById(buttonId);
            if (button) {
                positionDropdownPanel(button, $dropdown);
            }
        }

        // Position fixed sub-work item row "..." menus next to their trigger button
        // (the Kanban card's own "task-menu-" 3-dot menu is CSS-absolute and needs no JS positioning)
        if (dropdownId.startsWith('sub-task-menu-')) {
            const button = document.getElementById(dropdownId.replace('sub-task-menu-', 'sub-task-menu-btn-'));
            if (button) {
                positionDropdownPanel(button, $dropdown);
            }
        }

        // Auto-focus search input
        const $searchInput = $dropdown.find('input[type="text"]').first();
        if ($searchInput.length) setTimeout(() => $searchInput.focus(), 50);
    }
}

/* ── Close all dropdowns (card + drawer + 3-dot menus) ── */
export function closeAllDropdowns() {
    const prefixes = ['priority-', 'state-', 'startdate-', 'duedate-', 'assignee-', 'label-', 'task-menu-', 'substartdate-', 'subduedate-', 'sub-task-menu-'];

    prefixes.forEach(prefix => {
        $(`[id^="${prefix}"]`).each(function () {
            const idAfterPrefix = this.id.replace(prefix, '');
            if (/^\d+$/.test(idAfterPrefix)) $(this).addClass('hidden');
        });
    });

    // Drawer & 3-dot menus
    $('.drawer-dropdown').addClass('hidden');
    $('#drawer-more-menu').addClass('hidden');
    $('#addSubworkMenu').addClass('hidden');

    // Modal date pickers
    $('#modalStartDatePicker').addClass('hidden');
    $('#modalDueDatePicker').addClass('hidden');

    // "Create sub-work item" modal date pickers
    $('#createSubtaskStartDatePicker').addClass('hidden');
    $('#createSubtaskDueDatePicker').addClass('hidden');

    // Also close component dropdowns
    document.querySelectorAll('[data-role="dropdown-panel"]').forEach(p => p.classList.add('hidden'));
}

// export function filterDropdown(input, taskId, dropdownType) {
//     console.log(`Filtering dropdown: ${dropdownType}, Task ID: ${taskId}, Query: "${$(input).val()}"`);
//     const query = $(input).val().toLowerCase().trim();
//     let $dropdown;

//     if (taskId) {
//         $dropdown = $(`#${dropdownType}-${taskId}`);
//     } else {
//         $dropdown = $(input).closest('.drawer-dropdown');
//     }
//     if (!$dropdown.length) return;

//     $dropdown.find(`.${dropdownType}-item, .drawer-${dropdownType}-item`).each(function () {
//         const name = ($(this).data('name') || $(this).text() || '').toLowerCase();
//         $(this).toggle(name.includes(query));
//     });
// }

// export function toggleModalDropdown(e, dropdownId, triggerId) {
//     console.log(`Toggling modal dropdown: ${dropdownId}, Trigger: ${triggerId}`);
//     e.stopPropagation();
//     const dropdown = document.getElementById(dropdownId);
//     const trigger = document.getElementById(triggerId);
//     if (!dropdown || !trigger) return;

//     // Close other modal dropdowns
//     document.querySelectorAll('[id^="modal-"][id$="-dropdown"]').forEach(dd => {
//         if (dd.id !== dropdownId) dd.classList.add('hidden');
//     });

//     const isHidden = dropdown.classList.contains('hidden');

//     if (isHidden) {
//         const searchInput = dropdown.querySelector('input[type="text"]');
//         if (searchInput) {
//             searchInput.value = '';
//             const filterMap = {
//                 'modal-assignees-dropdown': filterAssigneesModal,
//                 'modal-labels-dropdown': filterLabelsModal,
//                 'modal-state-dropdown': filterStatesModal,
//                 'modal-priority-dropdown': filterPrioritiesModal,
//             };
//             if (filterMap[dropdownId]) filterMap[dropdownId]();
//         }

//         const rect = trigger.getBoundingClientRect();
//         dropdown.style.top = (rect.bottom + 4) + 'px';
//         dropdown.style.left = rect.left + 'px';
//         dropdown.classList.remove('hidden');

//         setTimeout(() => { if (searchInput) searchInput.focus(); }, 50);
//     } else {
//         dropdown.classList.add('hidden');
//     }
// }

// export function filterAssigneesModal() {
//     console.log('Filtering assignees modal with query:', document.getElementById('assignees-search')?.value);
//     const v = (document.getElementById('assignees-search')?.value || '').toLowerCase();
//     document.querySelectorAll('.assignee-item').forEach(item => {
//         item.style.display = (item.getAttribute('data-name') || '').includes(v) ? 'flex' : 'none';
//     });
// }

// export function filterLabelsModal() {
//     console.log('Filtering labels modal with query:', document.getElementById('labels-search')?.value);
//     const v = (document.getElementById('labels-search')?.value || '').toLowerCase();
//     document.querySelectorAll('.label-item').forEach(item => {
//         item.style.display = (item.getAttribute('data-name') || '').includes(v) ? 'flex' : 'none';
//     });
// }

// export function filterStatesModal() {
//     console.log('Filtering states modal with query:', document.getElementById('state-search')?.value);
//     const v = (document.getElementById('state-search')?.value || '').toLowerCase();
//     document.querySelectorAll('.state-item').forEach(item => {
//         item.style.display = (item.getAttribute('data-name') || '').includes(v) ? 'flex' : 'none';
//     });
// }

// export function filterPrioritiesModal() {
//     console.log('Filtering priorities modal with query:', document.getElementById('priority-search')?.value);
//     const v = (document.getElementById('priority-search')?.value || '').toLowerCase();
//     document.querySelectorAll('.priority-item').forEach(item => {
//         item.style.display = (item.getAttribute('data-name') || '').includes(v) ? 'flex' : 'none';
//     });
// }

/* ── Close modal dropdowns on outside click ── */
export function initDropdownListeners() {
    document.addEventListener('click', function (e) {
        // Modal dropdowns
        if (!e.target.closest('[onclick*="toggleModalDropdown"]') &&
            !e.target.closest('[id^="modal-"][id$="-dropdown"]')) {
            document.querySelectorAll('[id^="modal-"][id$="-dropdown"]').forEach(dd => dd.classList.add('hidden'));
        }

        // Check if click is on a toggle button or inside a dropdown
        const clickedToggle = e.target.closest('[onclick*="toggleDropdown"]');
        const clickedInsideDropdown = e.target.closest('[id^="startdate-"], [id^="duedate-"], [id^="task-menu-"], [id^="substartdate-"], [id^="subduedate-"], [id^="sub-task-menu-"]');
        const clickedInsideDrawer = e.target.closest('.drawer-dropdown');
        const clickedInsideModalDatePicker = e.target.closest('#modalStartDatePicker, #modalDueDatePicker');
        const clickedModalDateButton = e.target.closest('#modalStartDateButton, #modalDueDateButton');
        
        // If clicked outside all dropdowns and toggle buttons, close them.
        // Delegates to the single closeAllDropdowns() implementation above instead of
        // keeping a second, separately-maintained prefix list here (that list had drifted
        // out of sync before — e.g. missing the "sub" prefixes — which is why the sub-work
        // item date pickers/menu weren't closing on outside click).
        if (!clickedToggle && !clickedInsideDropdown && !clickedInsideDrawer && !clickedInsideModalDatePicker && !clickedModalDateButton) {
            closeAllDropdowns();
        }
    });
}