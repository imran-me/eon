/**
 * Task Drawer — The right-side panel that opens when you click a task title.
 * Manages drawer state, properties tab, activity tab, description auto-save,
 * links, attachments, and comments.
 */
import { TaskApi } from './task-api';
import { getTimeAgo, escapeHtml, formatFileSize, formatDateDisplay, formatDatetimeLocal, getFileIcon } from './task-utils';
import { getDropdownItemText, setComponentDropdownSelection } from '../ui/dropdown-selection';
import { TaskStore } from './task-store';
import { loadSubtasks } from './task-subtask';

export let currentDrawerTaskId = null;

/* ═══════════ Label selections map (shared with card) ═══════════ */
export const taskLabelSelections = new Map();

/* ═══════════ Open / Close ═══════════ */
export function openTaskDrawer(config, taskId, title, description, assignedTo, startDate, dueDate, priority, columnId, labelIds, createdBy, parentId = null, parentTitle = null) {
    currentDrawerTaskId = taskId;

    // Check TaskStore for latest data first
    const storedTask = TaskStore.get(taskId);
    
    // Use stored data if available, otherwise use passed parameters
    if (storedTask) {
        title = storedTask.title ?? title;
        description = storedTask.description ?? description;
        assignedTo = storedTask.assignee_ids ?? storedTask.assigned_to ?? assignedTo;
        startDate = storedTask.start_date ?? startDate;
        dueDate = storedTask.due_date ?? dueDate;
        priority = storedTask.priority ?? priority;
        columnId = storedTask.column_id ?? columnId;
        labelIds = storedTask.label_ids ?? labelIds;
    }

    // Store task data in TaskStore for later use
    TaskStore.set({
        id: taskId,
        title: title,
        description: description,
        assignee_ids: assignedTo,
        assigned_to: assignedTo,
        start_date: startDate,
        due_date: dueDate,
        priority: priority,
        column_id: columnId,
        label_ids: labelIds
    });

    // Initialize label selections
    if (labelIds && labelIds.length > 0) {
        taskLabelSelections.set(taskId, labelIds);
    } else if (!taskLabelSelections.has(taskId)) {
        taskLabelSelections.set(taskId, []);
    }

    // Populate data
    $('#drawerTaskTitle').text(title || 'Untitled');
    // Breadcrumb: show TASK-parentId / TASK-taskId when task has a parent
    if (parentId) {
        $('#drawerTaskCode').html(
            `<span class="cursor-pointer text-blue-600 hover:underline" onclick="window._openParentDrawer && window._openParentDrawer(${parentId})">`
            + `TASK-${parentId}</span><span class="mx-1 text-gray-400">/</span>TASK-${taskId}`
        );
        // Show parent row in Properties
        $('#drawerParentRow').removeClass('hidden');
        $('#drawerParentValue').html(
            `<span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full">`
            + `TASK-${parentId} <span class="text-gray-500 text-xs font-normal">${escapeHtml(parentTitle || '')}</span>`
            + `<button onclick="window.removeTaskParent && window.removeTaskParent(${taskId})" class="ml-1 text-gray-400 hover:text-red-500">&times;</button></span>`
        );
    } else {
        $('#drawerTaskCode').text('TASK-' + taskId);
        $('#drawerParentRow').removeClass('hidden');
        $('#drawerParentValue').html(
            `<button onclick="window.openAddParentModal && window.openAddParentModal(${taskId})" class="text-sm text-gray-400 hover:text-blue-600">Add parent work item</button>`
        );
    }
    $('#linkTaskId').val(taskId);
    $('#drawerCreatedBy').text(createdBy || 'Unknown');
    $('#drawerTaskDescription').summernote('code', description || '');

    const stateName = getDropdownItemText('state', columnId, 'drawer') || 'State';
    setComponentDropdownSelection('state', columnId, stateName, 'drawer');
    updateDrawerStateDisplayComponent(columnId);

    const prioNameMap = { 'high': 'High', 'medium': 'Medium', 'low': 'Low', '': 'None', null: 'None', undefined: 'None' };
    setComponentDropdownSelection('priority', priority ?? '', prioNameMap[priority] ?? 'Priority', 'drawer');
    updateDrawerPriorityDisplayComponent(priority);

    const assigneesArr = (assignedTo || []).map(String);
    setComponentDropdownSelection('assignees', assigneesArr, assigneesArr.length ? `${assigneesArr.length} assignees` : 'Assignees', 'drawer');
    updateDrawerAssigneeDisplayComponent(assigneesArr);


    const labelsArr = (labelIds || []).map(String);
    setComponentDropdownSelection('labels', labelsArr, labelsArr.length ? `${labelsArr.length} labels` : 'Labels', 'drawer');
    updateDrawerLabelsDisplayComponent(labelsArr);

    // Properties
    populateDrawerDates(startDate, dueDate);

    // Load async data
    loadTaskAttachments(config);
    loadTaskLinks(config);
    loadTaskActivities(config);
    fetchLastEditedInfo(config, taskId);
    loadSubtasks(config, taskId);

    // Bind taskId to all drawer dropdowns
    bindDrawerTaskId(taskId);

    // Show
    $('#taskDrawerBackdrop').removeClass('hidden');
    $('#taskDrawer').removeClass('hidden');
}

function bindDrawerTaskId(taskId) {
  document
    .querySelectorAll('[data-dropdown][data-context="drawer"]')
    .forEach(w => {
      w.dataset.taskId = String(taskId);
    });
}

export function closeTaskDrawer() {
    currentDrawerTaskId = null;
    $('#taskDrawerBackdrop').addClass('hidden');
    $('#taskDrawer').addClass('hidden');
}

/* ═══════════ Tabs ═══════════ */
export function showDrawerTab(tab) {
    const propBtn = document.getElementById('tabPropertiesBtn');
    const actBtn = document.getElementById('tabActivityBtn');
    const prop = document.getElementById('tabProperties');
    const act = document.getElementById('tabActivity');

    const active = 'border-blue-600 text-blue-600';
    const inactive = 'border-transparent text-gray-500 hover:text-gray-800';

    if (tab === 'properties') {
        prop.classList.remove('hidden');
        act.classList.add('hidden');
        propBtn.className = propBtn.className.replace(inactive, active);
        actBtn.className = actBtn.className.replace(active, inactive);
    } else {
        act.classList.remove('hidden');
        prop.classList.add('hidden');
        actBtn.className = actBtn.className.replace(inactive, active);
        propBtn.className = propBtn.className.replace(active, inactive);
    }
}

/* ═══════════ Toggle collapsible sections ═══════════ */
export function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;
    section.classList.toggle('hidden');
    const caretId = sectionId.replace('Body', 'Caret');
    const caret = document.getElementById(caretId);
    if (caret) caret.classList.toggle('rotate-180');
}

export function updateDrawerStateDisplayComponent(columnId, context = 'drawer') {
    const wrapper = document.querySelector(`[data-dropdown="state"][data-context="${context}"]`);
    if (!wrapper) return;

    const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
    const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');

    // Update hidden value
    if (hidden) hidden.value = String(columnId || '');

    // Update checkmarks
    wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
        const id = String(item.dataset.value);
        const isSelected = id === String(columnId);

        item.classList.toggle('bg-blue-50', isSelected);

        const check = item.querySelector('[data-role="dropdown-check"]');
        if (check) check.classList.toggle('hidden', !isSelected);
    });

    // Update label text with icon
    if (!labelEl || !columnId) {
        if (labelEl) labelEl.textContent = 'State';
        return;
    }

    const selectedItem = wrapper.querySelector(`[data-role="dropdown-item"][data-value="${CSS.escape(String(columnId))}"]`);
    if (selectedItem) {
        // Clone the item content (icon + text) into the label
        const itemClone = selectedItem.cloneNode(true);
        // Remove the checkmark from the clone
        const checkmark = itemClone.querySelector('[data-role="dropdown-check"]');
        if (checkmark) checkmark.remove();
        
        // Extract just the icon and text, preserving all styles
        const content = itemClone.innerHTML;
        labelEl.innerHTML = `<span class="flex items-center gap-2">${content}</span>`;
    }
}

export function updateDrawerPriorityDisplayComponent(priority, context = 'drawer') {
    const wrapper = document.querySelector(`[data-dropdown="priority"][data-context="${context}"]`);
    if (!wrapper) return;

    const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
    const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');

    const value = priority || '';

    // Update hidden value
    if (hidden) hidden.value = String(value);

    // Update checkmarks
    wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
        const id = String(item.dataset.value);
        const isSelected = id === String(value);

        item.classList.toggle('bg-blue-50', isSelected);

        const check = item.querySelector('[data-role="dropdown-check"]');
        if (check) check.classList.toggle('hidden', !isSelected);
    });

    // Update label text with icon
    if (!labelEl) return;

    const selectedItem = wrapper.querySelector(`[data-role="dropdown-item"][data-value="${CSS.escape(String(value))}"]`);
    if (selectedItem) {
        // Clone the item content (icon + text) into the label
        const itemClone = selectedItem.cloneNode(true);
        // Remove the checkmark from the clone
        const checkmark = itemClone.querySelector('[data-role="dropdown-check"]');
        if (checkmark) checkmark.remove();
        labelEl.innerHTML = itemClone.innerHTML;
    }
}

/* ═══════════ Properties: Dates ═══════════ */
function populateDrawerDates(startDate, dueDate) {
    if (startDate) {
        $('#drawerStartDateInput').val(formatDatetimeLocal(startDate));
        $('#drawerStartDateDisplay').text(formatDateDisplay(startDate));
    } else {
        $('#drawerStartDateInput').val('');
        $('#drawerStartDateDisplay').text('Add start date');
    }

    if (dueDate) {
        $('#drawerDueDateInput').val(formatDatetimeLocal(dueDate));
        $('#drawerDueDateDisplay').text(formatDateDisplay(dueDate));
    } else {
        $('#drawerDueDateInput').val('');
        $('#drawerDueDateDisplay').text('Add due date');
    }
}

export function updateDrawerDate(config, taskId, dateType) {
    console.log('Updating drawer date:', { taskId, dateType });
    const inputId = dateType === 'start_date' ? 'drawerStartDateInput' : 'drawerDueDateInput';
    const displayId = dateType === 'start_date' ? 'drawerStartDateDisplay' : 'drawerDueDateDisplay';
    const dateValue = $(`#${inputId}`).val();
    if (!dateValue) return;

    const formattedDate = dateValue.replace('T', ' ') + ':00';
    $(`#${displayId}`).text(formatDateDisplay(dateValue));
    updateTaskFieldAndRefresh(config, taskId, dateType, formattedDate);
}

export function setDrawerQuickDate(config, taskId, dateType, days) {
    console.log('setDrawerQuickDate called with:', { taskId, dateType, days });
    const inputId = dateType === 'start_date' ? 'drawerStartDateInput' : 'drawerDueDateInput';
    const displayId = dateType === 'start_date' ? 'drawerStartDateDisplay' : 'drawerDueDateDisplay';

    if (days === null) {
        // Clear
        $(`#${inputId}`).val('');
        $(`#${displayId}`).text(dateType === 'start_date' ? 'Add start date' : 'Add due date');
        updateTaskFieldAndRefresh(config, taskId, dateType, '');
    } else {
        const date = new Date();
        date.setDate(date.getDate() + days);
        // Use formatDatetimeLocal utility to format the date
        const localDatetime = formatDatetimeLocal(date.toISOString());
        $(`#${inputId}`).val(localDatetime);
        updateDrawerDate(config, taskId, dateType);
    }
}

export function updateDrawerAssigneeDisplayComponent(assigneeIds, context = 'drawer') {
  const wrapper = document.querySelector(`[data-dropdown="assignees"][data-context="${context}"]`);
  if (!wrapper) return;

  const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
  const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');

  const ids = (assigneeIds || []).map(String);
  const set = new Set(ids);

  // 1) Toggle checkmarks + bg for selected items
  wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
    const id = String(item.dataset.value);
    const isSelected = set.has(id);

    item.classList.toggle('bg-blue-50', isSelected);

    const check = item.querySelector('[data-role="dropdown-check"]');
    if (check) check.classList.toggle('hidden', !isSelected);
  });

  // 2) Update hidden selected csv
  if (hidden) hidden.value = ids.join(',');

  // 3) Update label UI
  if (!labelEl) return;

  if (ids.length === 0) {
    labelEl.textContent = 'Select assignees';
    return;
  }

  // build selected list with name + initials
  const selected = [];
  wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
    const id = String(item.dataset.value);
    if (!set.has(id)) return;
    const name = item.dataset.name || item.textContent.trim();
    const initial = (name?.[0] || '?').toUpperCase();
    const image = item.dataset.image || '';
    selected.push({ id, name, initial, image });
  });

  // Same design logic you had before
  if (selected.length === 1) {
    // Show only avatar for single selection
    const avatarHtml = selected[0].image 
    ? `<img src="${selected[0].image}" alt="${selected[0].name}" class="w-5 h-5 object-cover rounded-full">`
    : `<div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-semibold flex items-center justify-center">${escapeHtml(selected[0].initial)}</div>`;

    labelEl.innerHTML = `<div class="flex items-center gap-2">${avatarHtml}</div>`;
    
  } else if (selected.length <= 3) {
    const html = selected.map(a => {
        const avatarHtml = a.image
          ? `<img src="${escapeHtml(a.image)}" alt="${escapeHtml(a.name)}" class="w-5 h-5 rounded-full object-cover ring-2 ring-white -ml-2 first:ml-0">`
          : `<div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-semibold flex items-center justify-center ring-2 ring-white -ml-2 first:ml-0">${escapeHtml(a.initial)}</div>`;
      return avatarHtml;
    }).join('');
    labelEl.innerHTML = `<div class="flex items-center">${html}</div>`;
  } else {
    const first = selected.slice(0, 2);
    const html = first.map(a => {
        const avatarHtml = a.image
          ? `<img src="${escapeHtml(a.image)}" alt="${escapeHtml(a.name)}" class="w-5 h-5 rounded-full object-cover ring-2 ring-white -ml-2 first:ml-0">`
          : `<div class="w-5 h-5 rounded-full bg-indigo-500 text-white text-[9px] font-semibold flex items-center justify-center ring-2 ring-white -ml-2 first:ml-0">${escapeHtml(a.initial)}</div>`;
      return avatarHtml;
    }).join('');
    labelEl.innerHTML = `
      <div class="flex items-center gap-1">
        <div class="flex">${html}</div>
        <span class="text-xs text-gray-600">+${selected.length - 2} more</span>
      </div>
    `;
  }
}

export function updateDrawerLabelsDisplayComponent(labelIds, context = 'drawer') {
  const wrapper = document.querySelector(`[data-dropdown="labels"][data-context="${context}"]`);
  if (!wrapper) return;

  const labelEl = wrapper.querySelector('[data-role="dropdown-label"]');
  const hidden = wrapper.querySelector('[data-role="dropdown-selected"]');

  const ids = (labelIds || []).map(String);
  const set = new Set(ids);

  // 1) Toggle checkmarks + bg for selected items
  wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
    const id = String(item.dataset.value);
    const isSelected = set.has(id);

    item.classList.toggle('bg-blue-50', isSelected);

    const check = item.querySelector('[data-role="dropdown-check"]');
    if (check) check.classList.toggle('hidden', !isSelected);
  });

  // 2) Update hidden selected csv
  if (hidden) hidden.value = ids.join(',');

  // 3) Update label UI
  if (!labelEl) return;

  if (ids.length === 0) {
    labelEl.textContent = 'Select labels';
    return;
  }

  // Build selected list with name + color
  const labels = [];
  wrapper.querySelectorAll('[data-role="dropdown-item"]').forEach(item => {
    const id = String(item.dataset.value);
    if (!set.has(id)) return;
    
    const name = item.dataset.name || item.textContent.trim();
    const colorSpan = item.querySelector('span[style*="background"]');
    const color = colorSpan ? colorSpan.style.background : '#6B7280';
    
    labels.push({ id, name, color });
  });

  // Display labels similarly to assignees
  if (labels.length === 1) {
    const l = labels[0];
    const dn = l.name.length > 20 ? l.name.substring(0, 20) + '...' : l.name;
    labelEl.innerHTML = `
      <div class="flex items-center gap-2">
        <span class="w-3.5 h-3.5 rounded-sm" style="background:${l.color};"></span>
        <span>${escapeHtml(dn)}</span>
      </div>
    `;
  } else if (labels.length <= 3) {
    const html = labels.map(l => {
      const dn = l.name.length > 12 ? l.name.substring(0, 12) + '...' : l.name;
      return `<div class="flex items-center gap-1" title="${escapeHtml(l.name)}"><span class="w-2.5 h-2.5 rounded-sm" style="background:${l.color};"></span><span class="text-xs">${escapeHtml(dn)}</span></div>`;
    }).join('');
    labelEl.innerHTML = `<div class="flex flex-wrap gap-1.5">${html}</div>`;
  } else {
    labelEl.innerHTML = `
      <div class="flex items-center gap-1.5">
        <svg class="w-4 h-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
          <path d="M17.707 9.293 10.707 2.293A1 1 0 0 0 10 2H3a1 1 0 0 0-1 1v7a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l7-7a1 1 0 0 0 0-1.414ZM5.5 6.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/>
        </svg>
        <span class="text-sm">${labels.length} Labels</span>
      </div>
    `;
  }
}

/* ═══════════ Links ═══════════ */
export function loadTaskLinks(config) {
    const taskId = currentDrawerTaskId;
    if (!taskId) return;

    TaskApi.getLinks(config.role, taskId).then(data => {
        const links = data.links || [];
        $('#drawerLinksCount').text(links.length);
        if (links.length === 0) {
            $('#drawerLinksList').html('<div class="px-3 py-6 text-center text-sm text-gray-400">No links yet</div>');
        } else {
            $('#drawerLinksList').html(links.map(l => renderLinkRow(l, config.role)).join(''));
        }
    }).catch(err => console.error('Failed to load links:', err));
}

function renderLinkRow(link, role) {
    return `<div class="flex items-center justify-between gap-3 px-3 py-2 rounded-md border border-gray-200 hover:bg-gray-50" data-link-id="${link.id}"><div class="flex items-center gap-2 min-w-0"><svg class="w-4 h-4 text-gray-500 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M12.586 2.586a2 2 0 012.828 0l2 2a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0 1 1 0 011.414-1.414.5.5 0 00.707 0l5-5a.5.5 0 000-.707l-2-2a.5.5 0 00-.707 0l-2 2A1 1 0 019.172 4l2.414-2.414z"/><path d="M7.414 7.414a2 2 0 012.828 0 1 1 0 11-1.414 1.414.5.5 0 00-.707 0l-5 5a.5.5 0 000 .707l2 2a.5.5 0 00.707 0l2-2A1 1 0 019.828 16l-2.414 2.414a2 2 0 01-2.828 0l-2-2a2 2 0 010-2.828l5-5z"/></svg><a href="${link.url}" target="_blank" class="text-sm text-gray-800 hover:text-blue-600 truncate" title="${link.display_title}">${link.display_title}</a></div><div class="flex items-center gap-2 shrink-0"><span class="text-xs text-gray-500">${link.created_at}</span><button onclick="copyLinkToClipboard(this.dataset.url)" data-url="${link.url}" class="p-1.5 rounded hover:bg-gray-100" title="Copy link"><svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor"><path d="M7 7a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H9a2 2 0 01-2-2V7z"/><path d="M5 3a2 2 0 00-2 2v8a2 2 0 002 2h1a1 1 0 100-2H5V5h8v1a1 1 0 102 0V5a2 2 0 00-2-2H5z"/></svg></button><button onclick="openEditLinkModal(${link.id}, this.dataset.url, this.dataset.title)" data-url="${link.url}" data-title="${link.display_title}" class="p-1.5 rounded hover:bg-blue-100" title="Edit"><svg class="w-4 h-4 text-blue-600" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"/><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"/></svg></button><button onclick="deleteLink(${link.id}, ${link.task_id})" class="p-1.5 rounded hover:bg-red-100" title="Delete"><svg class="w-4 h-4 text-red-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></button></div></div>`;
}

/* ═══════════ Attachments ═══════════ */
export function loadTaskAttachments(config) {
    const taskId = currentDrawerTaskId;
    if (!taskId) return;

    $('#drawerAttachmentsList').html('<div class="px-3 py-6 text-center text-sm text-gray-500"><svg class="animate-spin h-5 w-5 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span class="mt-2 block">Loading attachments...</span></div>');

    TaskApi.getAttachments(config.role, taskId).then(data => {
        const atts = data.attachments || [];
        $('#drawerAttachmentsCount').text(atts.length);
        if (atts.length === 0) {
            $('#drawerAttachmentsList').html('<div class="px-3 py-6 text-center text-sm text-gray-400">No attachments yet</div>');
        } else {
            $('#drawerAttachmentsList').html(atts.map(a => renderAttachmentRow(a)).join(''));
        }
    }).catch(() => {
        $('#drawerAttachmentsList').html('<div class="px-3 py-6 text-center text-sm text-red-500">Failed to load attachments</div>');
    });
}

function renderAttachmentRow(att) {
    const ext = att.file_name.split('.').pop().toLowerCase();
    const icon = getFileIcon(ext);
    return `<div class="flex items-center justify-between gap-3 px-3 py-2 rounded-md hover:bg-gray-50" data-attachment-id="${att.id}"><div class="flex items-center gap-3 min-w-0"><div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-600 shrink-0"><i class="${icon}"></i></div><div class="min-w-0"><div class="text-sm text-gray-800 truncate">${att.file_name}</div><div class="text-xs text-gray-500">${formatFileSize(att.file_size)}</div></div></div><div class="flex items-center gap-2 shrink-0"><div class="w-7 h-7 rounded-full bg-indigo-500 overflow-hidden flex items-center justify-center text-xs text-white font-semibold" title="${att.uploader_name || 'Unknown'}">${(att.uploader_name || 'U').charAt(0).toUpperCase()}</div><a href="${att.file_url}" download target="_blank" class="p-1.5 rounded hover:bg-gray-100" title="Download"><svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></a><button onclick="deleteAttachment(${att.id}, ${att.task_id})" class="p-1.5 rounded hover:bg-red-100" title="Delete"><svg class="w-4 h-4 text-red-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg></button></div></div>`;
}

export function handleAttachmentUpload(config, event) {
    const files = event.target.files;
    if (!files.length) return;
    if (!currentDrawerTaskId) { alert('Please open a task first.'); event.target.value = ''; return; }
    for (let i = 0; i < files.length; i++) {
        uploadFileToServer(config, files[i], currentDrawerTaskId);
    }
    event.target.value = '';
}

function uploadFileToServer(config, file, taskId) {
    TaskApi.uploadAttachment(config.attachmentUploadUrl, file, taskId)
        .then(data => {
            if (data.success) {
                loadTaskAttachments(config);
                loadTaskActivities(config);
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        })
        .catch(err => {
            console.error('Upload failed:', err);
            alert(`Failed to upload ${file.name}\nError: ${err.message}`);
        });
}

export function deleteAttachment(config, attachmentId, taskId) {
    if (!confirm('Are you sure you want to delete this attachment?')) return;

    TaskApi.deleteAttachment(config.role, attachmentId).then(data => {
        if (data.success) {
            const reloadTaskId = data.task_id || currentDrawerTaskId;
            $(`[data-attachment-id="${attachmentId}"]`).fadeOut(300, function () {
                $(this).remove();
                const count = $('#drawerAttachmentsList .rounded-md').length;
                $('#drawerAttachmentsCount').text(count);
                if (count === 0) $('#drawerAttachmentsList').html('<div class="px-3 py-6 text-center text-sm text-gray-400">No attachments yet</div>');
            });
            if (reloadTaskId) loadTaskActivities(config);
        }
    }).catch(err => { console.error('Delete failed:', err); alert('Failed to delete attachment'); });
}

/* ═══════════ Activities ═══════════ */
export function loadTaskActivities(config) {
    const taskId = currentDrawerTaskId;
    if (!taskId) return;

    $('#drawerActivityList').html('<div class="text-center py-8 text-gray-400 text-sm"><svg class="animate-spin h-5 w-5 mx-auto text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span class="mt-2 block">Loading activities...</span></div>');

    TaskApi.getActivities(config.role, taskId).then(data => {
        if (data.success) {
            renderActivities(data.activities);
        } else {
            $('#drawerActivityList').html('<div class="text-center py-6 text-red-500 text-sm">Failed to load activities</div>');
        }
    }).catch(() => {
        $('#drawerActivityList').html('<div class="text-center py-6 text-red-500 text-sm">Error loading activities</div>');
    });
}

function renderActivities(activities) {
    if (activities.length === 0) {
        $('#drawerActivityList').html('<div class="px-3 py-6 text-center text-sm text-gray-400">No activities yet</div>');
        return;
    }
    $('#drawerActivityList').html(activities.map(a => a.type === 'comment' ? renderCommentActivity(a) : renderLogActivity(a)).join(''));
}

function renderLogActivity(a) {
    const icons = { 'created': '✅', 'priority_changed': '🔔', 'start_date_changed': '📅', 'start_date_removed': '📅', 'due_date_changed': '📅', 'due_date_removed': '📅', 'column_changed': '↔️', 'assignee_added': '👤', 'assignee_removed': '👤', 'label_added': '🏷️', 'label_removed': '🏷️', 'attachment_uploaded': '📎', 'attachment_deleted': '📎', 'link_added': '🔗', 'commented': '💬' };
    const icon = icons[a.activity_type] || '📝';
    return `<div class="flex gap-3"><div class="w-8 flex justify-center"><div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-600">${icon}</div></div><div class="flex-1"><div class="text-sm text-gray-700"><span class="font-medium">${a.user_name}</span> <span>${a.description}.</span> <span class="text-gray-500">${a.time_ago}</span></div></div></div>`;
}

function renderCommentActivity(a) {
    const initial = a.user_name.charAt(0).toUpperCase();
    return `<div class="flex gap-3"><div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center text-sm font-semibold shrink-0">${initial}</div><div class="flex-1 bg-purple-50 border border-purple-100 rounded-xl rounded-tl-none px-4 py-3"><div class="flex items-center justify-between gap-3"><span class="font-semibold text-gray-800 text-sm">${a.user_name}</span><span class="text-xs text-gray-400 shrink-0">${a.time_ago}</span></div><div class="mt-1.5 text-sm text-gray-700 leading-relaxed">${escapeHtml(a.comment)}</div></div></div>`;
}

/* ═══════════ Comments ═══════════ */
export function submitComment(config) {
    const comment = $('#drawerCommentInput').val().trim();
    if (!comment) { alert('Please enter a comment'); return; }
    if (!currentDrawerTaskId) { alert('No task selected'); return; }

    TaskApi.addComment(config.role, currentDrawerTaskId, comment).then(data => {
        if (data.success) {
            $('#drawerCommentInput').val('');
            loadTaskActivities(config);
        } else {
            alert('Failed to add comment: ' + (data.message || 'Unknown error'));
        }
    }).catch(err => { console.error(err); alert('Failed to add comment'); });
}

/* ═══════════ Description Auto-save ═══════════ */
let descriptionSaveTimeout;

export function autoSaveDescription(config) {
    const taskId = currentDrawerTaskId;
    if (!taskId) return;

    clearTimeout(descriptionSaveTimeout);
    descriptionSaveTimeout = setTimeout(() => {
        const description = $('#drawerTaskDescription').summernote('code');
        saveTaskDescription(config, taskId, description);
    }, 1000);
}

function saveTaskDescription(config, taskId, description) {
    TaskApi.updateField(config.role, taskId, { description }).then(data => {
        if (data.success) {
            updateLastEdited(data.last_edited_by, data.last_edited_at);
            if (currentDrawerTaskId && taskId == currentDrawerTaskId) {
                loadTaskActivities(config);
            }
        }
    }).catch(err => console.error('Error saving description:', err));
}

function fetchLastEditedInfo(config, taskId) {
    TaskApi.fetchTask(config.role, taskId).then(data => {
        if (data.success && data.task) {
            if (data.task.description !== undefined) {
                $('#drawerTaskDescription').summernote('code', data.task.description || '');
            }
            if (data.task.updated_by && data.task.updated_by.name && data.task.updated_at) {
                updateLastEdited(data.task.updated_by.name, data.task.updated_at);
            } else {
                $('#drawerLastEdited').text('');
            }
        }
    }).catch(err => console.error('Failed to fetch last edited info:', err));
}

function updateLastEdited(userName, timestamp) {
    if (!userName || !timestamp) return;
    $('#drawerLastEdited').text(`Last edited by ${userName} ${getTimeAgo(timestamp)}`);
}

/* ═══════════ Date field update helper (only used for dates now) ═══════════ */
function updateTaskFieldAndRefresh(config, taskId, field, value) {
    // Update UI for dates
    if (field === 'start_date' || field === 'due_date') {
        updateDateUI(taskId, field, value);
    }

    TaskApi.updateField(config.role, taskId, { [field]: value }).then(response => {
        if (response.success !== undefined && !response.success) {
            alert('Error: ' + (response.message || 'Update failed'));
            return;
        }
        
        // Update TaskStore with the latest data
        if (response && response.id) {
            TaskStore.set(response);
            // Update drawer if it's open
            updateDrawerIfOpen(response);
        } else {
            // If response doesn't return full task, patch the store
            TaskStore.patch(taskId, { [field]: value });
        }
        
        // Update modal if it's open for this task
        updateModalIfOpen(taskId, field, value);
        
        // Refresh activities if this task is open in the drawer
        if (currentDrawerTaskId && taskId == currentDrawerTaskId) {
            loadTaskActivities(config);
        }
    }).catch(err => console.error(err));
}

function updateDateUI(taskId, field, value) {
    const isDrawerTask = currentDrawerTaskId && String(currentDrawerTaskId) === String(taskId);
    
    // Update drawer date display
    if (isDrawerTask) {
        if (field === 'start_date') {
            $('#drawerStartDateDisplay').text(value ? formatDateDisplay(value) : 'Add start date');
        } else if (field === 'due_date') {
            $('#drawerDueDateDisplay').text(value ? formatDateDisplay(value) : 'Add due date');
        }
    }
    
    // Update card date display
    updateCardDateUI(taskId, field, value);
}

function updateCardDateUI(taskId, field, value) {
    const labelId = field === 'start_date' ? `startdate-label-${taskId}` : `duedate-label-${taskId}`;
    const label = document.getElementById(labelId);
    if (!label) return;
    
    if (value) {
        // Parse as local time: replace space with 'T' to avoid UTC interpretation
        const dateStr = value.replace(' ', 'T');
        const d = new Date(dateStr);
        label.textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } else {
        label.textContent = '';
    }
}

/**
 * Update drawer fields if drawer is currently open for the given task
 */
export function updateDrawerIfOpen(task) {
    if (!currentDrawerTaskId || String(currentDrawerTaskId) !== String(task.id)) return;
    
    console.log('Updating drawer for task:', task.id);
    
    // Update priority
    if (task.priority !== undefined) {
        const prioNameMap = { 'high': 'High', 'medium': 'Medium', 'low': 'Low', '': 'None', null: 'None', undefined: 'None' };
        updateDrawerPriorityDisplayComponent(task.priority ?? '');
    }
    
    // Update state/column
    if (task.column_id !== undefined) {
        updateDrawerStateDisplayComponent(task.column_id);
    }
    
    // Update assignees
    if (task.assignee_ids !== undefined) {
        updateDrawerAssigneeDisplayComponent(task.assignee_ids || []);
    }
    
    // Update labels
    if (task.label_ids !== undefined) {
        updateDrawerLabelsDisplayComponent(task.label_ids || []);
    }
}

/**
 * Update modal if it's open for this task (for dates)
 */
function updateModalIfOpen(taskId, field, value) {
    // Check if modal is open
    const taskModal = document.getElementById('taskModal');
    if (!taskModal || taskModal.classList.contains('hidden')) return;
    
    // Check if it's for the same task
    const currentTaskId = document.getElementById('task_id')?.value;
    if (!currentTaskId || String(currentTaskId) !== String(taskId)) return;
    
    console.log('Updating modal date for task:', taskId, 'field:', field, 'value:', value);
    
    // Update modal date fields
    if (field === 'start_date') {
        const startInput = document.getElementById('start_date');
        const startDisplay = document.getElementById('modalStartDateDisplay');
        if (startInput && value) {
            startInput.value = formatDatetimeLocal(value);
        } else if (startInput) {
            startInput.value = '';
        }
        if (startDisplay) {
            startDisplay.textContent = value ? formatDateDisplay(value) : 'Add start date';
        }
    } else if (field === 'due_date') {
        const dueInput = document.getElementById('due_date');
        const dueDisplay = document.getElementById('modalDueDateDisplay');
        if (dueInput && value) {
            dueInput.value = formatDatetimeLocal(value);
        } else if (dueInput) {
            dueInput.value = '';
        }
        if (dueDisplay) {
            dueDisplay.textContent = value ? formatDateDisplay(value) : 'Add due date';
        }
    }
}
