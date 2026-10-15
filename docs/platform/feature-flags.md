# Feature flags

Config decides how a feature behaves. Flags decide whether it runs at all, per barangay, without a deploy.

## Catalog

The full list lives in `App\Enums\FeatureFlag`. `App\Domain\Features\FlagCatalog` records, for each flag:

| Field       | Meaning                                                                                                                                             |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| area        | Module that owns it (platform, residents, sms, certificates, forms, finance, migration)                                                             |
| kind        | `module` (a whole screen set), `option` (behavior inside a module), `ops` (dangerous operational tools), `release` (temporary, for a risky rollout) |
| default     | State when nobody has set an override                                                                                                               |
| requires    | Flags that must also be on                                                                                                                          |
| removeAfter | Removal date. Required for `release` flags; a unit test enforces it                                                                                 |

The owner of a flag is the maintainers group of its area (`FlagOwners`).

Labels and descriptions shown in the UI are in `lang/en/features.php`.

## Resolution order

`FlagResolver` is a pure function. For a flag in a barangay:

1. `BMS_FEATURES_FORCED_OFF` lists the flag: **off** (`forced-off`). This is the operator kill switch and beats everything.
2. The barangay has an override: use it (`barangay-override`).
3. A global override exists: use it (`global-override`).
4. Otherwise the catalog default (`default`).
5. If the result is on but a required flag resolves off: **off** (`missing-dependency`, with `blocked_by`).

The stored override is kept even while a dependency blocks it, so turning the dependency on restores it.

## Where flags are enforced

- Routes: `->middleware('feature:sms')`. A disabled feature answers 404 (`feature_disabled`), the same as a route that does not exist.
- Services: `FeatureFlags::enabled(FeatureFlag::X)` before optional side effects.
- Frontend: the shared `features` prop. It hides navigation only; the server always enforces.

## Changing a flag

- UI: Administration → Features. Barangay admins change their barangay (`features.manage`). Central admins change the global default (`features.manage-global`).
- CLI: `php artisan bms:features:set sms on --barangay=9902801001 --reason="pilot"`.
- Emergency: add the flag to `BMS_FEATURES_FORCED_OFF` and run `php artisan config:cache`.

Every change writes an audit row (`feature-overridden` or `feature-override-cleared`) and a log line (`features.override.set` or `features.override.cleared`).

## Caching

Overrides are cached per barangay for `BMS_FEATURES_OVERRIDE_CACHE_SECONDS` (default 60; 0 disables caching). Writes clear the cache for that scope. A global change can take up to the TTL to reach other app servers whose cache store is not shared; use a shared cache store (database or Redis) in production.

## Rebuild

`feature_overrides` is the only stored state. The catalog is code. Deleting all rows returns every barangay to defaults.

## Defaults

Sensitive or unproven features start off: `sql-report-builder` (saved reports), `report-government-id-columns`, `sms`, `biometric-capture`, `biometric-verification`, `political-color-tagging`, `database-reset`, `report-designer`, `custom-print-templates`, `cross-barangay-reporting`. They expose personal or sensitive data (government IDs, biometrics, political tags, message text), need an external service or legal decision first (SMS gateway, fingerprint readers), or have no screens yet. The module docs and `docs/plan.md` give the reason per flag.
