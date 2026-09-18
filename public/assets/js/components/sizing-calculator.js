import { authHeaders, getUser } from "/assets/js/lib/auth-session.js";

/**
 * System-sizing calculator form — collects the customer's appliance/energy
 * profile, desired backup duration, and optional budget range, then submits
 * it to POST /api/v1/sizing-calculations (see
 * src/Controllers/SizingController.php for the sizing methodology). This
 * component only handles form UI/validation; all sizing math happens
 * server-side so it stays independent of any installer's quote (see
 * planning/04-solar-co-ke/prd.md Core Feature 1).
 *
 * @param {{ onResult: (result: object) => void, onError: (message: string) => void }} props
 * @returns {HTMLElement}
 */
export function createSizingForm({ onResult, onError }) {
  const form = document.createElement("form");
  form.className = "sizing-form";

  const applianceList = document.createElement("div");
  applianceList.className = "sizing-form__appliances";

  const applianceRowTemplate = () => {
    const row = document.createElement("div");
    row.className = "sizing-form__appliance-row";
    row.style.cssText = "display:flex; gap: var(--ac-space-2); align-items:flex-end; flex-wrap:wrap;";
    row.innerHTML = `
      <label style="flex: 2 1 10rem;">
        Appliance
        <input type="text" name="appliance_name" placeholder="e.g. Fridge" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
      </label>
      <label style="flex: 1 1 7rem;">
        Watts
        <input type="number" name="appliance_watts" min="1" step="1" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
      </label>
      <label style="flex: 1 1 7rem;">
        Hours/day
        <input type="number" name="appliance_hours" min="0.5" max="24" step="0.5" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
      </label>
      <button type="button" class="btn btn--secondary sizing-form__remove-row" aria-label="Remove appliance">Remove</button>
    `;
    row.querySelector(".sizing-form__remove-row").addEventListener("click", () => {
      if (applianceList.children.length > 1) {
        row.remove();
      }
    });
    return row;
  };

  applianceList.appendChild(applianceRowTemplate());

  const addRowBtn = document.createElement("button");
  addRowBtn.type = "button";
  addRowBtn.className = "btn btn--secondary";
  addRowBtn.textContent = "+ Add another appliance";
  addRowBtn.addEventListener("click", () => {
    applianceList.appendChild(applianceRowTemplate());
  });

  const restOfForm = document.createElement("div");
  restOfForm.style.cssText = "display:flex; flex-direction:column; gap: var(--ac-space-4);";
  restOfForm.innerHTML = `
    <label>
      Desired backup duration (hours)
      <input type="number" name="desired_backup_duration_hours" min="0.5" step="0.5" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
    </label>
    <fieldset style="border:none; padding:0; display:flex; gap: var(--ac-space-3);">
      <label style="flex:1;">
        Budget min (KES, optional)
        <input type="number" name="budget_range_min" min="0" step="1000" style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
      </label>
      <label style="flex:1;">
        Budget max (KES, optional)
        <input type="number" name="budget_range_max" min="0" step="1000" style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
      </label>
    </fieldset>
    <button type="submit" class="btn btn--primary">Calculate recommended system size</button>
  `;

  form.appendChild(document.createElement("hr"));
  const applianceHeading = document.createElement("strong");
  applianceHeading.textContent = "Your appliances";
  form.prepend(applianceHeading);
  form.appendChild(applianceList);
  form.appendChild(addRowBtn);
  form.appendChild(restOfForm);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!getUser()) {
      onError('Sign in first to save your sizing result to your account — <a href="/login?next=/sizing">log in</a> or <a href="/signup">sign up</a>.');
      return;
    }

    const applianceProfile = Array.from(applianceList.querySelectorAll(".sizing-form__appliance-row")).map((row) => ({
      appliance: row.querySelector('[name="appliance_name"]').value,
      watts: Number(row.querySelector('[name="appliance_watts"]').value),
      hours_per_day: Number(row.querySelector('[name="appliance_hours"]').value),
    }));

    const backupHoursInput = restOfForm.querySelector('[name="desired_backup_duration_hours"]');
    const budgetMinInput = restOfForm.querySelector('[name="budget_range_min"]');
    const budgetMaxInput = restOfForm.querySelector('[name="budget_range_max"]');

    try {
      const response = await fetch("/api/v1/sizing-calculations", {
        method: "POST",
        headers: { "Content-Type": "application/json", ...authHeaders() },
        body: JSON.stringify({
          appliance_profile: applianceProfile,
          desired_backup_duration_hours: Number(backupHoursInput.value),
          budget_range_min: budgetMinInput.value ? Number(budgetMinInput.value) : null,
          budget_range_max: budgetMaxInput.value ? Number(budgetMaxInput.value) : null,
        }),
      });

      const data = await response.json();
      if (!response.ok) {
        onError(data.error ? `${data.error}${data.details?.reason ? ': ' + data.details.reason : ''}` : "Sizing calculation failed");
        return;
      }
      onResult(data);
    } catch {
      onError("Network error — please try again");
    }
  });

  return form;
}
