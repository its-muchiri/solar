<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\Response;

/**
 * System-sizing calculator — this platform's central risk-mitigation
 * artifact (see planning/04-solar-co-ke/prd.md Core Feature 1).
 *
 * IMPORTANT: the actual sizing formula (appliance profile -> recommended
 * panel/battery/inverter capacity) is a genuinely platform-specific
 * engineering + domain problem, not something to stub with a guess — it
 * has real safety/liability weight (see open-questions.md #1, the
 * platform's liability posture for its own calculator's recommendations).
 * This controller only persists the input/output shape; `calculate()`
 * intentionally throws until a validated methodology is implemented.
 */
final class SizingController
{
    public function create(Request $request): void
    {
        $applianceProfile = $request->input('appliance_profile', []);
        $desiredBackupHours = $request->input('desired_backup_duration_hours');

        try {
            $recommendation = $this->calculate($applianceProfile, $desiredBackupHours);
        } catch (\RuntimeException $e) {
            Response::error('Sizing calculation not yet implemented', 501, ['reason' => $e->getMessage()]);
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO system_sizing_calculations
                (customer_id, appliance_profile, average_daily_consumption_kwh, desired_backup_duration_hours,
                 budget_range_min, budget_range_max, recommended_panel_capacity_kw, recommended_battery_capacity_kwh,
                 recommended_inverter_rating_kw, estimated_cost_range_min, estimated_cost_range_max,
                 calculator_version, created_at)
             VALUES (:customer_id, :appliance_profile, :avg_kwh, :backup_hours,
                 :budget_min, :budget_max, :panel_kw, :battery_kwh, :inverter_kw, :cost_min, :cost_max,
                 :calculator_version, NOW())'
        );

        $stmt->execute([
            'customer_id' => $request->user['id'] ?? null,
            'appliance_profile' => json_encode($applianceProfile),
            'avg_kwh' => $recommendation['average_daily_consumption_kwh'],
            'backup_hours' => $desiredBackupHours,
            'budget_min' => $request->input('budget_range_min'),
            'budget_max' => $request->input('budget_range_max'),
            'panel_kw' => $recommendation['recommended_panel_capacity_kw'],
            'battery_kwh' => $recommendation['recommended_battery_capacity_kwh'],
            'inverter_kw' => $recommendation['recommended_inverter_rating_kw'],
            'cost_min' => $recommendation['estimated_cost_range_min'],
            'cost_max' => $recommendation['estimated_cost_range_max'],
            'calculator_version' => self::CALCULATOR_VERSION,
        ]);

        Response::json(['id' => (int) $db->lastInsertId()] + $recommendation, 201);
    }

    public function show(Request $request): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM system_sizing_calculations WHERE id = :id');
        $stmt->execute(['id' => $request->params['id']]);
        $calculation = $stmt->fetch();

        if (!$calculation) {
            Response::notFound('Sizing calculation not found');
            return;
        }

        Response::json($calculation);
    }

    private const CALCULATOR_VERSION = 'unimplemented-0.0.0';

    /**
     * @param array<int, array{appliance: string, watts: float, hours_per_day: float}> $applianceProfile
     * @throws \RuntimeException always, until a validated sizing methodology replaces this stub
     */
    private function calculate(array $applianceProfile, ?float $desiredBackupHours): array
    {
        throw new \RuntimeException(
            'System-sizing methodology not implemented — this must be designed and validated by someone ' .
            'with solar-engineering domain expertise before this platform can safely make sizing ' .
            'recommendations. Do not guess at a formula here.'
        );
    }
}
