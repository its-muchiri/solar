<?php

namespace Solar\Controllers;

use Solar\Config\Database;
use Solar\Core\Request;
use Solar\Core\Response;

/**
 * System-sizing calculator — this platform's central risk-mitigation
 * artifact (see planning/04-solar-co-ke/prd.md Core Feature 1). It must
 * produce a recommendation independent of any installer's quote, so a
 * quote that deviates materially from this output is a visible red flag
 * (see planning/04-solar-co-ke/user-flows.md step 4).
 *
 * Methodology: standard off-grid/hybrid solar sizing arithmetic (daily
 * load -> panel array sized against local peak-sun-hours, backup-duration
 * load -> battery capacity via depth-of-discharge, connected load ->
 * inverter rating with surge headroom). These are the same formulas used
 * industry-wide for preliminary sizing, not an invented heuristic — but
 * per open-questions.md #1 (platform liability for the calculator's own
 * recommendations), this has NOT been sign-off-reviewed by a solar
 * engineer against Kenya-specific irradiance data, and the calculator
 * result is presented to customers as an estimate. Revisit before
 * treating this as load-bearing for dispute resolution (user-flows.md's
 * Admin Journey case (b)).
 */
final class SizingController
{
    private const CALCULATOR_VERSION = 'v1.0.0-standard-offgrid';

    // Conservative average peak sun hours across Kenya (varies ~4.0 coastal
    // cloud cover to ~5.5 northern arid regions; 4.5 is a deliberately
    // conservative national default pending region-specific irradiance data).
    private const PEAK_SUN_HOURS_KENYA = 4.5;

    // Derates panel nameplate capacity for inverter conversion loss, wiring
    // loss, dust/soiling, and temperature derating — a standard combined
    // system-loss factor for off-grid PV sizing.
    private const SYSTEM_DERATE_FACTOR = 0.78;

    // Usable fraction of battery capacity; 0.8 is the typical manufacturer-
    // recommended depth of discharge for lithium-ion (LiFePO4) batteries,
    // the dominant chemistry for new installs.
    private const BATTERY_DEPTH_OF_DISCHARGE = 0.8;

    // Round-trip (charge/discharge) efficiency loss through the battery and
    // its charge controller/inverter path.
    private const BATTERY_ROUND_TRIP_EFFICIENCY = 0.9;

    // Headroom over simultaneous connected load to absorb motor/compressor
    // starting surges — standard off-grid inverter sizing practice.
    private const INVERTER_SAFETY_MARGIN = 1.25;

    // Approximate Kenya market rates (KES) for hardware + install labor,
    // used only to produce an estimated cost *range* for the customer, not
    // a quote. Proposed placeholders pending stakeholder market data.
    private const COST_PER_PANEL_KW = 130000;
    private const COST_PER_BATTERY_KWH = 45000;
    private const COST_PER_INVERTER_KW = 25000;
    private const INSTALLATION_LABOR_RATE = 0.15;

    public function create(Request $request): void
    {
        $applianceProfile = $request->input('appliance_profile', []);
        $desiredBackupHours = $request->input('desired_backup_duration_hours');
        $desiredBackupHours = $desiredBackupHours !== null ? (float) $desiredBackupHours : null;

        try {
            $recommendation = $this->calculate($applianceProfile, $desiredBackupHours);
        } catch (\InvalidArgumentException $e) {
            Response::error('Invalid sizing input', 422, ['reason' => $e->getMessage()]);
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

    /**
     * @param array<int, array{appliance: string, watts: float, hours_per_day: float}> $applianceProfile
     * @throws \InvalidArgumentException on invalid/insufficient input
     * @return array{average_daily_consumption_kwh: float, recommended_panel_capacity_kw: float,
     *     recommended_battery_capacity_kwh: float, recommended_inverter_rating_kw: float,
     *     estimated_cost_range_min: float, estimated_cost_range_max: float}
     */
    private function calculate(array $applianceProfile, ?float $desiredBackupHours): array
    {
        if (empty($applianceProfile)) {
            throw new \InvalidArgumentException('appliance_profile must include at least one appliance');
        }

        if ($desiredBackupHours === null || $desiredBackupHours <= 0) {
            throw new \InvalidArgumentException('desired_backup_duration_hours must be greater than 0');
        }

        $dailyWattHours = 0.0;
        $peakLoadWatts = 0.0;

        foreach ($applianceProfile as $index => $item) {
            $watts = isset($item['watts']) ? (float) $item['watts'] : 0.0;
            $hoursPerDay = isset($item['hours_per_day']) ? (float) $item['hours_per_day'] : 0.0;

            if ($watts <= 0) {
                throw new \InvalidArgumentException("appliance_profile[{$index}].watts must be greater than 0");
            }
            if ($hoursPerDay <= 0 || $hoursPerDay > 24) {
                throw new \InvalidArgumentException("appliance_profile[{$index}].hours_per_day must be between 0 and 24");
            }

            $dailyWattHours += $watts * $hoursPerDay;
            $peakLoadWatts += $watts;
        }

        $averageDailyConsumptionKwh = $dailyWattHours / 1000;

        // Panel array sized to fully replace average daily consumption
        // within local peak-sun-hours, after system losses.
        $recommendedPanelCapacityKw = $averageDailyConsumptionKwh
            / (self::PEAK_SUN_HOURS_KENYA * self::SYSTEM_DERATE_FACTOR);

        // Battery sized to cover the customer's desired backup window at
        // their average consumption rate, grossed up for usable DoD and
        // round-trip efficiency.
        $backupEnergyRequirementKwh = $averageDailyConsumptionKwh * ($desiredBackupHours / 24);
        $recommendedBatteryCapacityKwh = $backupEnergyRequirementKwh
            / (self::BATTERY_DEPTH_OF_DISCHARGE * self::BATTERY_ROUND_TRIP_EFFICIENCY);

        // Inverter must cover total connected load (with surge headroom)
        // and comfortably handle the sized panel array's output.
        $recommendedInverterRatingKw = max(
            ($peakLoadWatts * self::INVERTER_SAFETY_MARGIN) / 1000,
            $recommendedPanelCapacityKw * 1.1
        );

        // Enforce sensible minimums so a tiny load profile still yields a
        // viable, installable system.
        $recommendedPanelCapacityKw = max(round($recommendedPanelCapacityKw, 2), 0.5);
        $recommendedBatteryCapacityKwh = max(round($recommendedBatteryCapacityKwh, 2), 1.0);
        $recommendedInverterRatingKw = max(round($recommendedInverterRatingKw, 2), 0.5);

        $hardwareCost = $recommendedPanelCapacityKw * self::COST_PER_PANEL_KW
            + $recommendedBatteryCapacityKwh * self::COST_PER_BATTERY_KWH
            + $recommendedInverterRatingKw * self::COST_PER_INVERTER_KW;
        $baseCost = $hardwareCost * (1 + self::INSTALLATION_LABOR_RATE);

        return [
            'average_daily_consumption_kwh' => round($averageDailyConsumptionKwh, 2),
            'recommended_panel_capacity_kw' => $recommendedPanelCapacityKw,
            'recommended_battery_capacity_kwh' => $recommendedBatteryCapacityKwh,
            'recommended_inverter_rating_kw' => $recommendedInverterRatingKw,
            'estimated_cost_range_min' => round($baseCost * 0.9, -3),
            'estimated_cost_range_max' => round($baseCost * 1.3, -3),
        ];
    }
}
