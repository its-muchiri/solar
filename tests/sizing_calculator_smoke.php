<?php

/**
 * Standalone smoke test for SizingController's calculate() method — no
 * PHPUnit dependency (none is wired up yet, see tests/README.md), no
 * database required. Run with: php tests/sizing_calculator_smoke.php
 *
 * Covers tests/README.md's top-priority item: "the sizing methodology
 * itself... test edge cases (zero appliances, extreme backup-duration
 * requests) before this ever reaches a real customer."
 */

require __DIR__ . '/../vendor/autoload.php';

use Solar\Controllers\SizingController;

$failures = 0;
$passes = 0;

function invokeCalculate(array $applianceProfile, ?float $backupHours): array
{
    $controller = new SizingController();
    $method = new ReflectionMethod(SizingController::class, 'calculate');
    $method->setAccessible(true);
    return $method->invoke($controller, $applianceProfile, $backupHours);
}

function check(string $label, callable $assertion): void
{
    global $failures, $passes;
    try {
        $assertion();
        echo "PASS: {$label}\n";
        $passes++;
    } catch (\Throwable $e) {
        echo "FAIL: {$label} — {$e->getMessage()}\n";
        $failures++;
    }
}

// 1. Typical household profile produces a sane, positive recommendation.
check('typical household profile yields positive recommendation', function () {
    $result = invokeCalculate([
        ['appliance' => 'Fridge', 'watts' => 150, 'hours_per_day' => 24],
        ['appliance' => 'Lights', 'watts' => 60, 'hours_per_day' => 6],
        ['appliance' => 'TV', 'watts' => 120, 'hours_per_day' => 4],
    ], 8.0);

    assert_true($result['average_daily_consumption_kwh'] > 0, 'average_daily_consumption_kwh should be > 0');
    assert_true($result['recommended_panel_capacity_kw'] > 0, 'recommended_panel_capacity_kw should be > 0');
    assert_true($result['recommended_battery_capacity_kwh'] > 0, 'recommended_battery_capacity_kwh should be > 0');
    assert_true($result['recommended_inverter_rating_kw'] > 0, 'recommended_inverter_rating_kw should be > 0');
    assert_true($result['estimated_cost_range_min'] < $result['estimated_cost_range_max'], 'cost range min should be < max');
});

// 2. Zero appliances must be rejected, not silently produce a zero-size system.
check('empty appliance_profile throws InvalidArgumentException', function () {
    $threw = false;
    try {
        invokeCalculate([], 8.0);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'expected InvalidArgumentException for empty appliance_profile');
});

// 3. Missing/zero backup duration must be rejected.
check('zero desired_backup_duration_hours throws InvalidArgumentException', function () {
    $threw = false;
    try {
        invokeCalculate([['appliance' => 'Fridge', 'watts' => 150, 'hours_per_day' => 24]], 0.0);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'expected InvalidArgumentException for zero backup duration');
});

// 4. hours_per_day out of the valid 0-24 range must be rejected.
check('hours_per_day > 24 throws InvalidArgumentException', function () {
    $threw = false;
    try {
        invokeCalculate([['appliance' => 'Bad', 'watts' => 100, 'hours_per_day' => 30]], 8.0);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'expected InvalidArgumentException for hours_per_day > 24');
});

// 5. Extreme backup-duration request (e.g. multi-day) must not crash and
// must scale battery capacity up proportionally rather than silently cap.
check('extreme backup duration scales battery capacity without error', function () {
    $short = invokeCalculate([['appliance' => 'Fridge', 'watts' => 150, 'hours_per_day' => 24]], 8.0);
    $long = invokeCalculate([['appliance' => 'Fridge', 'watts' => 150, 'hours_per_day' => 24]], 72.0);

    assert_true($long['recommended_battery_capacity_kwh'] > $short['recommended_battery_capacity_kwh'],
        'longer backup duration should recommend more battery capacity');
});

// 6. Zero-watt appliance must be rejected rather than silently ignored.
check('zero-watt appliance throws InvalidArgumentException', function () {
    $threw = false;
    try {
        invokeCalculate([['appliance' => 'Nothing', 'watts' => 0, 'hours_per_day' => 5]], 8.0);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    assert_true($threw, 'expected InvalidArgumentException for zero-watt appliance');
});

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

echo "\n{$passes} passed, {$failures} failed\n";
exit($failures > 0 ? 1 : 0);
