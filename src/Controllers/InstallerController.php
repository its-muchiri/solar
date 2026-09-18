<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\Response;
use Solar\Models\Installer;
use InvalidArgumentException;

/**
 * Installer onboarding (Tier 3 KYC + certification tracking — see
 * installer_certifications in database/schema.sql) and public profile.
 */
final class InstallerController
{
    public function onboard(Request $request): void
    {
        if (!$request->user) {
            Response::unauthorized('Sign in as an installer to submit KYC documents');
            return;
        }

        $db = Database::connection();
        $installerId = $request->user['id'];

        // Tier 3 KYC (shared-architecture.md): a certification must be tracked with
        // a type from the taxonomy and a future expiry, or the installer can never
        // be listed in the directory.
        $certificationType = (string) $request->input('certification_type', '');
        if (!isset(Installer::CERTIFICATION_TYPES[$certificationType])) {
            Response::error('Choose a recognised certification type', 422, ['allowed' => array_keys(Installer::CERTIFICATION_TYPES)]);
            return;
        }
        $expiresAt = (string) $request->input('certification_expires_at', '');
        $expiryDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAt);
        if (!$expiryDate || $expiryDate->format('Y-m-d') !== $expiresAt) {
            Response::error('certification_expires_at must be a date in YYYY-MM-DD format', 422);
            return;
        }
        if ($expiresAt < date('Y-m-d')) {
            Response::error('That certification has already expired — renew it before applying', 422);
            return;
        }

        $requiredDocs = ['national_id', 'business_registration', 'professional_certification'];
        $documents = $request->input('documents', []);
        if (!is_array($documents)) {
            Response::error('documents must be a list of {document_type, file_reference}', 422);
            return;
        }
        foreach ($documents as $document) {
            if (!is_array($document) || empty($document['document_type']) || empty($document['file_reference'])) {
                Response::error('Every document needs a document_type and file_reference', 422);
                return;
            }
        }
        $submittedTypes = array_column($documents, 'document_type');

        foreach ($requiredDocs as $required) {
            if (!in_array($required, $submittedTypes, true)) {
                Response::error("Missing required document: {$required}", 422, ['required' => $requiredDocs]);
                return;
            }
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO kyc_documents (user_id, document_type, file_reference, verification_status)
                 VALUES (:user_id, :document_type, :file_reference, \'pending\')'
            );
            $certificationDocId = null;
            foreach ($documents as $document) {
                $stmt->execute([
                    'user_id' => $installerId,
                    'document_type' => $document['document_type'],
                    'file_reference' => $document['file_reference'],
                ]);
                if ($document['document_type'] === 'professional_certification') {
                    $certificationDocId = (int) $db->lastInsertId();
                }
            }

            $stmt = $db->prepare(
                'INSERT INTO installer_certifications (installer_id, certification_kyc_document_id, certification_type, expires_at, status)
                 VALUES (:installer_id, :doc_id, :cert_type, :expires_at, \'valid\')'
            );
            $stmt->execute([
                'installer_id' => $installerId,
                'doc_id' => $certificationDocId,
                'cert_type' => $certificationType,
                'expires_at' => $expiresAt,
            ]);

            $stmt = $db->prepare('UPDATE users SET status = \'pending_verification\' WHERE id = :id');
            $stmt->execute(['id' => $installerId]);

            $db->commit();
            Response::json(['status' => 'pending_verification'], 201);
        } catch (\Throwable $e) {
            $db->rollBack();
            Response::error('Onboarding submission failed', 500, ['reason' => $e->getMessage()]);
        }
    }

    /** GET /api/v1/installers — the directory, filterable by certification, rating and completed installs. */
    public function index(Request $request): void
    {
        try {
            $filters = Installer::parseFilters($request->query);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, ['allowed_certifications' => array_keys(Installer::CERTIFICATION_TYPES)]);
            return;
        }

        Response::json(['filters' => $filters, 'installers' => Installer::search($filters)]);
    }

    public function profile(Request $request): void
    {
        $installer = Installer::find((int) $request->params['id']);

        if (!$installer) {
            Response::notFound('Installer not found');
            return;
        }

        Response::json($installer);
    }
}
