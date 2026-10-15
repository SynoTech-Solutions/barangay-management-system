# 0014. Versioned import bundle instead of a source-specific importer

Date: 2026-10-26. Status: accepted.

## Context

Barangays onboard with existing records (residents, documents, budgets, accounts) kept in other tools. An importer that reads a source database directly must carry that source's table names, connection settings, a test database server, and cleaning rules that only make sense for that source (date sentinels, untrustworthy flags, delimited code lists, placeholder phone numbers, code tables, schema-version detection), and CI would need a second database engine. Every new source would add another importer. The import must also be repeatable: dry runs first, interrupted runs resumed, and re-runs that never duplicate records.

## Decision

- **One generic format.** Data enters only as an import bundle: a directory with `manifest.json`, one JSON Lines file per entity, and `files/` for attachments. Format and fields: `docs/import/bundle-format-v1.md`.
- **Records are already in this system's vocabulary.** ISO dates, RFC 3339 date-times with offsets, decimal-string money (ADR 0007), enum values, clean names, mobile numbers `PhilippineMobileNumber` accepts, lookup values by label. Every source-specific transform moves to a converter maintained outside this repository. The importer only collapses whitespace.
- **Opaque keys.** Every record has a `source_key`; references use `<thing>_key`. The id map is `import_id_map (barangay_id, entity, source_key) → (target_table, target_id)`, unique on the first three, so re-runs are idempotent and resumable.
- **Contract as JSON Schema.** `resources/import-bundle/v1/` holds draft 2020-12 schemas, one per entity plus the manifest and shared definitions. Every object forbids unknown properties; the users schema forbids `password` outright. The whole bundle is validated before anything is written; one invalid record rejects the bundle, so a converter bug never produces a half import. Business rules (references, OR conflicts, uniqueness) run afterwards per record.
- **Validator package: `opis/json-schema` 2.x.** It implements drafts 2020-12, 2019-09, 07, and 06, including `format`, `if`/`then`/`else`, `propertyNames`, and `false` schemas; resolves `$ref` across local files by URI prefix without network access; is pure PHP with two small dependencies of its own (`opis/uri`, `opis/string`); is Apache-2.0 licensed and actively released (2.6.0). It was preferred to `justinrainbow/json-schema` for draft 2020-12 support and prefix-based local `$ref` resolution out of the box. Validating in the importer with the same files we publish means the contract and the check cannot drift.
- **Versioned.** `manifest.version` is checked first; unsupported versions stop with a clear message. v1 is frozen once a converter ships; breaking changes become v2 schemas plus an upgrader to the newest record shape (procedure in the format document).
- **Run mechanics:** entities in dependency order, chunked reads with per-chunk transactions and per-record retry, dry run in a rolled-back transaction, OR numbers through `official_receipts`, sequence continuation from the highest imported number, inactive users with unusable passwords, color tags only behind `political-color-tagging`, per-run audit rows with counts only, and a problem CSV with entity, key, and code only.
- **`--only` respects dependencies.** An entity whose dependency is in the bundle but neither selected nor imported by an earlier completed run is refused, instead of importing with silently missing links.

## Consequences

- This repository has no source-specific code, connection, or test service, and CI needs no second database engine.
- A converter has to be written per source and owns its data cleaning; its output can be checked with the same schemas before it ever reaches a server.
- Rejecting the whole bundle on one schema error is strict; it keeps imports reproducible and pushes fixes to the converter where they belong.
- Tests build bundles in memory with fake data (`Tests\Support\BundleWriter`, `Tests\Support\SampleBundle`), which is faster and needs no fixture database.
