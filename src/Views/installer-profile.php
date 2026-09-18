<?php
/** @var array|null $installer */
/** @var int $installerId */
/** @var string|null $dbError */
use Solar\Core\Photos;
use Solar\Core\View;

$stars = static fn (int $n): string => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
$certTones = ['valid' => 'success', 'expiring_soon' => 'warning', 'expired' => 'danger'];
?>
<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (!$installer): ?>
  <h1>Installer not found</h1>
  <p class="card__meta">This installer doesn't exist or isn't currently listed. <a href="/installers">Browse verified installers</a>.</p>
<?php else: ?>
  <img class="profile-cover" src="<?= View::e(Photos::installerCover($installer['id'], 1200)) ?>" alt="" width="1200" height="750">
  <h1><?= View::e($installer['full_name']) ?></h1>
  <p class="installer-card__stats">
    <?php if ($installer['avg_rating'] !== null): ?>
      <span><strong>★ <?= number_format($installer['avg_rating'], 1) ?></strong> (<?= $installer['review_count'] ?> review<?= $installer['review_count'] === 1 ? '' : 's' ?>)</span>
    <?php else: ?>
      <span>No reviews yet</span>
    <?php endif; ?>
    <span aria-hidden="true">·</span>
    <span><?= $installer['completed_installs'] ?> completed install<?= $installer['completed_installs'] === 1 ? '' : 's' ?></span>
  </p>

  <h2 style="margin-top: var(--ac-space-8);">Certifications</h2>
  <?php if (empty($installer['certifications'])): ?>
    <p class="card__meta">No certifications on file.</p>
  <?php else: ?>
    <ul class="cert-table">
      <?php foreach ($installer['certifications'] as $cert): ?>
        <li>
          <span class="cert-badge" data-tone="<?= $certTones[$cert['status']] ?>">
            <?= View::e($cert['label']) ?> — <?= str_replace('_', ' ', $cert['status']) ?>
          </span>
          <span class="card__meta">expires <?= View::e($cert['expires_at']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h2 style="margin-top: var(--ac-space-8);">Customer reviews</h2>
  <?php if (empty($installer['reviews'])): ?>
    <p class="card__meta">No reviews yet — reviews are left by customers after a completed installation.</p>
  <?php else: ?>
    <ul class="review-list">
      <?php foreach ($installer['reviews'] as $review): ?>
        <li class="card">
          <div><span aria-label="<?= $review['rating'] ?> out of 5"><?= $stars($review['rating']) ?></span> <strong><?= View::e($review['reviewer_name']) ?></strong></div>
          <?php if ($review['comment']): ?><p><?= View::e($review['comment']) ?></p><?php endif; ?>
          <div class="card__meta"><?= View::e(substr($review['created_at'], 0, 10)) ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div id="sticky-cta-container"></div>

  <script type="module">
    import { createStickyCta } from "/assets/js/components/sticky-cta.js";

    document.getElementById("sticky-cta-container").appendChild(
      createStickyCta({ label: "Book an installation", onClick: () => { window.location.href = "/installations/new"; } })
    );
  </script>
<?php endif; ?>
