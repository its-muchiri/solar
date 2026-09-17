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

This is a **starting skeleton**: `SizingController` stores whatever appliance-profile input it's given but does not implement the actual sizing-calculation formula (panel/battery/inverter recommendation) — that is a genuinely platform-specific algorithm to be designed and validated, not a stub to fake. The deviation-flag threshold for installer quotes (open-questions.md #6) and the platform's liability posture for its own calculator (open-questions.md #1) are both unresolved and intentionally left unimplemented rather than guessed at.

**Implemented in this scaffold:** sizing-calculation persistence, installations/quotes/site-survey/commissioning/warranty, payments (stub), reviews, disputes, installer onboarding (Tier 3 KYC + certification tracking), and the e-commerce store. **Not yet wired:** maintenance-subscription endpoints from `/planning/04-solar-co-ke/api-endpoints.md`.
