# 0002. One database, barangay as tenant

Date: 2026-10-15. Status: accepted.

## Context

Many barangays use the system, and central and municipal staff need reports that combine them. A database per barangay would need a synchronization service to feed those reports and would multiply migrations and connection pools by the tenant count. The product owner chose one central server serving many barangays.

## Decision

Single database. Tenant tables carry a non-null `barangay_id`, and Eloquent applies it through a global scope that fails closed when no barangay is set. Global staff (central office) have no barangay and switch into one per session. Details: `docs/platform/tenancy.md`.

## Consequences

- No synchronization service between sites; every barangay writes to the same database.
- Cross-barangay reports are plain queries, gated by a flag and a global permission.
- Every tenant table needs `barangay_id` and indexes that start with it.
- Offline use at a barangay without internet is not supported. If needed later, it is a separate decision.
