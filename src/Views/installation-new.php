<?php
/** @var string $prefillSizingId */
use Solar\Core\View;
?>
<h1>Book an installation</h1>
<?php if ($prefillSizingId): ?>
  <p class="card__meta">Linked to sizing calculation #<?= View::e($prefillSizingId) ?>.</p>
<?php endif; ?>

<form id="installation-form" style="max-width: 32rem; display:flex; flex-direction:column; gap: var(--ac-space-4); margin-top: var(--ac-space-4);">
  <input type="hidden" name="sizing_calculation_id" value="<?= View::e($prefillSizingId) ?>">

  <label>
    Site address
    <input type="text" name="site_address" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
  </label>

  <button type="submit" class="btn btn--primary">Request installation</button>
</form>

<p id="installation-result" class="card__meta" style="margin-top: var(--ac-space-4);"></p>

<script type="module">
  document.getElementById("installation-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);
    const resultEl = document.getElementById("installation-result");

    try {
      const res = await fetch("/api/v1/installations", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          site_address: formData.get("site_address"),
          site_lat: 0,
          site_lng: 0,
          sizing_calculation_id: formData.get("sizing_calculation_id") || null,
        }),
      });
      const data = await res.json();

      if (!res.ok) {
        // Expected right now: no auth middleware exists yet, so customer_id
        // resolves to null and the database rejects the insert. See
        // src/Controllers/InstallationController.php.
        resultEl.textContent = "Request failed: " + (data.error || "unknown error") + " — expected until auth middleware and a live database are wired up.";
        return;
      }

      resultEl.innerHTML = `Installation #${data.id} requested (status: ${data.status}). <a href="/installations/${data.id}">Track it</a>`;
    } catch (e) {
      resultEl.textContent = "Network error: " + e.message;
    }
  });
</script>
