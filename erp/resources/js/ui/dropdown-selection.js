function getWrapper(type, context = 'modal', taskId = null) {
    const base = `[data-dropdown="${type}"][data-context="${context}"]`;
    if (taskId) return document.querySelector(`${base}[data-task-id="${taskId}"]`);
    return document.querySelector(base);
}

export function getDropdownItemText(type, value, context = 'modal', taskId = null) {
    const wrapper = getWrapper(type, context, taskId);
    if (!wrapper) return '';
    const item = wrapper.querySelector(
        `[data-role="dropdown-item"][data-value="${CSS.escape(String(value))}"]`
    );
    return item ? item.textContent.trim() : '';
}

export function setComponentDropdownSelection(type, valueOrArray, labelText, context = 'modal', taskId = null) {
    const wrapper = getWrapper(type, context, taskId);
    if (!wrapper) return;

    const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');
    const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
    const multi = wrapper.dataset.multi === "true";

    let csv = '';
    if (multi) {
        const arr = Array.isArray(valueOrArray) ? valueOrArray : [];
        csv = arr.map(String).join(',');
    } else {
        csv = valueOrArray == null ? '' : String(valueOrArray);
    }

    if (hidden) hidden.value = csv;
    if (labelEl && labelText != null) labelEl.textContent = labelText;

    const selectedSet = new Set(csv.split(',').map(s => s.trim()).filter(Boolean));

    wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
        const v = String(item.dataset.value ?? '');
        const isSelected = selectedSet.has(v);

        item.classList.toggle('bg-blue-50', isSelected);

        const check = item.querySelector('[data-role="dropdown-check"]');
        if (check) check.classList.toggle('hidden', !isSelected);
    });
}