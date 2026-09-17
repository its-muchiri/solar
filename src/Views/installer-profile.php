<?php
/** @var array|null $installer */
/** @var int $installerId */
/** @var string|null $dbError */
use Solar\Core\View;
?>
<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (!$installer): ?>
  <h1>Installer #<?= $installerId ?></h1>
  <p class="card__meta">No installer found with this ID.</p>
<?php else: ?>
  <h1><?= View::e($installer['full_name']) ?></h1>

  <h3>Certifications</h3>
  <?php if (empty($installer['certifications'])): ?>
    <p class="card__meta">No certifications on file.</p>
  <?php else: ?>
    <div id="cert-badges" style="display:flex; flex-wrap:wrap; gap: var(--ac-space-2); margin-bottom: var(--ac-space-4);"></div>
  <?php endif; ?>

  <div id="sticky-cta-container"></div>

  <script type="module">
    import { createStatusBadge } from "/assets/js/components/status-badge.js";
    import { createStickyCta } from "/assets/js/components/sticky-cta.js";

    const certifications = <?= json_encode($installer['certifications']) ?>;
    const TONE = { valid: "success", expiring_soon: "warning", expired: "danger" };
    const container = document.getElementById("cert-badges");
    if (container) {
      certifications.forEach((cert) => {
        container.appendChild(createStatusBadge({
          label: `${cert.certification_type} (${cert.status.replace(/_/g, " ")})`,
          tone: TONE[cert.status] ?? "neutral",
        }));
      });
    }

    document.getElementById("sticky-cta-container").appendChild(
      createStickyCta({ label: "Book an installation", onClick: () => { window.location.href = "/installations/new"; } })
    );
  </script>
<?php endif; ?>
