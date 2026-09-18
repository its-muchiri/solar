<?php
/** @var array $installer  a row from Solar\Models\Installer::search() */
use Solar\Core\View;

$rating = $installer['avg_rating'];
$installs = $installer['completed_installs'];
?>
<article class="card installer-card">
  <img class="installer-card__photo" src="<?= View::e($installer['photo_url']) ?>" alt="" loading="lazy" width="640" height="400">
  <div class="installer-card__body">
    <h3><a href="/installers/<?= $installer['id'] ?>" class="installer-card__link"><?= View::e($installer['full_name']) ?></a></h3>
    <p class="installer-card__stats">
      <?php if ($rating !== null): ?>
        <span aria-label="Rated <?= $rating ?> out of 5 from <?= $installer['review_count'] ?> review<?= $installer['review_count'] === 1 ? '' : 's' ?>"><strong>★ <?= number_format($rating, 1) ?></strong> (<?= $installer['review_count'] ?>)</span>
      <?php else: ?>
        <span>No reviews yet</span>
      <?php endif; ?>
      <span aria-hidden="true">·</span>
      <span><?= $installs ?> completed install<?= $installs === 1 ? '' : 's' ?></span>
    </p>
    <div class="cert-list">
      <?php foreach ($installer['certifications'] as $cert): ?>
        <?php if ($cert['status'] === 'expired') { continue; } ?>
        <span class="cert-badge" data-tone="<?= $cert['status'] === 'valid' ? 'success' : 'warning' ?>">
          <?= View::e($cert['label']) ?><?= $cert['status'] === 'expiring_soon' ? ' — renewal due' : '' ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
</article>
