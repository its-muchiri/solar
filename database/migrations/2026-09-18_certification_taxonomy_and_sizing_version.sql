-- Migration for databases created before 2026-09-18 (fresh installs from
-- schema.sql / schema.postgres.sql already have the column change).
-- Statements are valid on both MySQL and Postgres unless marked otherwise.

-- 1. system_sizing_calculations.calculator_version was VARCHAR(20); the
--    current version string ('v1.0.0-standard-offgrid', 23 chars) made every
--    sizing save fail with a truncation error.
--    MySQL:    ALTER TABLE system_sizing_calculations MODIFY calculator_version VARCHAR(40) NOT NULL;
--    Postgres: ALTER TABLE system_sizing_calculations ALTER COLUMN calculator_version TYPE VARCHAR(40);

-- 2. installer_certifications.certification_type moved from free text to the
--    fixed taxonomy in Solar\Models\Installer::CERTIFICATION_TYPES so the
--    directory can filter on it. Map legacy free-text rows onto it (anything
--    not matched is left alone and simply won't match a certification filter).
UPDATE installer_certifications
SET certification_type = 'epra_solar_pv_installer'
WHERE LOWER(certification_type) LIKE '%solar%pv%'
  AND certification_type NOT IN ('epra_solar_pv_installer', 'epra_solar_pv_contractor');
