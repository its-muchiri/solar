<h1>System sizing calculator</h1>
<p class="card__meta">Tell us your desired backup duration and we'll recommend a panel, battery, and inverter size.</p>

<div id="sizing-form-container" style="margin-top: var(--ac-space-4);"></div>
<div id="sizing-result"></div>

<script type="module">
  import { createSizingForm } from "/assets/js/components/sizing-calculator.js";

  const resultEl = document.getElementById("sizing-result");

  const form = createSizingForm({
    onResult: (data) => {
      resultEl.innerHTML = `
        <div class="sizing-form__result" style="margin-top: var(--ac-space-4);">
          <strong>Recommended system</strong>
          <p>${data.recommended_panel_capacity_kw} kW panels · ${data.recommended_battery_capacity_kwh} kWh battery · ${data.recommended_inverter_rating_kw} kW inverter</p>
          <p>Estimated cost: KES ${data.estimated_cost_range_min?.toLocaleString?.() ?? data.estimated_cost_range_min} – ${data.estimated_cost_range_max?.toLocaleString?.() ?? data.estimated_cost_range_max}</p>
          <a href="/installations/new?sizing_calculation_id=${data.id}" class="btn btn--primary">Book an installation</a>
        </div>
      `;
    },
    onError: (message) => {
      // Expected right now: SizingController::calculate() intentionally
      // throws until a validated sizing methodology is designed by someone
      // with solar-engineering domain expertise — see that controller's
      // docblock. This is the real, current state of the app, not a bug.
      resultEl.innerHTML = `<p class="sizing-form__result" style="margin-top: var(--ac-space-4); color: var(--ac-danger);">${message} — this is expected: the sizing methodology is intentionally unimplemented pending domain-expert validation (see src/Controllers/SizingController.php).</p>`;
    },
  });

  document.getElementById("sizing-form-container").appendChild(form);
</script>
