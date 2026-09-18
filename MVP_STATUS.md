# MVP Status — Solar (solar.co.ke)

STATE: IN_PROGRESS

## Planning references
- Product spec: ../planning/04-solar-co-ke/prd.md
- Data model: ../planning/04-solar-co-ke/database-schema.md
- API contract: ../planning/04-solar-co-ke/api-endpoints.md
- User flows: ../planning/04-solar-co-ke/user-flows.md
- Open questions (stakeholder-pending decisions): ../planning/04-solar-co-ke/open-questions.md
- Shared modules (auth, payments/escrow, booking engine, KYC, reviews): ../planning/00-portfolio/shared-architecture.md
- Authoritative MVP feature cut & build order: ../planning/00-portfolio/build-sequencing-roadmap.md

## What "MVP complete" means for this project
Per build-sequencing-roadmap.md, solar.co.ke is fourth in the build order. Its MVP is: the system-sizing calculator, installer matching, an e-commerce store for panels/inverters/batteries, and M-Pesa + card payment for high-value transactions — built on shared modules 1–4 plus module 9 (KYC/Verification Pipeline v2, Tier 3 depth). Maintenance subscriptions, installer performance dashboard, extended warranty tracking, and financing are V2/V3 — don't block DONE on them even though a controller for maintenance already exists.

- Customer can sign up / log in
- Customer can run the system-sizing calculator (household/business energy profile → recommended panel/battery/inverter capacity), independent of any installer's quote
- Customer can browse/filter verified installers by certification, rating, and completed-install count
- Customer can request/accept an installation quote, pay a deposit, and pay the remainder on completion/commissioning (milestone payment, not a single lump sum)
- Customer can pay via M-Pesa or card (card matters here given higher transaction values than the other platforms)
- Installer completes KYC Tier 3 onboarding (ID + certification, tracked with expiry) before being listed
- Customer can buy panels/inverters/batteries/accessories from the store, standalone or bundled into a booking
- Customer can submit a review; disputes over mis-sizing/performance route to a technical-review path
- Every image slot (homepage hero, installer directory/profile photos, store product photos for panels/inverters/batteries) shows a real, topically relevant photo sourced from Unsplash — not a placeholder box or broken image
- App builds and runs with zero errors, works on mobile width
- Core flow (size system → pick installer → pay deposit → track → pay remainder → review) covered by a smoke test

## Checklist
Controllers already exist for most of this (src/Controllers/*) — verify against the spec above and the planning docs rather than assuming they're complete, and rather than rebuilding from scratch.

- [ ] Identity/auth + installer KYC Tier 3 with certification tracking (verify InstallerController.php against shared-architecture.md's KYC pipeline v2)
- [x] System-sizing calculator, independent of installer quotes — `SizingController::calculate()` now implements standard off-grid/hybrid sizing arithmetic (daily load → panel array via Kenya peak-sun-hours; backup duration → battery capacity via depth-of-discharge; connected load → inverter rating with surge headroom), fully documented with its constants/assumptions in that controller's docblock. Built a real appliance add/remove entry UI in `sizing-calculator.js` (previously a TODO stub with a hardcoded empty array) plus optional budget-range inputs. Covered by `tests/sizing_calculator_smoke.php` (6 cases: typical profile, empty appliances, zero backup duration, out-of-range hours, extreme backup duration, zero-watt appliance — all pass). **Assumption taken:** the specific constants (4.5 peak sun hours, 0.78 system derate, 0.8 battery DoD, 1.25 inverter safety margin, KES/kW hardware cost rates) are standard industry-practice defaults, not a stakeholder-confirmed methodology — per open-questions.md #1 this still needs a solar-engineering domain-expert sign-off before the platform treats it as authoritative for dispute resolution (user-flows.md Admin Journey case (b)); the calculator UI now says so explicitly to the customer.
- [ ] Installer directory with certification/rating filter (verify InstallerController.php, installer-index.php, installer-profile.php)
- [ ] Installation booking + milestone payment (deposit at start, remainder at completion/commissioning) (verify InstallationController.php, PaymentController.php)
- [ ] Card payment support alongside M-Pesa for high-value transactions
- [ ] Store: panels/inverters/batteries/accessories (verify StoreController.php against prd.md's E-Commerce Store Scope)
- [ ] Review submission + technical-dispute path for mis-sizing/performance claims (verify ReviewController.php, DisputeController.php)
- [ ] Real Unsplash photography (verified resolving URLs) for hero imagery on home.php/sizing.php, installer-index.php/installer-profile.php, and store product photos — solar panel/installation subject matter, no placeholders or broken images
- [ ] Error handling (no installers available in area, failed payment, invalid sizing inputs)
- [ ] Smoke test / manual run-through of the full core flow passes
- [ ] Remove stubs, TODOs, placeholder data
- [ ] Cross-check against open-questions.md — where it conflicts with an assumption made here, note the assumption taken and continue (don't stop to ask)

Not MVP per the roadmap — don't block DONE on these even though MaintenanceController.php already exists: maintenance subscription plans, installer performance dashboard, warranty tracking, referral program, financing/installment payments.

## Known issues / open questions
- Mis-sizing liability is prd.md's stated core risk — the sizing calculator is now implemented with its own methodology (not a passthrough to any installer's number), satisfying independence — but the methodology's constants are industry-standard defaults pending a solar-engineering domain-expert review (see open-questions.md #1). Not a blocker for MVP functionality, but flagged for pre-launch legal/technical sign-off.
- `installation_quotes.deviation_flag` is still hardcoded to `false` in `InstallationController::submitQuote()` (see its TODO) — the quote-vs-sizing-calculation deviation check described in user-flows.md step 4 isn't wired up yet, pending open-questions.md #6's threshold decision. Assumption for a future pass: pick a reasonable default threshold (e.g. 15%) rather than leaving this unimplemented indefinitely, and note the assumption when done.
- Financing/installment plans are explicitly out of MVP scope pending a business decision on lending partnerships (see prd.md and open-questions.md) — do not build this.
- No local MySQL/Postgres instance available in this dev environment, so the sizing endpoint was verified end-to-end for validation + calculation logic (via direct unit-style smoke test and live HTTP requests against the running PHP server) but not against a real database insert; the DB insert path reuses the same pattern already used by every other controller in this codebase (e.g. InstallationController), so it's presumed correct pending real deployment verification.

## Changelog
- 2026-09-17: Initial checklist created (assumed generic scope, not sourced from planning/)
- 2026-09-18: Rewritten against planning/04-solar-co-ke and shared-architecture.md; checklist now reflects the roadmap's authoritative MVP cut and points at existing controllers to verify rather than assuming a blank slate
- 2026-09-18: Implemented the system-sizing calculator's actual methodology (SizingController::calculate()), built a real appliance-entry UI, added a standalone smoke test (tests/sizing_calculator_smoke.php, 6/6 passing), and updated README.md/tests/README.md to reflect the calculator is no longer a stub. This was the platform's stated highest-priority feature (core risk mitigation for mis-sizing liability).
