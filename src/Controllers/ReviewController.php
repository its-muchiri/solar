<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\Response;

final class ReviewController
{
    public function store(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO reviews (booking_id, reviewer_id, reviewee_id, rating, comment, created_at)
             VALUES (:booking_id, :reviewer_id, :reviewee_id, :rating, :comment, NOW())'
        );
        $stmt->execute([
            'booking_id' => $request->params['id'],
            'reviewer_id' => $request->user['id'] ?? null,
            'reviewee_id' => $request->input('reviewee_id'),
            'rating' => $request->input('rating'),
            'comment' => $request->input('comment'),
        ]);

        Response::json(['id' => (int) $db->lastInsertId()], 201);
    }

    public function forInstaller(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT r.* FROM reviews r
             JOIN solar_bookings b ON b.id = r.booking_id
             WHERE b.installer_id = :installer_id
             ORDER BY r.created_at DESC'
        );
        $stmt->execute(['installer_id' => $request->params['id']]);

        Response::json($stmt->fetchAll());
    }
}
