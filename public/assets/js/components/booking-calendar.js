/**
 * Booking Calendar — single-slot mode, for site-survey appointments (see
 * planning/00-portfolio/design-system.md's single-slot vs. date-range
 * configuration of the shared component).
 *
 * @param {{ slots: { id: string, label: string, available: boolean }[], onSelect: (slotId: string) => void }} props
 * @returns {HTMLElement}
 */
export function createSlotPicker({ slots, onSelect }) {
  const grid = document.createElement("div");
  grid.className = "slot-picker";
  grid.setAttribute("role", "listbox");
  grid.setAttribute("aria-label", "Available site-survey slots");

  slots.forEach((slot) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "slot-picker__slot";
    button.textContent = slot.label;
    button.setAttribute("role", "option");
    button.setAttribute("aria-disabled", String(!slot.available));
    button.setAttribute("aria-selected", "false");
    button.disabled = !slot.available;

    button.addEventListener("click", () => {
      grid.querySelectorAll('[aria-selected="true"]').forEach((el) => el.setAttribute("aria-selected", "false"));
      button.setAttribute("aria-selected", "true");
      onSelect(slot.id);
    });

    grid.appendChild(button);
  });

  return grid;
}
