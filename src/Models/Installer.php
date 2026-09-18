<?php

namespace Solar\Models;

use InvalidArgumentException;
use Solar\Config\Database;
use Solar\Core\Photos;

/**
 * Installer directory data: who is listed, their certification standing,
 * rating and completed-install count (prd.md Core Feature 2).
 *
 * An installer is listed only when the account is `active` (an admin has
 * approved their Tier 3 KYC) AND at least one certification has not passed
 * its expiry date. Certification standing is derived from `expires_at` at
 * read time rather than from `installer_certifications.status`, which
 * nothing updates after onboarding and would go stale.
 */
final class Installer
{
    /**
     * Assumption (open-questions.md #5 is unresolved): a small fixed taxonomy
     * of the certifications an installer can plausibly hold in Kenya, so the
     * directory has something consistent to filter on instead of free text.
     * Needs confirmation of the exact licence classes with EPRA/ops.
     */
    public const CERTIFICATION_TYPES = [
        'epra_solar_pv_installer' => 'EPRA solar PV installer licence',
        'epra_solar_pv_contractor' => 'EPRA solar PV contractor licence',
        'epra_electrical_installation' => 'EPRA electrical installation licence',
        'manufacturer_certified' => 'Manufacturer-certified installer',
    ];

    public const EXPIRING_SOON_DAYS = 60;

    private const SORTS = [
        'rating' => 'COALESCE(avg_rating, 0) DESC, completed_installs DESC, full_name ASC',
        'installs' => 'completed_installs DESC, COALESCE(avg_rating, 0) DESC, full_name ASC',
        'name' => 'full_name ASC',
    ];

    public static function certificationLabel(string $type): string
    {
        return self::CERTIFICATION_TYPES[$type] ?? $type;
    }

    /**
     * Sanitise directory filters from a query string.
     *
     * @return array{certification: ?string, min_rating: float, min_installs: int, sort: string}
     * @throws InvalidArgumentException on a certification type outside the taxonomy
     */
    public static function parseFilters(array $query): array
    {
        $certification = isset($query['certification']) && $query['certification'] !== '' ? (string) $query['certification'] : null;
        if ($certification !== null && !isset(self::CERTIFICATION_TYPES[$certification])) {
            throw new InvalidArgumentException('Unknown certification type: ' . $certification);
        }

        $sort = (string) ($query['sort'] ?? 'rating');

        return [
            'certification' => $certification,
            'min_rating' => max(0.0, min(5.0, (float) ($query['min_rating'] ?? 0))),
            'min_installs' => max(0, (int) ($query['min_installs'] ?? 0)),
            'sort' => isset(self::SORTS[$sort]) ? $sort : 'rating',
        ];
    }

    /**
     * Listed installers matching the filters, each with certifications,
     * rating and completed-install count attached.
     */
    public static function search(array $filters = [], ?int $limit = null): array
    {
        $filters += ['certification' => null, 'min_rating' => 0.0, 'min_installs' => 0, 'sort' => 'rating'];
        $db = Database::connection();

        $certClause = 'ic.installer_id = u.id AND ic.expires_at >= :today';
        $params = ['today' => date('Y-m-d')];
        if ($filters['certification'] !== null) {
            $certClause .= ' AND ic.certification_type = :certification';
            $params['certification'] = $filters['certification'];
        }

        $sql = "SELECT * FROM (
                    SELECT u.id, u.full_name,
                           (SELECT COUNT(*) FROM solar_bookings b WHERE b.installer_id = u.id AND b.status = 'completed') AS completed_installs,
                           (SELECT AVG(r.rating) FROM reviews r WHERE r.reviewee_id = u.id) AS avg_rating,
                           (SELECT COUNT(*) FROM reviews r WHERE r.reviewee_id = u.id) AS review_count
                    FROM users u
                    WHERE u.account_type = 'provider' AND u.status = 'active'
                      AND EXISTS (SELECT 1 FROM installer_certifications ic WHERE {$certClause})
                ) directory
                WHERE completed_installs >= :min_installs
                  AND COALESCE(avg_rating, 0) >= :min_rating
                ORDER BY " . self::SORTS[$filters['sort']];
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        $params['min_installs'] = $filters['min_installs'];
        $params['min_rating'] = $filters['min_rating'];

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $installers = array_map([self::class, 'shape'], $stmt->fetchAll());

        return self::attachCertifications($installers);
    }

    /** A listed installer's public profile, or null if unknown / not listed. */
    public static function find(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT u.id, u.full_name,
                    (SELECT COUNT(*) FROM solar_bookings b WHERE b.installer_id = u.id AND b.status = 'completed') AS completed_installs,
                    (SELECT AVG(r.rating) FROM reviews r WHERE r.reviewee_id = u.id) AS avg_rating,
                    (SELECT COUNT(*) FROM reviews r WHERE r.reviewee_id = u.id) AS review_count
             FROM users u
             WHERE u.id = :id AND u.account_type = 'provider' AND u.status = 'active'"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        [$installer] = self::attachCertifications([self::shape($row)]);

        $reviewStmt = $db->prepare(
            'SELECT r.rating, r.comment, r.created_at, u.full_name AS reviewer_name
             FROM reviews r
             JOIN users u ON u.id = r.reviewer_id
             WHERE r.reviewee_id = :id
             ORDER BY r.created_at DESC
             LIMIT 10'
        );
        $reviewStmt->execute(['id' => $id]);
        // First name only — reviewers are private individuals.
        $installer['reviews'] = array_map(static fn (array $r): array => [
            'rating' => (int) $r['rating'],
            'comment' => $r['comment'],
            'created_at' => $r['created_at'],
            'reviewer_name' => explode(' ', trim($r['reviewer_name']))[0],
        ], $reviewStmt->fetchAll());

        return $installer;
    }

    private static function shape(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'full_name' => $row['full_name'],
            'completed_installs' => (int) $row['completed_installs'],
            'avg_rating' => $row['avg_rating'] === null ? null : round((float) $row['avg_rating'], 1),
            'review_count' => (int) $row['review_count'],
            'photo_url' => Photos::installerCover((int) $row['id']),
        ];
    }

    /**
     * @param array<int,array> $installers
     * @return array<int,array>
     */
    private static function attachCertifications(array $installers): array
    {
        if (!$installers) {
            return [];
        }

        $ids = array_column($installers, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT installer_id, certification_type, expires_at
             FROM installer_certifications
             WHERE installer_id IN ({$placeholders})
             ORDER BY expires_at DESC"
        );
        $stmt->execute($ids);

        $byInstaller = [];
        foreach ($stmt->fetchAll() as $cert) {
            $byInstaller[(int) $cert['installer_id']][] = [
                'certification_type' => $cert['certification_type'],
                'label' => self::certificationLabel($cert['certification_type']),
                'expires_at' => $cert['expires_at'],
                'status' => self::certificationStatus($cert['expires_at']),
            ];
        }

        foreach ($installers as &$installer) {
            $installer['certifications'] = $byInstaller[$installer['id']] ?? [];
        }

        return $installers;
    }

    public static function certificationStatus(string $expiresAt): string
    {
        $today = date('Y-m-d');
        if ($expiresAt < $today) {
            return 'expired';
        }

        return $expiresAt <= date('Y-m-d', strtotime('+' . self::EXPIRING_SOON_DAYS . ' days')) ? 'expiring_soon' : 'valid';
    }
}
