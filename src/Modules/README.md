# Shared Modules — Integration Point

Placeholder directory. solar.co.ke is sequenced fourth (see `planning/00-portfolio/build-sequencing-roadmap.md`), after the KYC pipeline's v2 (Tier 3) depth is proven on construction.co.ke-adjacent needs and the Booking Engine's quote-based mode is proven on construction.co.ke's pattern.

Once shared modules exist, `src/Controllers/InstallationController.php`'s inline `TODO`s and installer KYC-gating (certification tracking, see `installer_certifications`) should delegate here instead of reimplementing per platform. `src/Controllers/SizingController.php`'s sizing methodology is explicitly **not** a shared-module concern — it is genuinely platform-specific domain logic that must be designed by someone with solar-engineering expertise, not delegated to a generic shared engine.
