# 0001. Laravel 13, PostgreSQL, Inertia with Vue

Date: 2026-10-15. Status: accepted.

## Context

The data is relational: residents, documents, budgets, and vouchers reference each other through many-to-many links, and amounts must be exact. The product owner chose Laravel with PostgreSQL. Barangay staff use the system from office PCs on varied connections.

## Decision

- Laravel 13 with the official Vue starter kit (Inertia 3, Vue 3, TypeScript, Tailwind 4, Fortify for sign-in and two-factor authentication).
- PostgreSQL: partial and expression indexes, `NULLS NOT DISTINCT` unique keys for mixed shared and tenant rows, `jsonb` for versioned payloads, `INSERT ... ON CONFLICT ... RETURNING` for document numbering.
- Wayfinder generates typed route helpers; `bms:contracts:generate` generates permission and flag constants so the client never hand-types them.
- Argon2id password hashing.

## Consequences

- One deployable app; no separate SPA and API to version yet. If a mobile client arrives, add a versioned `/api/v1` with the same services.
- PostgreSQL-specific SQL appears in migrations and the numbering allocator; tests run on PostgreSQL, not SQLite.
