<h1>System sizing calculator</h1>
<p class="card__meta">List the appliances you want to run, your desired backup duration, and (optionally) your budget — we'll recommend a panel, battery, and inverter size independent of any installer's quote. Use this as your reference point: a quote that deviates significantly from this recommendation is worth asking about.</p>
<p class="card__meta">This is an estimate based on standard sizing methodology, not a substitute for a site survey — your installer's post-survey recommendation may refine it.</p>

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
      resultEl.innerHTML = `<p class="sizing-form__result" style="margin-top: var(--ac-space-4); color: var(--ac-danger);">${message}</p>`;
    },
  });

  document.getElementById("sizing-form-container").appendChild(form);
</script>
