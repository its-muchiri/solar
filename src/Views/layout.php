<?php
/**
 * Global layout — header/nav/footer per planning/00-portfolio/design-system.md
 * §Global layout and artcollect-design-system.md §6. `$content` and
 * optional `$title` are provided by View::render(). `$critical` (bool),
 * when set true by a page, marks <main> with the `.critical-flow` class —
 * see artcollect-design-system.md §7: deposit/final payment and
 * mis-sizing-dispute screens carry zero decoration.
 */
$pageTitle = isset($title) ? $title . ' — solar.co.ke' : 'solar.co.ke';
$isCritical = $critical ?? false;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
  <header class="site-header container">
    <a href="/" style="text-decoration:none;color:inherit;"><strong>solar.co.ke</strong></a>
    <nav aria-label="Primary" style="display:flex; gap: var(--ac-space-3);">
      <a href="/installers" class="btn btn--secondary">Browse installers</a>
      <a href="/sizing" class="btn btn--primary">Size my system</a>
    </nav>
  </header>

  <main class="container<?= $isCritical ? ' critical-flow' : '' ?>" style="padding-block: var(--ac-space-8);">
    <?= $content ?>
  </main>

  <footer class="site-footer container">
    <p class="card__meta">&copy; <?= date('Y') ?> solar.co.ke — part of the artcollect.co.ke network. <a href="/showcase.html">Component showcase</a></p>
  </footer>

  <script type="module" src="/assets/js/main.js"></script>
</body>
</html>
