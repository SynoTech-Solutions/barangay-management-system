# 0003. Feature flags in the database, catalog in code

Date: 2026-10-15. Status: accepted.

## Context

Config cannot differ per barangay and needs a redeploy or config reload to change. Many behaviors are optional or sensitive (SMS, fingerprints, political color tags, check printing layouts) and must be switchable per barangay without a deploy.

## Decision

- The flag list, defaults, dependencies, owner area, and kind are code (`FeatureFlag` enum and `FlagCatalog`), reviewed in pull requests.
- Overrides are rows in `feature_overrides` (global or per barangay), written through `FeatureFlags`, audited, and cached briefly.
- An environment kill switch (`BMS_FEATURES_FORCED_OFF`) beats every override.
- Resolution is a pure function (`FlagResolver`) with unit tests.

Laravel Pennant was evaluated. It stores per-scope values but has no dependency rules, no forced-off layer, and no catalog metadata; the custom resolver is about 80 lines.

## Consequences

- Adding a flag needs an enum case, a catalog entry, and lang text; a test fails if lang text is missing.
- Release flags must carry a removal date; a test fails otherwise.
