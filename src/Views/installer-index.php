<?php
/** @var array $installers */
/** @var array $filters */
/** @var string|null $filterError */
/** @var string|null $dbError */
use Solar\Core\View;
use Solar\Models\Installer;

$hasFilters = $filters['certification'] !== null || $filters['min_rating'] > 0 || $filters['min_installs'] > 0;
?>
<h1>Browse installers</h1>
<p class="card__meta" style="max-width: var(--ac-measure); margin-top: var(--ac-space-2);">
  Every installer listed here has passed identity and business KYC and holds a current, unexpired certification.
</p>

<form method="get" action="/installers" class="filter-bar" aria-label="Filter installers">
  <label>
    Certification
    <select name="certification">
      <option value="">Any</option>
      <?php foreach (Installer::CERTIFICATION_TYPES as $key => $label): ?>
        <option value="<?= View::e($key) ?>"<?= $filters['certification'] === $key ? ' selected' : '' ?>><?= View::e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>
    Minimum rating
    <select name="min_rating">
      <?php foreach (['0' => 'Any', '3' => '3.0 and up', '4' => '4.0 and up', '4.5' => '4.5 and up'] as $value => $label): ?>
        <option value="<?= $value ?>"<?= (float) $value === $filters['min_rating'] ? ' selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>
    Completed installs
    <select name="min_installs">
      <?php foreach (['0' => 'Any', '1' => '1 or more', '5' => '5 or more', '10' => '10 or more'] as $value => $label): ?>
        <option value="<?= $value ?>"<?= (int) $value === $filters['min_installs'] ? ' selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>
    Sort by
    <select name="sort">
      <?php foreach (['rating' => 'Highest rated', 'installs' => 'Most installs', 'name' => 'Name (A–Z)'] as $value => $label): ?>
        <option value="<?= $value ?>"<?= $filters['sort'] === $value ? ' selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="filter-bar__actions">
    <button type="submit" class="btn btn--primary">Apply</button>
    <?php if ($hasFilters): ?><a href="/installers" class="btn btn--secondary">Clear</a><?php endif; ?>
  </div>
</form>

<?php if ($filterError): ?>
  <p class="notice notice--warning" role="alert"><?= View::e($filterError) ?></p>
<?php endif; ?>

<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (empty($installers)): ?>
  <div class="notice" role="status">
    <?php if ($hasFilters): ?>
      <strong>No installers match those filters.</strong>
      <p>Try a lower rating or install count, or <a href="/installers">clear the filters</a>.</p>
    <?php else: ?>
      <strong>No verified installers are listed yet.</strong>
      <p>Installers appear here once their KYC and certification have been approved. You can still <a href="/sizing">size your system</a> now and request quotes when installers are available.</p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <p class="card__meta" style="margin-top: var(--ac-space-4);"><?= count($installers) ?> installer<?= count($installers) === 1 ? '' : 's' ?></p>
  <div class="installer-grid">
    <?php foreach ($installers as $installer): ?>
      <?= View::partial('installer-card', ['installer' => $installer]) ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
