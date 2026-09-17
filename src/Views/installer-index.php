<?php
/** @var array $installers */
/** @var string|null $dbError */
use Solar\Core\View;
?>
<h1>Browse installers</h1>

<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (empty($installers)): ?>
  <p class="card__meta">No installers are onboarded yet in this environment.</p>
<?php else: ?>
  <div style="display:flex; flex-wrap:wrap; gap: var(--ac-space-4); margin-top: var(--ac-space-4);">
    <?php foreach ($installers as $installer): ?>
      <div class="card" style="width: 16rem;">
        <h3><?= View::e($installer['full_name']) ?></h3>
        <div class="card__meta">
          <?= (int) $installer['valid_certifications'] ?> valid certification<?= (int) $installer['valid_certifications'] === 1 ? '' : 's' ?>
        </div>
        <a href="/installers/<?= (int) $installer['id'] ?>" class="btn btn--secondary" style="margin-top: var(--ac-space-3);">View profile</a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
