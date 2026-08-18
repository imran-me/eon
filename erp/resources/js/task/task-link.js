/**
 * Task Link Module — Add / Edit / Copy / Delete links in the drawer.
 */
import { TaskApi } from './task-api';
import { currentDrawerTaskId, loadTaskLinks, loadTaskActivities } from './task-drawer';

/* ── URL validation ── */
export function isValidUrl(str) {
    try {
        const u = new URL(str);
        return u.protocol === 'http:' || u.protocol === 'https:';
    } catch {
        return false;
    }
}

export function showLinkError(msg) {
    const el = document.getElementById('linkUrlError');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
}

export function hideLinkError() {
    const el = document.getElementById('linkUrlError');
    if (!el) return;
    el.classList.add('hidden');
}

/* ── Open link modal (add) ── */
export function openAddLinkModal(taskId = null) {
    document.getElementById('addLinkBackdrop')?.classList.remove('hidden');
    document.getElementById('addLinkModal')?.classList.remove('hidden');

    document.querySelector('#addLinkModal h3').textContent = 'Add link';
    document.querySelector('#linkbutton').textContent = 'Add link';

    const input = document.getElementById('linkTaskId');
    input.value = taskId ?? currentDrawerTaskId ?? '';
    document.getElementById('linkUrl').value = '';
    document.getElementById('linkTitle').value = '';

    delete input.dataset.linkId;
    delete input.dataset.isEdit;

    hideLinkError();
    setTimeout(() => document.getElementById('linkUrl')?.focus(), 50);
}

/* ── Open link modal (edit) ── */
export function openEditLinkModal(linkId, url, title) {
    document.getElementById('addLinkBackdrop')?.classList.remove('hidden');
    document.getElementById('addLinkModal')?.classList.remove('hidden');

    document.querySelector('#addLinkModal h3').textContent = 'Edit link';
    document.querySelector('#linkbutton').textContent = 'Save';

    const input = document.getElementById('linkTaskId');
    input.dataset.linkId = linkId;
    input.dataset.isEdit = 'true';

    document.getElementById('linkUrl').value = url || '';
    document.getElementById('linkTitle').value = title || '';
    hideLinkError();
}

/* ── Close link modal ── */
export function closeAddLinkModal() {
    document.getElementById('addLinkBackdrop')?.classList.add('hidden');
    document.getElementById('addLinkModal')?.classList.add('hidden');
    hideLinkError();
}

/* ── Submit (add or edit) ── */
export function submitAddLink(config) {
    const input = document.getElementById('linkTaskId');
    const taskId = input.value;
    const url = document.getElementById('linkUrl').value.trim();
    const title = document.getElementById('linkTitle').value.trim();
    const isEdit = input.dataset.isEdit === 'true';
    const linkId = input.dataset.linkId;

    hideLinkError();

    if (!isValidUrl(url)) {
        showLinkError('Please enter a valid http/https URL.');
        return;
    }

    const endpoint = isEdit
        ? `/${config.role}/tasks/links/${linkId}`
        : config.linkStoreUrl;
    const method = isEdit ? 'PUT' : 'POST';
    const body = { task_id: taskId, url, display_title: title || url };

    TaskApi.saveLink(endpoint, method, body)
        .then(data => {
            if (data.success) {
                const reloadTaskId = data.task_id || taskId || currentDrawerTaskId;
                if (data.total_links !== undefined) $('#drawerLinksCount').text(data.total_links);
                loadTaskLinks(config);
                loadTaskActivities(config);
                closeAddLinkModal();
            }
        })
        .catch(err => {
            showLinkError(err.message || `Failed to ${isEdit ? 'update' : 'add'} link`);
        });
}

/* ── Delete link ── */
export function deleteLink(config, linkId, taskId) {
    if (!confirm('Are you sure you want to delete this link?')) return;

    TaskApi.deleteLink(config.role, linkId).then(data => {
        if (data.success) {
            if (data.total_links !== undefined) $('#drawerLinksCount').text(data.total_links);
            $(`[data-link-id="${linkId}"]`).fadeOut(300, function () { $(this).remove(); });
            loadTaskActivities(config);
        }
    }).catch(err => { console.error(err); alert('Failed to delete link'); });
}

/* ── Copy link ── */
export function copyLinkToClipboard(url) {
    // Check if Clipboard API is available
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            // Could show a toast / tooltip
        }).catch(err => console.error('Copy failed:', err));
    } else {
        // Fallback for browsers that don't support Clipboard API
        const textArea = document.createElement('textarea');
        textArea.value = url;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            textArea.remove();
        } catch (err) {
            console.error('Fallback copy failed:', err);
            textArea.remove();
        }
    }
}
