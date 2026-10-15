# Documentation

[plan.md](plan.md): build order and decisions waiting on the product owner.

## Platform

- [platform/tenancy.md](platform/tenancy.md): how barangays are isolated in one database, and how central staff switch between them.
- [platform/feature-flags.md](platform/feature-flags.md): the flag catalog, how a flag's state is resolved, and how to turn flags on and off.
- [platform/access-control.md](platform/access-control.md): roles, permissions, and how they are enforced.
- [platform/configuration.md](platform/configuration.md): every environment setting, its default, and boot validation.
- [platform/audit-and-logging.md](platform/audit-and-logging.md): the audit trail, log events, and request IDs.
- [platform/document-numbering.md](platform/document-numbering.md): per-barangay, per-year document numbers.
- [platform/lookups.md](platform/lookups.md): shared and barangay-specific dropdown values.

## Modules

- [modules/residents.md](modules/residents.md): resident registry, family, education and work, sectors, puroks, the shared resident picker, CSV export, barangay ID cards, statistics and reports, color tagging, and the blank intake form.
- [modules/finance.md](modules/finance.md): chart of accounts, bank accounts, vendors, budgets with versioned revisions and derived fund balances, other collections, and the consolidated collection report (part A); disbursement vouchers, checks, and cash-advance liquidation (part B).
- [modules/forms.md](modules/forms.md): form templates with immutable versions, the unified placeholder syntax, rich-text and fillable-PDF printing, form transactions with value snapshots, pick lists, and catalog-based saved reports.
- [modules/sms.md](modules/sms.md): text messaging through a transactional outbox and a configurable gateway port, the outbox screen, groups, templates, sends from resident lists, operations, retention, and privacy.
- [modules/import.md](modules/import.md): operator runbook for importing a data bundle into a barangay (dry run, validation, report, re-runs, exit codes).
- [modules/certificates.md](modules/certificates.md): barangay clearances, business registry and report, business clearances, expired list, the shared issued-document lifecycle, dashboard counters, cedula, blotter with the complaint hold, and indigency certificates.

## Decisions

- [adr/0001-laravel-postgresql-inertia.md](adr/0001-laravel-postgresql-inertia.md)
- [adr/0002-single-database-multitenancy.md](adr/0002-single-database-multitenancy.md)
- [adr/0003-database-backed-feature-flags.md](adr/0003-database-backed-feature-flags.md)
- [adr/0004-roles-and-permissions-in-code.md](adr/0004-roles-and-permissions-in-code.md)
- [adr/0005-resident-registry-model.md](adr/0005-resident-registry-model.md)
- [adr/0006-political-tag-isolation.md](adr/0006-political-tag-isolation.md)
- [adr/0007-money.md](adr/0007-money.md)
- [adr/0008-community-tax-calculation.md](adr/0008-community-tax-calculation.md)
- [adr/0009-derived-fund-balances-and-versioned-budgets.md](adr/0009-derived-fund-balances-and-versioned-budgets.md)
- [adr/0010-official-receipt-registry.md](adr/0010-official-receipt-registry.md)
- [adr/0011-approval-time-budget-control.md](adr/0011-approval-time-budget-control.md)
- [adr/0012-versioned-form-templates-and-saved-reports.md](adr/0012-versioned-form-templates-and-saved-reports.md)
- [adr/0013-sms-outbox-and-gateway-port.md](adr/0013-sms-outbox-and-gateway-port.md)
- [adr/0014-versioned-import-bundle.md](adr/0014-versioned-import-bundle.md)

## Import format

- [import/bundle-format-v1.md](import/bundle-format-v1.md): the versioned import bundle contract (manifest, entities, fields, reference rules, versioning). Schemas live in `resources/import-bundle/v1/`.
