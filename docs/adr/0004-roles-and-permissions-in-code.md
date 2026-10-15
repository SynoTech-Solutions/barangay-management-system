# 0004. Roles and permissions defined in code

Date: 2026-10-15. Status: accepted.

## Context

Barangay staff have different duties (secretary, treasurer, bookkeeper, clerk), and central and municipal staff work across barangays. Access must follow the duty and the barangay, not a particular account name, and some permissions (managing barangays, global flags) must never be granted inside one barangay.

## Decision

- Permissions are an enum. Built-in roles and their permission sets are code (`RoleCatalog`), synced to the database by `bms:roles:sync`.
- Assignments are per barangay (`user_roles.barangay_id`) or global (null).
- The `roles` table supports barangay-defined custom roles (`barangay_id` set); the admin UI for custom roles is not built yet.
- A small resolver plus `Gate::before` replaces a permissions package. spatie/laravel-permission was evaluated; its team mode does not distinguish global-only permissions.

## Consequences

- Changing a built-in role is a code change plus `bms:roles:sync` on deploy.
- Global-only permissions cannot leak into barangay roles, even by direct database edits.
