# Tests

No test runner is wired up yet. Recommended: PHPUnit for `src/`, a small assertion runner (or Vitest) for `public/assets/js/`.

Priority areas once real business logic lands:

- **The sizing methodology itself, once implemented** — this is the single highest-priority thing to test in this entire platform, given the safety/liability weight described in `src/Controllers/SizingController.php`'s docblock. Test edge cases (zero appliances, extreme backup-duration requests) before this ever reaches a real customer.
- Quote deviation-flag logic once open-questions.md #6 (threshold) is resolved
- M-Pesa/card callback idempotency
- Warranty expiry notification logic once implemented
