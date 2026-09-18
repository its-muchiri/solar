<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\Response;

/**
 * Maps to "Bookings / Installations" and the commissioning-report part of
 * "Platform-Specific Resources" in planning/04-solar-co-ke/api-endpoints.md.
 */
final class InstallationController
{
    public function create(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Sign in as a customer to request an installation');
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO solar_bookings
                (customer_id, sizing_calculation_id, status, site_address, site_lat, site_lng,
                 total_contract_value, deposit_amount, created_at, updated_at)
             VALUES (:customer_id, :sizing_calculation_id, \'open_for_quotes\', :site_address, :site_lat, :site_lng,
                 0, 0, NOW(), NOW())'
        );
        $stmt->execute([
            'customer_id' => $request->user['id'] ?? null,
            'sizing_calculation_id' => $request->input('sizing_calculation_id'),
            'site_address' => $request->input('site_address'),
            'site_lat' => $request->input('site_lat'),
            'site_lng' => $request->input('site_lng'),
        ]);

        Response::json(['id' => (int) $db->lastInsertId(), 'status' => 'open_for_quotes'], 201);
    }

    public function index(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->query('SELECT * FROM solar_bookings WHERE status = \'open_for_quotes\' ORDER BY created_at DESC');

        Response::json($stmt->fetchAll());
    }

    public function show(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM solar_bookings WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);
        $booking = $stmt->fetch();

        if (!$booking) {
            Response::notFound('Installation not found');
            return;
        }

        Response::json($booking);
    }

    public function submitQuote(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Sign in as an installer to submit a quote');
            return;
        }

        $db = Database::connection();

        // TODO: compare quoted capacity against the linked sizing
        // calculation and set deviation_flag — threshold unresolved, see
        // open-questions.md #6.
        $stmt = $db->prepare(
            'INSERT INTO installation_quotes
                (booking_id, installer_id, quoted_panel_capacity_kw, quoted_battery_capacity_kwh,
                 quoted_inverter_rating_kw, quoted_amount, deviation_flag, status, created_at)
             VALUES (:booking_id, :installer_id, :panel_kw, :battery_kwh, :inverter_kw, :amount, false, \'submitted\', NOW())'
        );
        $stmt->execute([
            'booking_id' => $request->params['id'],
            'installer_id' => $request->user['id'] ?? null,
            'panel_kw' => $request->input('quoted_panel_capacity_kw'),
            'battery_kwh' => $request->input('quoted_battery_capacity_kwh'),
            'inverter_kw' => $request->input('quoted_inverter_rating_kw'),
            'amount' => $request->input('quoted_amount'),
        ]);

        Response::json(['id' => (int) $db->lastInsertId()], 201);
    }

    public function acceptQuote(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE installation_quotes SET status = \'accepted\' WHERE id = :quote_id');
        $stmt->execute(['quote_id' => $request->params['quoteId']]);

        // MySQL's multi-table UPDATE...JOIN has no Postgres equivalent —
        // Postgres uses UPDATE...FROM instead — so this branches on the
        // active driver; see Database::driver() and
        // planning/00-portfolio/ui-implementation-plan.md for why both
        // exist (Vercel's Marketplace has no MySQL-compatible database).
        $sql = Database::driver() === 'pgsql'
            ? 'UPDATE solar_bookings b
               SET installer_id = q.installer_id,
                   contracted_panel_capacity_kw = q.quoted_panel_capacity_kw,
                   contracted_battery_capacity_kwh = q.quoted_battery_capacity_kwh,
                   contracted_inverter_rating_kw = q.quoted_inverter_rating_kw,
                   total_contract_value = q.quoted_amount,
                   status = \'quote_accepted\', updated_at = NOW()
               FROM installation_quotes q
               WHERE q.id = :quote_id AND b.id = :booking_id'
            : 'UPDATE solar_bookings b
               JOIN installation_quotes q ON q.id = :quote_id
               SET b.installer_id = q.installer_id,
                   b.contracted_panel_capacity_kw = q.quoted_panel_capacity_kw,
                   b.contracted_battery_capacity_kwh = q.quoted_battery_capacity_kwh,
                   b.contracted_inverter_rating_kw = q.quoted_inverter_rating_kw,
                   b.total_contract_value = q.quoted_amount,
                   b.status = \'quote_accepted\', b.updated_at = NOW()
               WHERE b.id = :booking_id';
        $stmt = $db->prepare($sql);
        $stmt->execute(['quote_id' => $request->params['quoteId'], 'booking_id' => $request->params['id']]);

        Response::json(['id' => (int) $request->params['id'], 'status' => 'quote_accepted']);
    }

    public function submitSiteSurvey(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Sign in as an installer to submit a site survey');
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO site_surveys
                (booking_id, scheduled_at, roof_type, roof_condition_notes, shading_assessment,
                 survey_photos, suitability_confirmed, revised_recommendation_notes, conducted_by)
             VALUES (:booking_id, :scheduled_at, :roof_type, :roof_condition_notes, :shading_assessment,
                 :survey_photos, :suitability_confirmed, :revised_notes, :conducted_by)'
        );
        $stmt->execute([
            'booking_id' => $request->params['id'],
            'scheduled_at' => $request->input('scheduled_at'),
            'roof_type' => $request->input('roof_type'),
            'roof_condition_notes' => $request->input('roof_condition_notes'),
            'shading_assessment' => $request->input('shading_assessment'),
            'survey_photos' => json_encode($request->input('survey_photos', [])),
            'suitability_confirmed' => $request->input('suitability_confirmed'),
            'revised_notes' => $request->input('revised_recommendation_notes'),
            'conducted_by' => $request->user['id'] ?? null,
        ]);

        Response::json(['id' => (int) $db->lastInsertId()], 201);
    }

    public function updateStatus(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE solar_bookings SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $request->input('status'), 'id' => $request->params['id']]);

        Response::json(['id' => (int) $request->params['id'], 'status' => $request->input('status')]);
    }

    public function cancel(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE solar_bookings SET status = \'cancelled\', updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);

        Response::json(['id' => (int) $request->params['id'], 'status' => 'cancelled']);
    }

    public function submitCommissioningReport(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Sign in as an installer to submit a commissioning report');
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO post_installation_reports
                (booking_id, actual_panel_capacity_kw, actual_battery_capacity_kwh, actual_inverter_rating_kw,
                 commissioning_readings, photo_urls, submitted_by, independently_verified, created_at)
             VALUES (:booking_id, :panel_kw, :battery_kwh, :inverter_kw, :readings, :photo_urls, :submitted_by, :verified, NOW())'
        );
        $stmt->execute([
            'booking_id' => $request->params['id'],
            'panel_kw' => $request->input('actual_panel_capacity_kw'),
            'battery_kwh' => $request->input('actual_battery_capacity_kwh'),
            'inverter_kw' => $request->input('actual_inverter_rating_kw'),
            'readings' => json_encode($request->input('commissioning_readings', [])),
            'photo_urls' => json_encode($request->input('photo_urls', [])),
            'submitted_by' => $request->user['id'] ?? null,
            'verified' => (bool) $request->input('independently_verified', false),
        ]);

        Response::json(['id' => (int) $db->lastInsertId()], 201);
    }

    public function getCommissioningReport(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM post_installation_reports WHERE booking_id = :booking_id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['booking_id' => $request->params['id']]);

        Response::json($stmt->fetch() ?: null);
    }

    public function getWarranty(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM warranty_records WHERE booking_id = :booking_id');
        $stmt->execute(['booking_id' => $request->params['id']]);

        Response::json($stmt->fetchAll());
    }
}
