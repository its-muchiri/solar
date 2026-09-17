/**
 * System-sizing calculator form — submits an appliance profile to
 * POST /api/v1/sizing-calculations (see src/Controllers/SizingController.php,
 * which returns HTTP 501 until the sizing methodology is implemented — see
 * that controller's docblock and planning/04-solar-co-ke/open-questions.md #1).
 * This component only handles the form UI and calls the endpoint; it does
 * not perform any sizing math client-side.
 *
 * @param {{ onResult: (result: object) => void, onError: (message: string) => void }} props
 * @returns {HTMLElement}
 */
export function createSizingForm({ onResult, onError }) {
  const form = document.createElement("form");
  form.className = "sizing-form";
  form.innerHTML = `
    <label>
      Desired backup duration (hours)
      <input type="number" name="desired_backup_duration_hours" min="0" step="0.5" required>
    </label>
    <button type="submit" class="btn btn--primary">Calculate recommended system size</button>
  `;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(form);

    try {
      const response = await fetch("/api/v1/sizing-calculations", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          desired_backup_duration_hours: Number(formData.get("desired_backup_duration_hours")),
          appliance_profile: [], // TODO: build a proper appliance-entry UI
        }),
      });

      const data = await response.json();
      if (!response.ok) {
        onError(data.error ?? "Sizing calculation failed");
        return;
      }
      onResult(data);
    } catch {
      onError("Network error — please try again");
    }
  });

  return form;
}
