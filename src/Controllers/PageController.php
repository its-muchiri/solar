<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\View;
use Solar\Models\Installer;
use Solar\Models\SolarBooking;
use InvalidArgumentException;
use Throwable;

/**
 * Server-rendered pages for the primary customer journey — size a system,
 * browse installers, book an installation, track status — per
 * planning/04-solar-co-ke/prd.md's Core Feature 1 and
 * user-flows.md's primary journey. Admin/maintenance consoles are not
 * built here, matching the scope discipline set by laundry.co.ke's page
 * layer (see planning/00-portfolio/ui-implementation-plan.md §3).
 *
 * Every DB-backed method fails soft: this environment (a fresh Vercel
 * deploy with no database wired yet) has no live connection, so a page
 * must still render a meaningful empty state rather than a fatal error,
 * per artcollect-design-system.md §8.
 */
final class PageController
{
    public function home(Request $request): void
    {
        $installers = [];
        $dbError = null;

        try {
            $installers = Installer::search(['sort' => 'rating'], 3);
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live installer data is unavailable in this environment — no database is connected yet.';
        }

        View::render('home', [
            'title' => 'Size, install, and maintain your solar system',
            'installers' => $installers,
            'dbError' => $dbError,
        ]);
    }

    public function loginForm(Request $request): void
    {
        View::render('login', ['title' => 'Log in']);
    }

    public function signupForm(Request $request): void
    {
        View::render('signup', ['title' => 'Sign up']);
    }

    public function onboardingForm(Request $request): void
    {
        View::render('onboarding', ['title' => 'Installer verification']);
    }

    public function sizingForm(Request $request): void
    {
        View::render('sizing', ['title' => 'System sizing calculator']);
    }

    public function installerIndex(Request $request): void
    {
        $installers = [];
        $dbError = null;
        $filterError = null;

        try {
            $filters = Installer::parseFilters($request->query);
        } catch (InvalidArgumentException $e) {
            $filterError = 'That certification filter is not recognised, so all installers are shown.';
            $filters = Installer::parseFilters([]);
        }

        try {
            $installers = Installer::search($filters);
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live installer data is unavailable in this environment — no database is connected yet.';
        }

        View::render('installer-index', [
            'title' => 'Browse installers',
            'installers' => $installers,
            'filters' => $filters,
            'filterError' => $filterError,
            'dbError' => $dbError,
        ]);
    }

    public function installerProfile(Request $request): void
    {
        $installerId = (int) $request->params['id'];
        $installer = null;
        $dbError = null;

        try {
            $installer = Installer::find($installerId);
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live installer data is unavailable in this environment — no database is connected yet.';
        }

        if (!$installer && !$dbError) {
            http_response_code(404);
        }

        View::render('installer-profile', [
            'title' => $installer ? $installer['full_name'] : 'Installer #' . $installerId,
            'installerId' => $installerId,
            'installer' => $installer,
            'dbError' => $dbError,
        ]);
    }

    public function installationForm(Request $request): void
    {
        View::render('installation-new', [
            'title' => 'Book an installation',
            'prefillSizingId' => $request->query['sizing_calculation_id'] ?? '',
        ]);
    }

    public function installationStatus(Request $request): void
    {
        $bookingId = (int) $request->params['id'];
        $booking = null;
        $warranty = [];
        $dbError = null;

        try {
            $booking = SolarBooking::find($bookingId);

            if ($booking && $booking['status'] === 'completed') {
                $stmt = Database::connection()->prepare('SELECT * FROM warranty_records WHERE booking_id = :id');
                $stmt->execute(['id' => $bookingId]);
                $warranty = $stmt->fetchAll();
            }
        } catch (Throwable $e) {
            error_log((string) $e);
            $dbError = 'Live installation data is unavailable in this environment — no database is connected yet.';
        }

        View::render('installation-status', [
            'title' => 'Installation #' . $bookingId,
            'bookingId' => $bookingId,
            'booking' => $booking,
            'warranty' => $warranty,
            'dbError' => $dbError,
        ]);
    }
}
