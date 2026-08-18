export const TaskUI = {
  // Update the label on the dropdown toggle itself
  setDropdownLabel(dropdownId, text) {
    const root = document.querySelector(`[data-dropdown-id="${dropdownId}"]`);
    if (!root) return;
    const label = root.querySelector('[data-role="dropdown-label"]');
    if (label) label.textContent = text;
  },

  // You can implement these as you already have card DOM ids
  // Start with placeholders, then wire to your existing update functions
  renderCard(task) {
    // TODO: update priority badge, labels chips, assignee avatars, etc.
    // Example:
    // this.renderPriority(task);
    // this.renderAssignees(task);
    // this.renderLabels(task);
  },
};