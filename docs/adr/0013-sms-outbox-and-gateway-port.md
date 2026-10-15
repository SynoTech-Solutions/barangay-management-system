# 0013. SMS through a transactional outbox and one gateway port

Date: 2026-10-24. Status: accepted.

## Context

Barangays send text messages to residents through an SMS gateway. The gateway product is not chosen yet (decision D43), so the system cannot depend on any one vendor. A message must never go out for a change that rolled back, and a cancelled message must never go out at all. Delivery results and errors must be recorded, and numbers and text must not be kept forever.

## Decision

- **Transactional outbox.** `sms_messages` (tenant) holds one row per recipient with status `queued → sending → sent | failed`, or `queued → cancelled`. `SmsOutbox::queue()` inserts the batch, the rows, and the audit row in the caller's transaction. Each row gets a `SendSmsMessage` job that implements `ShouldQueueAfterCommit`, so a rollback discards both rows and jobs.
- **Claim under a row lock.** The job re-reads the row with `SELECT … FOR UPDATE` and moves it to `sending` only if it is still `queued`. Cancel takes the same lock and accepts only `queued` rows. The gateway call happens after the claim commits and outside any lock; the result is recorded under the lock again.
- **Gateway port.** `App\Services\Sms\SmsGateway::send(OutgoingSms): SendResult` with three outcomes: accepted (with a provider reference), retryable, permanent. Retries, backoff, attempt caps, and rate limits live in `SmsDelivery`, not in adapters.
- **Transport is configuration, not a flag.** `BMS_SMS_DRIVER` = `log` | `http` | `null`, validated at boot by `BmsSettings`. The `sms` flag decides whether a barangay may send at all; the driver decides how messages leave the server and is the same for every barangay. The first real adapter is a generic JSON-over-HTTP client; a vendor-specific adapter is one more class and one more enum case.
- **Recovery.** `bms:sms:dispatch-stuck` requeues rows left in `sending` by a dead worker, capped by `BMS_SMS_MAX_ATTEMPTS`. Delivery is therefore at least once; the `reference` field (the row ID) lets a gateway drop repeats.
- **Privacy.** Audit and logs carry counts and last-four masked numbers only. `bms:sms:prune` erases text and numbers of finished rows after `BMS_SMS_RETENTION_DAYS` while keeping the rows.

## Rejected alternatives

- **Send inside the request.** Ties page latency to the gateway and sends for transactions that later roll back.
- **A separate service that polls the outbox table.** Works, but a queue job per row gives retries, backoff, rate limits, and failure hooks for free and needs no extra daemon.
- **One flag per transport (`sms-gateway-forwarding`, `sms-gsm-modem`).** A transport is an operator setting with credentials, not a per-barangay behavior; mixing them would let a barangay admin switch a server's credentials on or off.

## Consequences

- A queue worker and the scheduler must run in every environment that sends (README, Deploy and Operations).
- Tests use the `sync` queue to prove after-commit dispatch, because `Queue::fake()` records jobs immediately and ignores transactions.
- Production use is blocked on D43: until the gateway product is known, use `log` or `null`, or point `http` at a gateway that accepts the documented JSON contract.
