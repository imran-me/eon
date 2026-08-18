/**
 * Task API — All fetch calls to the backend.
 * Every function returns a Promise that resolves to JSON.
 */

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]').content;
}

function headers(json = true) {
  const h = {
    'Accept': 'application/json',
    'X-CSRF-TOKEN': csrfToken(),
  };
  if (json) h['Content-Type'] = 'application/json';
  return h;
}

export const TaskApi = {

  /* ── Generic task update (PUT via POST with _method) ── */
  async updateField(role, taskId, payload) {
    const res = await fetch(`/${role}/tasks/${taskId}`, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ _method: 'PUT', ...payload }),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || 'Task update failed');
    }
    return res.json();
  },

  /* ── Create or update a task (save modal) ── */
  async save(role, { id, ...payload }) {
    const url = id ? `/${role}/tasks/${id}` : `/${role}/tasks`;
    const res = await fetch(url, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ _method: id ? 'PUT' : 'POST', ...payload }),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || 'Failed to save task');
    }
    return res.json();
  },

  /* ── Delete a task (soft-delete) ── */
  async deleteTask(role, taskId) {
    const res = await fetch(`/${role}/tasks/${taskId}`, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ _method: 'DELETE' }),
    });
    return res.json();
  },

  async copyTask(role, taskId) {
    const res = await fetch(`/${role}/tasks/${taskId}/copy`, {
      method: 'POST',
      headers: headers(),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || 'Task copy failed');
    }
    return res.json();
  },

  /* ── Move (drag-and-drop) ── */
  async move(moveUrl, taskId, columnId, position) {
    return fetch(moveUrl, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ task_id: taskId, column_id: columnId, position }),
    });
  },

  /* ── Fetch single task (for "last edited" info) ── */
  async fetchTask(role, taskId) {
    const res = await fetch(`/${role}/tasks/${taskId}`, {
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    });
    return res.json();
  },

  /* ══════════════ Links ══════════════ */
  async getLinks(role, taskId) {
    const res = await fetch(`/${role}/tasks/${taskId}/links`, { headers: headers(false) });
    return res.json();
  },

  async saveLink(endpoint, method, body) {
    const res = await fetch(endpoint, {
      method,
      headers: headers(),
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || 'Link operation failed');
    }
    return res.json();
  },

  async deleteLink(role, linkId) {
    const res = await fetch(`/${role}/tasks/links/${linkId}`, {
      method: 'DELETE',
      headers: headers(false),
    });
    return res.json();
  },

  /* ══════════════ Attachments ══════════════ */
  async getAttachments(role, taskId) {
    const res = await fetch(`/${role}/tasks/${taskId}/attachments`, { headers: headers(false) });
    if (!res.ok) throw new Error('Failed to load attachments');
    return res.json();
  },

  async uploadAttachment(uploadUrl, file, taskId) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('task_id', taskId);

    const res = await fetch(uploadUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken() },
      body: formData,
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.message || `Server error: ${res.status}`);
    }
    return res.json();
  },

  async deleteAttachment(role, attachmentId) {
    const res = await fetch(`/${role}/tasks/attachments/${attachmentId}`, {
      method: 'DELETE',
      headers: headers(false),
    });
    if (!res.ok) throw new Error('Failed to delete');
    return res.json();
  },

  /* ══════════════ Activities & Comments ══════════════ */
  async getActivities(role, taskId) {
    const res = await fetch(`/${role}/tasks/${taskId}/activities`, { headers: headers(false) });
    return res.json();
  },

  async addComment(role, taskId, comment) {
    const res = await fetch(`/${role}/tasks/comments`, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ task_id: taskId, comment }),
    });
    return res.json();
  },

  /* ══════════════ Sub-work items ══════════════ */
  async getSubtasks(role, taskId) {
    const res = await fetch(`/${role}/tasks/${taskId}/subtasks`, { headers: headers(false) });
    return res.json();
  },

  async createSubtask(role, parentId, payload) {
    const res = await fetch(`/${role}/tasks/${parentId}/subtasks`, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, ...data };
  },

  async setParent(role, taskId, parentId) {
    const res = await fetch(`/${role}/tasks/${taskId}/set-parent`, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({ parent_id: parentId }),
    });
    return res.json();
  },

  async searchTasks(role, params) {
    const res = await fetch(`/${role}/tasks/search?${new URLSearchParams(params).toString()}`, {
      headers: headers(false),
    });
    return res.json();
  },
};