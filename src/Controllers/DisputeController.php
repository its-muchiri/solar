<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\Response;

/**
 * Category taxonomy (planning/04-solar-co-ke/database-schema.md):
 * mis_sizing, performance_shortfall, installation_quality, hardware_defect, other.
 *
 * The mis_sizing/performance_shortfall triage (installer error vs. a flaw
 * in the platform's own sizing calculator vs. no fault — see user-flows.md's
 * Admin Journey) is intentionally NOT implemented here: it depends on
 * open-questions.md #1 (the platform's liability posture for its own
 * calculator), which is unresolved.
 */
final class DisputeController
{
    public function store(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO disputes (booking_id, raised_by, category, description, evidence_urls, status, created_at)
             VALUES (:booking_id, :raised_by, :category, :description, :evidence_urls, \'open\', NOW())'
        );
        $stmt->execute([
            'booking_id' => $request->params['id'],
            'raised_by' => $request->user['id'] ?? null,
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'evidence_urls' => json_encode($request->input('evidence_urls', [])),
        ]);

        Response::json(['id' => (int) $db->lastInsertId(), 'status' => 'open'], 201);
    }

    public function index(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->query('SELECT * FROM disputes WHERE status IN (\'open\', \'under_review\') ORDER BY created_at ASC');

        Response::json($stmt->fetchAll());
    }

    public function resolve(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE disputes SET status = :status, resolved_by = :resolved_by, resolution_notes = :notes, resolved_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $request->input('status'),
            'resolved_by' => $request->user['id'] ?? null,
            'notes' => $request->input('resolution_notes'),
            'id' => $request->params['id'],
        ]);

        Response::json(['id' => (int) $request->params['id'], 'status' => $request->input('status')]);
    }
}
