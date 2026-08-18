const map = new Map();

export const TaskStore = {
  set(task) {
    map.set(String(task.id), task);
  },
  get(taskId) {
    return map.get(String(taskId));
  },
  patch(taskId, changes) {
    const t = this.get(taskId);
    if (!t) return;
    this.set({ ...t, ...changes });
  }
};