<?php
/** @var array $installers */
/** @var string|null $dbError */
use Solar\Core\View;
?>
<section style="padding-block: var(--ac-space-8) var(--ac-space-12);">
  <h1 style="font-size: 2.5rem; max-width: 32rem;">Size, install, and maintain your solar system.</h1>
  <p style="max-width: var(--ac-measure); margin-block: var(--ac-space-4);">
    Find out what system size fits your household or business, get quotes from certified installers, and track your installation from survey to commissioning.
  </p>
  <div style="display:flex; gap: var(--ac-space-3);">
    <a href="/sizing" class="btn btn--primary">Size my system</a>
    <a href="/installers" class="btn btn--secondary">Browse installers</a>
  </div>
</section>

<section style="padding-block: var(--ac-space-8); border-block: 1px solid var(--ac-paper-deep);">
  <h2>How it works</h2>
  <div style="display:flex; flex-wrap:wrap; gap: var(--ac-space-6); margin-top: var(--ac-space-4);">
    <div style="flex: 1 1 12rem;">
      <strong>1. Size</strong>
      <p class="card__meta">Tell us your appliances and backup needs for a recommended system size.</p>
    </div>
    <div style="flex: 1 1 12rem;">
      <strong>2. Quote</strong>
      <p class="card__meta">Certified installers quote panel, battery, and inverter capacity for your site.</p>
    </div>
    <div style="flex: 1 1 12rem;">
      <strong>3. Install</strong>
      <p class="card__meta">Track site survey, installation, and commissioning through to sign-off.</p>
    </div>
  </div>
</section>

<section style="padding-block: var(--ac-space-8);">
  <h2>Certified installers</h2>
  <?php if ($dbError): ?>
    <p class="card__meta"><?= View::e($dbError) ?></p>
  <?php elseif (empty($installers)): ?>
    <p class="card__meta">No verified installers are listed yet. Installers appear here once their KYC and certification have been approved.</p>
  <?php else: ?>
    <div class="installer-grid">
      <?php foreach ($installers as $installer): ?>
        <?= View::partial('installer-card', ['installer' => $installer]) ?>
      <?php endforeach; ?>
    </div>
    <p style="margin-top: var(--ac-space-4);"><a href="/installers" class="btn btn--secondary">See all installers</a></p>
  <?php endif; ?>
</section>
