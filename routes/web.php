<?php

/**
 * Server-rendered page routes (see src/Views/ and src/Core/View.php) —
 * distinct from routes/api.php's JSON API. Covers the primary customer
 * journey (size -> browse installers -> book -> track) per
 * planning/04-solar-co-ke/user-flows.md; maintenance/admin consoles are
 * not built here. Mirrors laundry.co.ke's routes/web.php pattern (see
 * planning/00-portfolio/ui-implementation-plan.md).
 *
 * @var \Solar\Core\Router $router
 */

use Solar\Controllers\PageController;

$page = new PageController();

$router->get('/', [$page, 'home']);
$router->get('/login', [$page, 'loginForm']);
$router->get('/signup', [$page, 'signupForm']);
$router->get('/onboarding', [$page, 'onboardingForm']);
$router->get('/sizing', [$page, 'sizingForm']);
$router->get('/installers', [$page, 'installerIndex']);
$router->get('/installers/{id}', [$page, 'installerProfile']);
$router->get('/installations/new', [$page, 'installationForm']);
$router->get('/installations/{id}', [$page, 'installationStatus']);
