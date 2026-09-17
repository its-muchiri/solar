<?php

/**
 * Route table for solar.co.ke. Mirrors planning/04-solar-co-ke/api-endpoints.md.
 *
 * @var \Solar\Core\Router $router
 */

use Solar\Controllers\DisputeController;
use Solar\Controllers\InstallationController;
use Solar\Controllers\InstallerController;
use Solar\Controllers\MaintenanceController;
use Solar\Controllers\PaymentController;
use Solar\Controllers\ReviewController;
use Solar\Controllers\SizingController;
use Solar\Controllers\StoreController;

$sizing = new SizingController();
$installation = new InstallationController();
$payment = new PaymentController();
$review = new ReviewController();
$dispute = new DisputeController();
$installer = new InstallerController();
$store = new StoreController();
$maintenance = new MaintenanceController();

// System sizing
$router->post('/api/v1/sizing-calculations', [$sizing, 'create']);
$router->get('/api/v1/sizing-calculations/{id}', [$sizing, 'show']);

// Bookings / Installations
$router->post('/api/v1/installations', [$installation, 'create']);
$router->get('/api/v1/installations', [$installation, 'index']);
$router->get('/api/v1/installations/{id}', [$installation, 'show']);
$router->post('/api/v1/installations/{id}/quotes', [$installation, 'submitQuote']);
$router->patch('/api/v1/installations/{id}/quotes/{quoteId}/accept', [$installation, 'acceptQuote']);
$router->post('/api/v1/installations/{id}/site-survey', [$installation, 'submitSiteSurvey']);
$router->patch('/api/v1/installations/{id}/status', [$installation, 'updateStatus']);
$router->post('/api/v1/installations/{id}/cancel', [$installation, 'cancel']);

// Commissioning + warranty
$router->post('/api/v1/installations/{id}/commissioning-report', [$installation, 'submitCommissioningReport']);
$router->get('/api/v1/installations/{id}/commissioning-report', [$installation, 'getCommissioningReport']);
$router->get('/api/v1/installations/{id}/warranty', [$installation, 'getWarranty']);

// Payments
$router->post('/api/v1/payments/mpesa/stk-push', [$payment, 'stkPush']);
$router->post('/api/v1/payments/mpesa/callback', [$payment, 'mpesaCallback']);
$router->post('/api/v1/payments/card', [$payment, 'card']);
$router->get('/api/v1/installers/me/earnings', [$payment, 'myEarnings']);

// Reviews
$router->post('/api/v1/installations/{id}/review', [$review, 'store']);
$router->get('/api/v1/installers/{id}/reviews', [$review, 'forInstaller']);

// Disputes
$router->post('/api/v1/installations/{id}/disputes', [$dispute, 'store']);
$router->get('/api/v1/disputes', [$dispute, 'index']);
$router->patch('/api/v1/disputes/{id}/resolve', [$dispute, 'resolve']);

// Installer onboarding (Tier 3 KYC + certification tracking)
$router->post('/api/v1/installers/onboard', [$installer, 'onboard']);
$router->get('/api/v1/installers/{id}', [$installer, 'profile']);

// Store
$router->get('/api/v1/store/products', [$store, 'listProducts']);
$router->post('/api/v1/store/orders', [$store, 'createOrder']);

// Maintenance subscriptions (V2 retention — see build-sequencing-roadmap.md)
$router->post('/api/v1/maintenance-subscriptions', [$maintenance, 'subscribe']);
$router->get('/api/v1/maintenance-subscriptions/me', [$maintenance, 'myStatus']);
