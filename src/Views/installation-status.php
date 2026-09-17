<?php
/** @var array|null $booking */
/** @var int $bookingId */
/** @var array $warranty */
/** @var string|null $dbError */
use Solar\Core\View;
?>
<h1>Installation #<?= $bookingId ?></h1>

<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (!$booking): ?>
  <p class="card__meta">No installation found with this ID.</p>
<?php else: ?>
  <p class="card__meta"><?= View::e($booking['site_address']) ?></p>
  <?php if ((float) $booking['total_contract_value'] > 0): ?>
    <p>Contract value: KES <?= number_format((float) $booking['total_contract_value']) ?></p>
  <?php endif; ?>

  <div id="status-badge-container" style="margin-top: var(--ac-space-2);"></div>
  <div id="status-timeline-container" style="max-width: 48rem; margin-top: var(--ac-space-4);"></div>

  <?php if (!empty($warranty)): ?>
    <h3 style="margin-top: var(--ac-space-6);">Warranty</h3>
    <ul>
      <?php foreach ($warranty as $w): ?>
        <li><?= View::e(ucfirst($w['component'])) ?> — <?= View::e(ucfirst(str_replace('_', ' ', $w['warranty_provider']))) ?> warranty until <?= View::e($w['warranty_end_date']) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <script type="module">
    import { createStatusTimeline, SOLAR_INSTALLATION_STEPS } from "/assets/js/components/status-timeline.js";
    import { createStatusBadge } from "/assets/js/components/status-badge.js";

    const status = <?= json_encode($booking['status']) ?>;
    const STEP_INDEX = {
      open_for_quotes: 0, quote_accepted: 1, site_survey_scheduled: 2, site_survey_complete: 3,
      installation_scheduled: 4, installing: 5, commissioning: 6, awaiting_signoff: 7, completed: 8,
    };
    const BADGE_TONE = {
      open_for_quotes: "neutral", quote_accepted: "accent", installing: "warning",
      completed: "success", cancelled: "danger", disputed: "danger",
    };

    document.getElementById("status-badge-container").appendChild(
      createStatusBadge({ label: status.replace(/_/g, " "), tone: BADGE_TONE[status] ?? "accent" })
    );

    if (STEP_INDEX[status] !== undefined) {
      document.getElementById("status-timeline-container").appendChild(
        createStatusTimeline({ steps: SOLAR_INSTALLATION_STEPS, currentIndex: STEP_INDEX[status] })
      );
    }
  </script>
<?php endif; ?>
