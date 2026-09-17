/**
 * Status Timeline — shared component. This platform's lifecycle is defined
 * in planning/04-solar-co-ke/database-schema.md's solar_bookings.status enum.
 * @param {{ steps: string[], currentIndex: number }} props
 * @returns {HTMLElement}
 */
export function createStatusTimeline({ steps, currentIndex }) {
  const list = document.createElement("ol");
  list.className = "status-timeline";

  steps.forEach((label, index) => {
    const item = document.createElement("li");
    item.className = "status-timeline__step" + (index <= currentIndex ? " status-timeline__step--done" : "");
    item.textContent = label;
    list.appendChild(item);
  });

  return list;
}

export const SOLAR_INSTALLATION_STEPS = [
  "Open for quotes",
  "Quote accepted",
  "Site survey scheduled",
  "Site survey complete",
  "Installation scheduled",
  "Installing",
  "Commissioning",
  "Awaiting sign-off",
  "Completed",
];
