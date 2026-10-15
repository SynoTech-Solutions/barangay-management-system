# Access control

## Model

- **Permission**: one action, `App\Enums\Permission` (for example `clearances.issue`). Labels in `lang/en/permissions.php`.
- **Role**: a named set of permissions. Built-in roles (`App\Enums\SystemRole`) are defined in code in `App\Domain\Access\RoleCatalog` and written to the `roles` and `role_permissions` tables by `php artisan bms:roles:sync`.
- **Assignment**: `user_roles` links a user to a role inside a barangay (`barangay_id`), or globally (null).

## Built-in roles

| Role             | Scope    | Summary                                                                                                                  |
| ---------------- | -------- | ------------------------------------------------------------------------------------------------------------------------ |
| central-admin    | global   | Everything, including adding and suspending barangays and global feature defaults                                        |
| municipal-viewer | global   | Read-only across barangays; can switch barangays and view combined reports                                               |
| barangay-admin   | barangay | Everything inside the barangay except global-only permissions                                                            |
| secretary        | barangay | Residents, puroks, IDs, clearances, businesses, blotter, forms, SMS, lookups                                             |
| treasurer        | barangay | Cedula, collections and types, budget preparation and revision, vouchers, checks, liquidations, suppliers, bank accounts |
| bookkeeper       | barangay | Voucher verification, liquidation clearing, chart of accounts, collection export                                         |
| clerk            | barangay | Data entry: residents, IDs, clearances, cedula, blotter, forms, collections                                              |
| viewer           | barangay | Read-only                                                                                                                |

## Rules

- Every barangay role holds `reports.view` (aggregate statistics and resident reports) along with `residents.view`. `blotter.view` is held only by barangay-admin, secretary, and clerk because blotter narratives are sensitive (decision D14). Political color tags need `political-tags.view`, held only by barangay-admin among barangay roles. Form templates and pick lists (`form-templates.manage`) and saved report design (`reports.design`) are held by barangay-admin and secretary (decision D34); government ID columns in saved reports (`reports.government-ids`) only by barangay-admin. Budget approval (`budget.approve`), voucher approval (`vouchers.approve`), direct checks (`checks.issue-direct`), and editing predefined accounts (`chart-of-accounts.edit-locked`) are held only by barangay-admin among barangay roles; see `docs/modules/finance.md`.

- Global-only permissions (`barangays.manage`, `barangays.switch`, `reports.cross-barangay`, `features.manage-global`) are ignored when they come from a barangay-scoped role, even if a database edit grants them.
- Inactive users have no permissions.
- Users cannot deactivate themselves or change their own roles. Another administrator must do it.
- Role assignment only accepts roles assignable in the account's scope.

## Enforcement

`Gate::before` maps any ability string that is a `Permission` value to `PermissionResolver`. Routes use `->middleware('can:users.manage')`; code uses `Gate::authorize(...)` or `$user->can(...)`. The frontend receives `auth.permissions` to hide controls; the server always re-checks.

- No account name carries special rights, and there is no master password. Access comes only from role assignments.
- Accounts created by a data import are inactive, with the role named in the bundle, until an administrator activates them and resets their password (`docs/modules/import.md`).
