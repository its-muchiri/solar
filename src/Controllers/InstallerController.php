<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\Response;

/**
 * Installer onboarding (Tier 3 KYC + certification tracking — see
 * installer_certifications in database/schema.sql) and public profile.
 */
final class InstallerController
{
    public function onboard(Request $request): void
    {
        $db = Database::connection();
        $installerId = $request->user['id'] ?? null;

        $requiredDocs = ['national_id', 'business_registration', 'professional_certification'];
        $documents = $request->input('documents', []);
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

            if ($certificationDocId && $request->input('certification_type') && $request->input('certification_expires_at')) {
                $stmt = $db->prepare(
                    'INSERT INTO installer_certifications (installer_id, certification_kyc_document_id, certification_type, expires_at, status)
                     VALUES (:installer_id, :doc_id, :cert_type, :expires_at, \'valid\')'
                );
                $stmt->execute([
                    'installer_id' => $installerId,
                    'doc_id' => $certificationDocId,
                    'cert_type' => $request->input('certification_type'),
                    'expires_at' => $request->input('certification_expires_at'),
                ]);
            }

            $stmt = $db->prepare('UPDATE users SET status = \'pending_verification\' WHERE id = :id');
            $stmt->execute(['id' => $installerId]);

            $db->commit();
            Response::json(['status' => 'pending_verification'], 201);
        } catch (\Throwable $e) {
            $db->rollBack();
            Response::error('Onboarding submission failed', 500, ['reason' => $e->getMessage()]);
        }
    }

    public function profile(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, full_name, status FROM users WHERE id = :id AND account_type = \'provider\'');
        $stmt->execute(['id' => $request->params['id']]);
        $installer = $stmt->fetch();

        if (!$installer) {
            Response::notFound('Installer not found');
            return;
        }

        $certStmt = $db->prepare('SELECT certification_type, expires_at, status FROM installer_certifications WHERE installer_id = :id');
        $certStmt->execute(['id' => $request->params['id']]);
        $installer['certifications'] = $certStmt->fetchAll();

        Response::json($installer);
    }
}
