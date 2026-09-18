<?php

/**
 * Smoke test for the installer directory (Installer::search/find/parseFilters).
 * Needs the MySQL database from .env (schema loaded); every fixture row is
 * inserted inside a transaction that is rolled back, so nothing is left
 * behind. Run with: php tests/installer_directory_smoke.php
 */

require __DIR__ . '/../vendor/autoload.php';

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

use Solar\Config\Database;
use Solar\Models\Installer;

try {
    $db = Database::connection();
} catch (Throwable $e) {
    echo "SKIP: database not reachable ({$e->getMessage()})\n";
    exit(0);
}

$failures = 0;
$passes = 0;

function check(string $label, callable $assertion): void
{
    global $failures, $passes;
    try {
        $assertion();
        echo "PASS: {$label}\n";
        $passes++;
    } catch (Throwable $e) {
        echo "FAIL: {$label} — {$e->getMessage()}\n";
        $failures++;
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Names of fixture installers in a result set, so real data can't interfere. */
function fixtureNames(array $installers): array
{
    $names = array_column($installers, 'full_name');

    return array_values(array_filter($names, static fn (string $n): bool => str_starts_with($n, 'ZZ Smoke ')));
}

$db->beginTransaction();

try {
    $phoneSeed = random_int(100000, 999999) * 10;
    $insertUser = static function (string $name, string $type, string $status) use ($db, &$phoneSeed): int {
        $stmt = $db->prepare(
            'INSERT INTO users (phone_number, password_hash, full_name, account_type, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(['+2547' . $phoneSeed++, 'x', $name, $type, $status]);

        return (int) $db->lastInsertId();
    };
    $insertCert = static function (int $installerId, string $type, string $expiresAt) use ($db): void {
        $doc = $db->prepare("INSERT INTO kyc_documents (user_id, document_type, file_reference) VALUES (?, 'professional_certification', 'https://example.test/cert.pdf')");
        $doc->execute([$installerId]);
        $stmt = $db->prepare('INSERT INTO installer_certifications (installer_id, certification_kyc_document_id, certification_type, expires_at, status) VALUES (?, ?, ?, ?, \'valid\')');
        $stmt->execute([$installerId, (int) $db->lastInsertId(), $type, $expiresAt]);
    };
    $insertBooking = static function (int $customerId, int $installerId, string $status) use ($db): int {
        $stmt = $db->prepare(
            'INSERT INTO solar_bookings (customer_id, installer_id, status, site_address, site_lat, site_lng) VALUES (?, ?, ?, \'Nairobi\', -1.28, 36.82)'
        );
        $stmt->execute([$customerId, $installerId, $status]);

        return (int) $db->lastInsertId();
    };
    $insertReview = static function (int $bookingId, int $reviewerId, int $installerId, int $rating, string $comment) use ($db): void {
        $stmt = $db->prepare('INSERT INTO reviews (booking_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$bookingId, $reviewerId, $installerId, $rating, $comment]);
    };

    $customer = $insertUser('ZZ Smoke Customer Wanjiru Kamau', 'customer', 'active');

    // A: pv installer licence, 3 completed installs, ratings 5 and 4 (avg 4.5)
    $a = $insertUser('ZZ Smoke A Bright Solar', 'provider', 'active');
    $insertCert($a, 'epra_solar_pv_installer', date('Y-m-d', strtotime('+2 years')));
    foreach ([1, 2, 3] as $_) {
        $b = $insertBooking($customer, $a, 'completed');
        if ($_ === 1) { $insertReview($b, $customer, $a, 5, 'Excellent work'); }
        if ($_ === 2) { $insertReview($b, $customer, $a, 4, 'Good, a day late'); }
    }
    $insertBooking($customer, $a, 'installing'); // in progress — must not count as completed

    // B: contractor licence, renewal due within 60 days, 1 completed install, no reviews
    $bId = $insertUser('ZZ Smoke B Sunrise Power', 'provider', 'active');
    $insertCert($bId, 'epra_solar_pv_contractor', date('Y-m-d', strtotime('+30 days')));
    $insertBooking($customer, $bId, 'completed');

    // C: certification already expired — must not be listed
    $c = $insertUser('ZZ Smoke C Lapsed Licence', 'provider', 'active');
    $insertCert($c, 'epra_solar_pv_installer', date('Y-m-d', strtotime('-1 day')));

    // D: KYC not yet approved — must not be listed
    $d = $insertUser('ZZ Smoke D Pending KYC', 'provider', 'pending_verification');
    $insertCert($d, 'epra_solar_pv_installer', date('Y-m-d', strtotime('+2 years')));

    // E: expired PV licence but a valid electrical licence — listed, and only under the electrical filter
    $e = $insertUser('ZZ Smoke E Two Licences', 'provider', 'active');
    $insertCert($e, 'epra_solar_pv_installer', date('Y-m-d', strtotime('-10 days')));
    $insertCert($e, 'epra_electrical_installation', date('Y-m-d', strtotime('+1 year')));

    $all = Installer::search([]);
    check('lists only active installers with an unexpired certification', function () use ($all) {
        $names = fixtureNames($all);
        sort($names);
        expect($names === ['ZZ Smoke A Bright Solar', 'ZZ Smoke B Sunrise Power', 'ZZ Smoke E Two Licences'], 'got ' . json_encode($names));
    });

    $byName = static fn (array $installers, string $name): ?array => current(array_filter($installers, static fn (array $i): bool => $i['full_name'] === $name)) ?: null;

    check('completed_installs counts only completed bookings', function () use ($all, $byName) {
        expect($byName($all, 'ZZ Smoke A Bright Solar')['completed_installs'] === 3, 'A should have 3');
        expect($byName($all, 'ZZ Smoke B Sunrise Power')['completed_installs'] === 1, 'B should have 1');
    });

    check('rating is the average of reviews, null when unreviewed', function () use ($all, $byName) {
        $a = $byName($all, 'ZZ Smoke A Bright Solar');
        expect($a['avg_rating'] === 4.5 && $a['review_count'] === 2, 'A: ' . json_encode([$a['avg_rating'], $a['review_count']]));
        expect($byName($all, 'ZZ Smoke B Sunrise Power')['avg_rating'] === null, 'B should be unrated');
    });

    check('certification standing is derived from expiry, not the stored status column', function () use ($all, $byName) {
        expect($byName($all, 'ZZ Smoke A Bright Solar')['certifications'][0]['status'] === 'valid', 'A valid');
        expect($byName($all, 'ZZ Smoke B Sunrise Power')['certifications'][0]['status'] === 'expiring_soon', 'B expiring_soon');
        $statuses = array_column($byName($all, 'ZZ Smoke E Two Licences')['certifications'], 'status', 'certification_type');
        expect($statuses['epra_solar_pv_installer'] === 'expired', 'E PV licence expired');
    });

    check('certification filter matches only unexpired certifications of that type', function () {
        $pv = fixtureNames(Installer::search(['certification' => 'epra_solar_pv_installer']));
        expect($pv === ['ZZ Smoke A Bright Solar'], 'pv: ' . json_encode($pv));
        $elec = fixtureNames(Installer::search(['certification' => 'epra_electrical_installation']));
        expect($elec === ['ZZ Smoke E Two Licences'], 'electrical: ' . json_encode($elec));
    });

    check('minimum rating filter excludes unrated and lower-rated installers', function () {
        expect(fixtureNames(Installer::search(['min_rating' => 4.5])) === ['ZZ Smoke A Bright Solar'], '4.5+');
        expect(fixtureNames(Installer::search(['min_rating' => 4.6])) === [], '4.6+ should be empty');
    });

    check('minimum completed-installs filter', function () {
        expect(fixtureNames(Installer::search(['min_installs' => 3])) === ['ZZ Smoke A Bright Solar'], '3+');
        expect(count(fixtureNames(Installer::search(['min_installs' => 1]))) === 2, '1+');
        expect(fixtureNames(Installer::search(['min_installs' => 50])) === [], '50+ should be empty');
    });

    check('sort orders: rating, installs, name; limit applies', function () {
        expect(fixtureNames(Installer::search(['sort' => 'installs']))[0] === 'ZZ Smoke A Bright Solar', 'installs sort');
        $byNameSorted = fixtureNames(Installer::search(['sort' => 'name']));
        expect($byNameSorted[0] === 'ZZ Smoke A Bright Solar' && end($byNameSorted) === 'ZZ Smoke E Two Licences', 'name sort');
        expect(count(Installer::search([], 1)) === 1, 'limit 1');
    });

    check('find() returns a listed installer with reviews, first names only', function () use ($a) {
        $profile = Installer::find($a);
        expect($profile !== null && $profile['completed_installs'] === 3, 'profile stats');
        expect(count($profile['reviews']) === 2, 'two reviews');
        expect($profile['reviews'][0]['reviewer_name'] === 'ZZ', 'first name only, got ' . $profile['reviews'][0]['reviewer_name']);
    });

    check('find() hides installers whose KYC is not approved and non-installers', function () use ($d, $customer) {
        expect(Installer::find($d) === null, 'pending installer visible');
        expect(Installer::find($customer) === null, 'customer visible as installer');
        expect(Installer::find(999999999) === null, 'unknown id');
    });

    check('parseFilters clamps values and rejects unknown certification types', function () {
        $f = Installer::parseFilters(['min_rating' => '9', 'min_installs' => '-4', 'sort' => 'bogus']);
        expect($f['min_rating'] === 5.0 && $f['min_installs'] === 0 && $f['sort'] === 'rating', json_encode($f));
        try {
            Installer::parseFilters(['certification' => 'made_up']);
            throw new RuntimeException('expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            // expected
        }
    });

    check('certificationStatus boundaries', function () {
        expect(Installer::certificationStatus(date('Y-m-d')) === 'expiring_soon', 'expires today is still usable');
        expect(Installer::certificationStatus(date('Y-m-d', strtotime('-1 day'))) === 'expired', 'yesterday expired');
        expect(Installer::certificationStatus(date('Y-m-d', strtotime('+61 days'))) === 'valid', '61 days valid');
    });
} finally {
    $db->rollBack();
}

echo "\n{$passes} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
