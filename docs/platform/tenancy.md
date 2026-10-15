# Tenancy

One PostgreSQL database serves every barangay. Each barangay is a tenant.

## Data placement

| Kind of table                        | `barangay_id`               | Examples                                                                                                     |
| ------------------------------------ | --------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Tenant data                          | Required, not null          | `barangay_profiles`, `signatories`, `document_sequences`, every future resident, document, and finance table |
| Mixed (shared rows plus tenant rows) | Nullable; null means shared | `lookup_values`, `feature_overrides`, `roles`, `audit_events`                                                |
| Global reference                     | None                        | `regions`, `provinces`, `municipalities`, `barangays`                                                        |

Users carry `barangay_id`. A null `barangay_id` marks global staff (central office, municipal viewers).

## Current barangay

`App\Tenancy\CurrentBarangay` holds the barangay for the current request or job. `ResolveCurrentBarangay` middleware sets it:

- Barangay staff: always their own barangay. They cannot switch.
- Global staff: the barangay chosen in the switcher (session key `bms.active_barangay_id`), only while they hold `barangays.switch`. Otherwise none.

Routes that work on tenant data use the `barangay` middleware, which answers `409 missing_barangay_context` when no barangay is selected.

## Isolation

Tenant models use the `BelongsToBarangay` trait:

- A global scope adds `where barangay_id = <current>` to every query.
- With no current barangay, the scope throws `missing_barangay_context`. Queries fail closed instead of returning every tenant's rows.
- `creating` fills `barangay_id` from the current barangay.
- `Model::acrossBarangays()` removes the scope. Use it only in central reporting gated by `cross-barangay-reporting` and `reports.cross-barangay`.

Jobs and commands that touch tenant data must wrap the work in `CurrentBarangay::runAs($barangay, fn () => ...)`.

Controllers that load records by route key (for example `users/{user}`) also check that the record belongs to the current barangay and answer 404 otherwise.

## Suspension

A suspended barangay (`barangays.status = suspended`):

- Its users cannot sign in, and active sessions end on the next request.
- Central staff can still switch into it to inspect or fix data.

## Rejected alternatives

- **One database or schema per barangay.** Migrations run N times, cross-barangay reports need federation, and connection pools grow with the tenant count. Separate databases would also need a synchronization service to feed central reports.
- **PostgreSQL row-level security as the only guard.** It is a good second layer for a later release; the application scope is simpler to test and gives clear errors.

## Log and audit fields

Every log line inside a request carries `request_id`, `user_id`, and `barangay_id` (Laravel Context). Audit rows store `barangay_id` directly.
