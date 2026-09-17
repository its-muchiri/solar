/**
 * Deposit / final payment page logic.
 *
 * ENFORCED RULE (per artcollect-design-system.md §7 and this platform's
 * README): this file, and any mis-sizing-dispute page, must NEVER import
 * components/scrap.js or any other decorative module.
 */
import { createStatusTimeline, SOLAR_INSTALLATION_STEPS } from "../components/status-timeline.js";

// import { createTornEdge } from "../components/scrap.js"; // <- NEVER do this here.

export function renderInstallationStatus(container, currentIndex) {
  container.classList.add("critical-flow");
  container.appendChild(createStatusTimeline({ steps: SOLAR_INSTALLATION_STEPS, currentIndex }));
}
