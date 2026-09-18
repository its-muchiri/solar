# solar.co.ke

Scaffold for the solar.co.ke marketplace platform. See `/planning/04-solar-co-ke/` for the full PRD, user flows, database schema, API spec, and open questions this scaffold implements a starting skeleton of.

## Stack
PHP (no framework, PSR-4 autoloaded) + vanilla JS/CSS + relational SQL (MySQL/MariaDB), per `/planning/00-portfolio/shared-architecture.md`.

## Design system
Tokens in `public/assets/css/tokens.css` are ported from the real artcollect.co.ke system documented in `/planning/00-portfolio/artcollect-design-system.md`, with the **platform accent set to lime** (artcollect's "pixel" lane accent) — chosen for its eco/energy association, a natural fit for solar. Only the token architecture, the collage/pixel decorative primitives, and the motion/accessibility governance rules are adopted; the heavier graffiti and 3D/diorama treatments are intentionally **not** ported here.

This platform does **not** use a FAB (see `planning/00-portfolio/design-system.md`'s assumption that construction/solar/event use a sticky in-page CTA instead) — see `public/assets/js/components/sticky-cta.js`.

**Critical-flow rule (enforced, not just documented):** `public/assets/js/pages/checkout.js` (deposit/final payment) and any mis-sizing-dispute page must never import `components/scrap.js` or any decorative module.

## Structure

```
public/                 Web root — front controller, static assets
  index.php             Front controller: bootstraps Router, dispatches request
  assets/css/           tokens.css, reset.css, main.css
  assets/js/            main.js, components/, pages/
src/
  Config/               Database connection (PDO)
  Core/                 Router, Request, Response
  Controllers/          SizingController, InstallationController, PaymentController, ReviewController, DisputeController
  Models/               Data-access classes
  Modules/              Placeholder for shared-module integration points (Escrow, KYC v2, ...)
database/
  schema.sql            Shared core tables + this platform's extension tables (system_sizing_calculations, solar_bookings, installation_quotes, ...)
routes/
  api.php               Route table — mirrors planning/04-solar-co-ke/api-endpoints.md
```

## Getting started

1. Copy `.env.example` to `.env` and fill in database + M-Pesa Daraja + card-gateway credentials.
2. Create the database and run `database/schema.sql` against it.
3. Point your web server's document root at `public/`, with all requests rewritten to `public/index.php`.
4. `composer install` if/when shared-module packages are added as dependencies.

## What this scaffold is (and isn't)

`SizingController::calculate()` implements standard off-grid/hybrid solar sizing arithmetic (daily load → panel array sized against Kenya peak-sun-hours, backup-duration load → battery capacity via depth-of-discharge, connected load → inverter rating with surge headroom) — see that controller's docblock for the methodology and constants used, and `tests/sizing_calculator_smoke.php` for edge-case coverage. It has **not** been sign-off-reviewed by a solar engineer against Kenya-specific irradiance data, and per open-questions.md #1 the platform's liability posture for its own calculator's recommendations is still an open business decision — the calculator is presented to customers as an estimate, not a guarantee. The deviation-flag threshold for installer quotes (open-questions.md #6) remains unresolved and intentionally left unimplemented rather than guessed at.

**Installer directory:** `GET /installers` (page) and `GET /api/v1/installers` (JSON) list installers who are `active` (KYC approved) and hold at least one unexpired certification, filterable by certification type (`certification=`), minimum rating (`min_rating=`) and minimum completed installs (`min_installs=`), sortable by rating / installs / name. Rating is the mean of `reviews.rating` where the installer is the reviewee; completed installs counts `solar_bookings.status = 'completed'`. Certification standing (`valid` / `expiring_soon` within 60 days / `expired`) is derived from `expires_at` at read time. The certification taxonomy (`Installer::CERTIFICATION_TYPES`) is an assumption pending open-questions.md #5. Cover photos come from a curated, visually-checked Unsplash pool in `src/Core/Photos.php`.

**Implemented in this scaffold:** sizing calculation (methodology + persistence), installations/quotes/site-survey/commissioning/warranty, payments (stub), reviews, disputes, installer onboarding (Tier 3 KYC + certification tracking), and the e-commerce store. **Not yet wired:** maintenance-subscription endpoints from `/planning/04-solar-co-ke/api-endpoints.md`.
