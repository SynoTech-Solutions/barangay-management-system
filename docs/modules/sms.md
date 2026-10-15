# SMS

Text messages to residents: compose, outbox, groups, templates, and sends from the resident list. Every send goes through a transactional outbox and one gateway port; the transport is chosen by configuration.

## What it does

| Area                | Behavior                                                                                                                                                                                |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Queue a message     | One row per recipient in `sms_messages`, inserted in the caller's transaction. One send job per row, dispatched after commit.                                                           |
| Outbox              | `/sms`: status, date range, and search (resident name, text, or a typed number) filters; pagination; bulk cancel and retry.                                                             |
| New message         | `/sms/create`: residents from the picker (only residents with a mobile number) plus typed numbers. Live segment counter. Confirmation with the recipient count.                         |
| View message        | `/sms/messages/{id}`: details, status, attempts, last error, gateway reference. "New message to this recipient" opens compose prefilled. No reply box and no inbox.                     |
| Groups              | `/sms/groups`: create, rename, deactivate, reactivate; members are residents or typed numbers; group send at `/sms/groups/{id}/message`.                                                |
| From resident lists | Resident list: "Send SMS" and "Add to SMS group" for the selected rows or for every resident matching the current filters. A confirmation page shows the count before anything happens. |
| Templates           | `/sms/templates`: title and text; the compose, group, and resident-list screens offer them to fill the message box.                                                                     |
| Resident profile    | "Send SMS" opens compose with the resident picked.                                                                                                                                      |
| Dashboard           | "Text messages waiting to send" (queued or sending) and "Text messages that failed", for users with `sms.view` while `sms` is on.                                                       |

## Outbox

| Column                                                                                                            | Meaning                                                                                                                                      |
| ----------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `recipient`                                                                                                       | E.164 (`+639XXXXXXXXX`), normalized by `PhilippineMobileNumber::toE164()`. Null after pruning.                                               |
| `resident_id`                                                                                                     | The resident the number was taken from, if any. Null after pruning.                                                                          |
| `body`, `encoding`, `segments`                                                                                    | Cleaned text (`SmsText::clean()` drops control and bidi characters), `gsm-7` or `ucs-2`, and segment count.                                  |
| `status`                                                                                                          | `queued`, `sending`, `sent`, `failed`, `cancelled` (`App\Enums\SmsStatus`, also a database check).                                           |
| `attempts`, `last_error_code`, `provider_message_id`                                                              | Gateway calls made, last error (`http_503`, `timeout`, `not_configured`, …), and the gateway's reference.                                    |
| `queued_by`/`queued_at`, `sent_at`, `failed_at`, `cancelled_by`/`cancelled_at`, `sending_started_at`, `pruned_at` | Lifecycle stamps.                                                                                                                            |
| `sms_batch_id`                                                                                                    | The send it belongs to. `sms_batches` keeps source (`compose`, `group`, `resident-list`), group, recipient and skipped counts, and segments. |

Length (decision D46): `App\Domain\Sms\SmsSegments` counts GSM-7 septets (extension characters such as `€ [ ] { } ~ ^ | \` count two) or UCS-2 code units (emoji count two). One segment holds 160 GSM-7 or 70 UCS-2 units; multi-part segments hold 153 or 67, and an escape pair or surrogate pair never splits across segments. A message may use at most `SmsSegments::MAX_SEGMENTS` (5) segments. `resources/js/lib/sms.ts` mirrors the count for the live counter; the server re-checks on every save.

Recipients are deduplicated by E.164 number per send; the first resident with that number keeps the link. A resident is skipped when removed, deceased, or without a valid mobile number. Every send reports how many were skipped.

## Gateway port and drivers

`App\Services\Sms\SmsGateway::send(OutgoingSms): SendResult`. A result is `accepted` (with an optional provider reference), `retryable`, or `permanent` (each with an error code). The binding in `AppServiceProvider` follows `BMS_SMS_DRIVER`:

| Driver          | Behavior                                                                                                                                                                                                                                                                                                  |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `log` (default) | Writes `sms.log-driver.delivered` with message ID, the number masked to its last four digits, segment and character counts, and a `log-<uuid>` reference. Nothing leaves the server. Use in development and demos.                                                                                        |
| `http`          | POST JSON `{"to", "message", "sender", "reference"}` to `BMS_SMS_HTTP_URL` with `Authorization: Bearer <BMS_SMS_HTTP_TOKEN>`. 2xx → accepted (reference from `id`, `message_id`, or `messageId`); 4xx → permanent, except 408 and 429 → retryable; 5xx, other codes, and connection timeouts → retryable. |
| `null`          | Rejects every message as permanent `not_configured`. Use where SMS must never leave.                                                                                                                                                                                                                      |

The real gateway product is not chosen yet (decision D43, blocking for production use). A vendor adapter only needs another `SmsGateway` class and a driver case. The GSM modem flag (`sms-gsm-modem`) stays off and nothing is built for it.

## Delivery

`App\Jobs\SendSmsMessage` (one job per message, `ShouldQueueAfterCommit`) calls `SmsDelivery::attempt()`:

1. Skip unless the row is still `queued`. A sent, sending, cancelled, failed, or missing message is never sent again.
2. If `sms` is now off for the barangay, mark the message failed with `feature_disabled` (staff can retry it after turning the flag on).
3. Per-barangay rate limit (`BMS_SMS_RATE_PER_MINUTE`, Laravel `RateLimiter`, key `RateLimiters::smsSend()`). When full, the job is released until the window opens; no attempt is used.
4. In a transaction, `SELECT … FOR UPDATE` the row; if it is not `queued` any more (for example cancelled a moment ago), stop. Otherwise set `sending`, add an attempt, stamp `sending_started_at`, commit.
5. Call the gateway outside the lock. An exception counts as retryable `gateway_exception`.
6. Lock the row again and record: accepted → `sent`; retryable with attempts left → back to `queued` and the job is released after `BMS_SMS_RETRY_BASE_SECONDS × 2^(attempt−1)`, capped by `BMS_SMS_RETRY_MAX_SECONDS`; otherwise → `failed`.

A job that the queue gives up on (24-hour retry window or three exceptions) marks its message `failed` with `job_failed`.

Delivery is at least once: if the worker dies after the gateway accepted a message but before the row says `sent`, the stuck sweep sends it again. The `reference` field lets a gateway drop such repeats.

## Operations

- **Queue worker.** Messages leave only while `php artisan queue:work` runs (any queue connection; production should not use `sync`).
- **Scheduler.** `php artisan schedule:work` or cron running `schedule:run` every minute. It runs:
    - `bms:sms:dispatch-stuck` every five minutes: messages in `sending` longer than `BMS_SMS_STUCK_AFTER_MINUTES` go back to `queued` with a new job (`last_error_code = stuck_in_sending`), or to `failed` when they used `BMS_SMS_MAX_ATTEMPTS`.
    - `bms:sms:prune` daily at 02:30: see Retention.
- **Cancel** (`sms.cancel`): only queued messages, all or nothing; a selection with any other status changes nothing (`sms_messages_not_queued`, listing the message IDs).
- **Retry** (`sms.send`): only failed messages that still have their text, all or nothing (`sms_messages_not_failed`). Attempts start again from zero.
- **Switching drivers** needs `php artisan config:cache` and a worker restart (`queue:restart`).

## Groups

- `sms_groups`: name unique per barangay ignoring case (`sms_groups_name_unique` on `lower(name)`), active flag. Groups are deactivated, never deleted, so members and past sends keep their group. A deactivated group cannot get members or be sent to (`sms_group_inactive`).
- `sms_group_members` (decision D50): either `resident_id` or a typed `mobile_number` stored as E.164, never both (database check), unique per group. A resident member stores no number: group send reads the resident's current number, so edits to the resident take effect and stale copies do not exist.
- Group send skips members whose resident is removed, deceased, or has no valid number, and reports the count on the confirmation screen and in the toast.

## From resident lists

The resident list shows "Send SMS" and "Add to SMS group" for selected rows, and for "all N residents matching the filters" (needs `sms-from-resident-lists`; group actions also need `sms-groups` and `sms-groups.manage`). The confirmation page (`/sms/residents/send` or `/group`) resolves the recipients with the same filters as the list and shows reachable, skipped, and merged-duplicate counts. The post must carry `confirmed_count`; if the count changed since the page was shown, nothing happens (`sms_recipients_changed`). More than `BMS_SMS_MAX_RECIPIENTS` recipients is refused (`sms_too_many_recipients`). One audit row per batch, with counts only.

## Templates

`sms_templates`: title (unique per barangay ignoring case, 50 characters) and text (same segment limit as messages). Create, edit, delete. No placeholders: the text is copied into the message box as is.

## Flags

| Flag                      | Default             | Effect when off                                                                                  |
| ------------------------- | ------------------- | ------------------------------------------------------------------------------------------------ |
| `sms`                     | off                 | Every `/sms` route answers 404; queued messages fail with `feature_disabled` when their job runs |
| `sms-groups`              | on (requires `sms`) | Group routes and "Add to SMS group" answer 404; sidebar entry hidden                             |
| `sms-templates`           | on (requires `sms`) | Template routes answer 404; compose screens offer no templates                                   |
| `sms-from-resident-lists` | on (requires `sms`) | Resident-list SMS routes answer 404; list buttons hidden                                         |
| `sms-gsm-modem`           | off                 | Nothing built                                                                                    |
| `sms-inbox`               | off                 | Nothing built (decision D44)                                                                     |

Forcing `sms` off with `BMS_FEATURES_FORCED_OFF=sms` turns every dependent flag off (`missing-dependency`).

## Permissions

| Permission             | Allows                                               | Built-in roles            |
| ---------------------- | ---------------------------------------------------- | ------------------------- |
| `sms.view`             | Outbox, message view, dashboard counters             | every barangay role       |
| `sms.send`             | Compose, group send, resident-list send, retry       | barangay-admin, secretary |
| `sms.cancel`           | Cancel queued messages                               | barangay-admin, secretary |
| `sms-groups.manage`    | Groups and members, resident-list "Add to SMS group" | barangay-admin, secretary |
| `sms-templates.manage` | Templates                                            | barangay-admin, secretary |

## Privacy

- Audit rows never hold message text or full numbers. `sms-queued` stores source, group, matched, recipient, skipped, and duplicate counts, segments, and encoding. Cancel and retry rows store the status change and the number masked to its last four digits (`***0001`, `MaskedNumber`). Group rows store counts, resident IDs, or masked numbers.
- Log lines carry message IDs, masked numbers, counts, and error codes. The HTTP token, the URL query, and the text are never logged.
- Retention (decision D45): with `BMS_SMS_RETENTION_DAYS` above 0, `bms:sms:prune` sets `recipient`, `body`, `resident_id`, and `provider_message_id` to null on sent, failed, and cancelled messages whose last stamp is older than the limit, and stamps `pruned_at`. Rows, statuses, segments, and batch counts stay, so totals remain correct. One `sms-pruned` audit row per barangay with the count. Queued and sending messages are never pruned; a pruned failed message cannot be retried.
- The outbox shows numbers and text to users with `sms.view` inside their own barangay only.

## Decisions

See ADR 0013 and `docs/plan.md` D43 to D50.
