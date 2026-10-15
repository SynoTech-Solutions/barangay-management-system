# Audit trail and logging

## Audit trail

`audit_events` is append-only. Services write it through `AuditTrail::record()` inside the same transaction as the change, so a change and its audit row commit or roll back together.

Each row stores: barangay, actor, action (`App\Enums\AuditAction`), subject type and ID, `changes`, request ID, and IP address.

`changes` is versioned JSON: `{"v": 1, "before": {...}, "after": {...}}`. `AuditRedactor` replaces sensitive keys (passwords, tokens, two-factor secrets, signature, photo, and fingerprint paths, and government ID numbers, blotter narratives and resolution notes, form template bodies, and form value snapshots) with `[redacted]` before storage. Readers must accept every version listed here:

| v   | Shape                                                                  |
| --- | ---------------------------------------------------------------------- |
| 1   | `before` and `after` objects of changed attributes; either may be null |

Recorded today: account create, update, role change, (de)activation, password change and reset; sign-in, failed sign-in, sign-out; feature override set and cleared; lookup create, rename, (de)activation; barangay profile and signatory changes; barangay onboarding and status changes; barangay switching by central staff; resident create, update, removal, restore, photo change, and bulk purok change; family, education, and work rows; purok changes; resident CSV exports; ID card, barangay clearance, and business clearance issue, update, release, cancel, and print; business registration, update, and status change; cedula issue, release, cancel, and print; cedula preset changes; blotter record, update, resolve, cancel, and photo add and remove; indigency certificate issue, update, release, cancel, and print; political color tag changes and purges; color category changes; form template create, rename, version, (de)activation; form pick list changes; form issue, update, release, cancel, and print (field keys only, never values or rendered text); form and saved-report CSV exports; saved report save and deactivation; SMS batches queued (counts only), message cancel and retry (masked number), retention prunes (count per barangay), SMS group create, rename, (de)activation, and member changes (counts, resident IDs, or masked numbers), SMS template save and delete (title only); data import runs started and finished (entities and counts only, `bms:import:bundle`).

Administration → Audit log shows the current barangay's events (global events when no barangay is selected), newest first, paginated.

## Logs

Log messages are event names from `App\Enums\LogEvent`, with structured context. Inside a request, Laravel Context adds `request_id`, `user_id`, and `barangay_id` to every line.

| Event                                                                                                 | When                                                                                 |
| ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `auth.login.succeeded` / `auth.login.failed` / `auth.login.rejected-inactive` / `auth.logout`         | Sign-in lifecycle                                                                    |
| `features.override.set` / `features.override.cleared`                                                 | Flag changes, with flag, barangay, state, reason                                     |
| `tenancy.barangay.switched` / `tenancy.barangay.onboarded`                                            | Central staff actions                                                                |
| `access.roles.synchronized`                                                                           | `bms:roles:sync`                                                                     |
| `health.readiness.failed`                                                                             | `/health/ready` found a broken dependency                                            |
| `errors.domain`                                                                                       | A domain rule rejected a request, with error code and HTTP status                    |
| `forms.pdf.fill-failed`                                                                               | `qpdf` failed or timed out, with exit code or timeout                                |
| `sms.queued` / `sms.sent` / `sms.retry-scheduled` / `sms.failed` / `sms.skipped` / `sms.rate-limited` | SMS delivery, with message or batch ID, masked number, counts, error code            |
| `sms.log-driver.delivered`                                                                            | The `log` driver "sent" a message (masked number, no text)                           |
| `sms.stuck.requeued` / `sms.pruned`                                                                   | Stuck sweep and retention prune, with counts                                         |
| `migration.import.started` / `migration.import.finished`                                              | Data import run, with barangay, run ID, dry-run flag, and totals                     |
| `migration.import.record-failed`                                                                      | One bundle record failed, with entity, exception class, and SQL state or error class |

## Request IDs

`AssignRequestId` accepts an inbound `X-Request-Id` if it is at most 64 characters of `[A-Za-z0-9._-]`; otherwise it generates a UUID. The ID is returned in the response header, logged, and stored on audit rows. Give it to support to find the related log lines.
