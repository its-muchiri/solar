<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\Response;

/**
 * Maintenance subscriptions — this platform's primary retention mechanic
 * given low natural repeat-purchase frequency for the installation itself
 * (see planning/04-solar-co-ke/prd.md Core Feature 5 and
 * build-sequencing-roadmap.md's V2 milestone for this platform).
 */
final class MaintenanceController
{
    private const FREQUENCY_INTERVALS = [
        'quarterly' => '+3 months',
        'biannual' => '+6 months',
        'annual' => '+1 year',
    ];

    public function subscribe(Request $request): void
    {
        $frequency = $request->input('frequency');
        if (!isset(self::FREQUENCY_INTERVALS[$frequency])) {
            Response::error('Invalid frequency', 422, ['allowed' => array_keys(self::FREQUENCY_INTERVALS)]);
            return;
        }

        $db = Database::connection();

        // Default the fulfilling provider to the original installer unless
        // a different one is explicitly specified — see database/schema.sql's
        // note that fulfilling_provider_id may differ from the original
        // installer if reassigned (confirm this is actually wanted, per
        // open-questions.md's flagged assumption on this point).
        $bookingId = $request->input('booking_id');
        $fulfillingProviderId = $request->input('fulfilling_provider_id');

        if (!$fulfillingProviderId) {
            $stmt = $db->prepare('SELECT installer_id FROM solar_bookings WHERE id = :id');
            $stmt->execute(['id' => $bookingId]);
            $fulfillingProviderId = $stmt->fetchColumn();
        }

        $stmt = $db->prepare(
            'INSERT INTO maintenance_subscriptions
                (customer_id, booking_id, fulfilling_provider_id, frequency, subscription_fee, status, next_service_due_at, created_at)
             VALUES (:customer_id, :booking_id, :provider_id, :frequency, :fee, "active", :next_due, NOW())'
        );
        $stmt->execute([
            'customer_id' => $request->user['id'] ?? null,
            'booking_id' => $bookingId,
            'provider_id' => $fulfillingProviderId,
            'frequency' => $frequency,
            'fee' => $request->input('subscription_fee', 0),
            'next_due' => date('Y-m-d', strtotime(self::FREQUENCY_INTERVALS[$frequency])),
        ]);

        Response::json(['id' => (int) $db->lastInsertId(), 'status' => 'active'], 201);
    }

    public function myStatus(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM maintenance_subscriptions WHERE customer_id = :customer_id ORDER BY created_at DESC'
        );
        $stmt->execute(['customer_id' => $request->user['id'] ?? null]);

        Response::json($stmt->fetchAll());
    }
}
