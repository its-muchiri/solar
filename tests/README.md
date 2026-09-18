# Tests

No PHPUnit/Vitest runner is wired up yet, but `tests/sizing_calculator_smoke.php` is a standalone, dependency-free smoke test (uses only the Composer autoloader) covering the sizing methodology's edge cases — run it with `php tests/sizing_calculator_smoke.php`. Recommended long-term: PHPUnit for `src/`, a small assertion runner (or Vitest) for `public/assets/js/`.

`tests/installer_directory_smoke.php` covers the installer directory (listing rules, rating/installs/certification filters, sorting, expiry-derived certification status, profile visibility). It needs the local MySQL from `.env`; all fixtures are inserted in a transaction that is rolled back, and it prints SKIP (exit 0) if no database is reachable. Run with `php tests/installer_directory_smoke.php`.

Priority areas once real business logic lands:

- ~~The sizing methodology itself~~ — implemented in `src/Controllers/SizingController.php` and covered by `tests/sizing_calculator_smoke.php` (zero appliances, zero/extreme backup-duration requests, zero-watt appliances, out-of-range hours). Still needs a solar-engineering domain-expert review of the constants/methodology before being treated as authoritative for dispute resolution — see that controller's docblock and open-questions.md #1.
- Quote deviation-flag logic once open-questions.md #6 (threshold) is resolved
- M-Pesa/card callback idempotency
- Warranty expiry notification logic once implemented
