/**
 * Centralized Dropdown Display Updater
 * Updates dropdown UI for any context (modal, drawer, card)
 * Eliminates code duplication across drawer/modal/card update functions
 */

/**
 * Update dropdown display component for any context
 * @param {string} type - 'priority' | 'state' | 'assignees' | 'labels'
 * @param {string|array} value - Selected value(s)
 * @param {string} context - 'modal' | 'drawer' | 'card'
 * @param {string|null} taskId - Optional task ID for card context
 */
export function updateDropdownDisplay(type, value, context = 'modal', taskId = null) {
    const wrapper = getWrapper(type, context, taskId);
    if (!wrapper) {
        console.warn(`No dropdown wrapper found for type="${type}" context="${context}" taskId="${taskId}"`);
        return;
    }

    const isMulti = wrapper.dataset.multi === 'true';
    
    if (isMulti) {
        updateMultiSelectDropdown(wrapper, value);
    } else {
        updateSingleSelectDropdown(wrapper, value);
    }
}

/**
 * Get dropdown wrapper element
 */
function getWrapper(type, context, taskId) {
    const base = `[data-dropdown="${type}"][data-context="${context}"]`;
    if (taskId) {
        return document.querySelector(`${base}[data-task-id="${taskId}"]`);
    }
    return document.querySelector(base);
}

/**
 * Update single-select dropdown (priority, state)
 */
function updateSingleSelectDropdown(wrapper, value) {
    const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
    const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');
    
    const val = value || '';
    
    // Update hidden value
    if (hidden) hidden.value = String(val);
    
    // Update checkmarks and selection styling
    wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
        const itemValue = String(item.dataset.value || '');
        const isSelected = itemValue === String(val);
        
        item.classList.toggle('bg-blue-50', isSelected);
        
        const check = item.querySelector('[data-role="dropdown-check"]');
        if (check) check.classList.toggle('hidden', !isSelected);
    });
    
    // Update label display (clone selected item content)
    if (!labelEl) return;
    
    const selectedItem = wrapper.querySelector(
        `[data-role="dropdown-item"][data-value="${CSS.escape(String(val))}"]`
    );
    
    if (selectedItem) {
        const itemClone = selectedItem.cloneNode(true);
        const checkmark = itemClone.querySelector('[data-role="dropdown-check"]');
        if (checkmark) checkmark.remove();
        labelEl.innerHTML = itemClone.innerHTML;
    }
}

/**
 * Update multi-select dropdown (assignees, labels)
 */
function updateMultiSelectDropdown(wrapper, value) {
    const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
    const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');
    
    const selectedIds = Array.isArray(value) ? value.map(String) : [];
    const selectedSet = new Set(selectedIds);
    
    // Update hidden value (CSV format)
    if (hidden) hidden.value = selectedIds.join(',');
    
    // Update checkmarks and selection styling
    wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
        const itemValue = String(item.dataset.value || '');
        const isSelected = selectedSet.has(itemValue);
        
        item.classList.toggle('bg-blue-50', isSelected);
        
        const check = item.querySelector('[data-role="dropdown-check"]');
        if (check) check.classList.toggle('hidden', !isSelected);
    });
    
    // Update label display (handled by component system or custom logic)
    // This is already managed by setComponentDropdownSelection in most cases
}

/**
 * Batch update multiple dropdowns at once
 * @param {string} context - 'modal' | 'drawer' | 'card'
 * @param {string|null} taskId - Optional task ID for card context
 * @param {object} updates - { priority: 'high', state: '3', assignees: [1,2], labels: [5] }
 */
export function updateMultipleDropdowns(context, taskId, updates) {
    Object.entries(updates).forEach(([type, value]) => {
        updateDropdownDisplay(type, value, context, taskId);
    });
}
