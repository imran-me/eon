import { TaskApi } from "./task-api";
import { TaskStore } from "./task-store";
import { TaskUI } from "./task-ui";

export const TaskController = {
  async handleDropdownChange(detail) {
    const { type, taskId, dropdownId, multi, payload } = detail;

    // Draft mode (modal create) if no taskId
    if (!taskId) {
      // later: DraftTask support
      return;
    }

    try {
      let updatePayload = {};

      if (!multi) {
        // Single dropdown
        if (type === "priority") updatePayload = { priority: payload.value || null };
        if (type === "state") updatePayload = { column_id: Number(payload.value) };

        const updated = await TaskApi.update(taskId, updatePayload);
        TaskStore.set(updated);

        // Update label quickly (optional)
        // label text is already set by engine; renderCard will sync other areas
        TaskUI.renderCard(updated);
        return;
      }

      // Multi dropdown
      if (type === "assignees") updatePayload = { assignee_ids: payload.selectedValues.map(Number) };
      if (type === "labels") updatePayload = { label_ids: payload.selectedValues.map(Number) };

      const updated = await TaskApi.update(taskId, updatePayload);
      TaskStore.set(updated);
      TaskUI.renderCard(updated);

    } catch (err) {
      console.error(err);
      // optional: show toast
    }
  }
};