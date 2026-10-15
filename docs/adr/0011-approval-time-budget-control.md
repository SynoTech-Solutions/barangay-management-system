# 0011. Budget control at voucher approval, serialized by a budget row lock

Date: 2026-10-22. Status: accepted.

## Context

ADR 0009 made balances derived: `available = appropriation − Σ consumption`. A check that runs only in the browser can be bypassed and races with other users, so two people could overspend the same account at the same moment. If draft items consumed money as soon as they were typed, abandoned drafts would tie up funds. Part B decides when a voucher starts to consume and how concurrent writers are kept from overspending.

## Decision

### Consumption starts at approval

- `VoucherFundConsumption` (bound to `FundConsumption`) reports the items of vouchers that have an `approved_at`. Prepared and verified vouchers do not reduce any balance, so an abandoned draft costs nothing and no number is taken before save.
- Items of a voucher cancelled after approval are reported as cancelled, so `FundBalance` ignores them and the money is back on the next read.
- After a liquidation is **cleared**, each item reports its actual expense instead of the original amount: a refund returns the difference, a reimbursement consumes more.

### The check runs inside the approval transaction

`VoucherService::approve()` does, in one transaction:

1. Lock the voucher row (`select … for update`) and require status `verified`.
2. Lock the budget row (`FundGuard::lock()`, `select … from budgets … for update`).
3. Compute every account's balance from source rows (`FundGuard::balances()`), then require each account's share of the voucher to be covered (`FundBalance::canCover()`; zero, negative, and overdrawn balances cover nothing).
4. Mark the voucher approved (and, for "Others" with `voucher-others-auto-release`, released and closed).

Clearing a liquidation takes the same budget lock before it checks that any increase (actual above original, per account) is covered. A budget revision already locks the same row; it now also refuses to cut an account below what is approved for disbursement (`budget_revision_below_committed`).

### Why a row lock on `budgets`

A budget row is unique per (barangay, fiscal year), so locking it serializes exactly the writers that can change one year's balances: approvals, liquidation clearing, and revisions. A PostgreSQL advisory transaction lock (`pg_advisory_xact_lock`) keyed on (barangay, year) would also serialize approvals, but it lives in a separate key space that revisions do not join unless every writer remembers the same key derivation, and a hashed text key can collide with unrelated locks. The row lock needs no key scheme, is released at commit or rollback, and shows up in `pg_locks` with the table name.

Lock order is always voucher (or liquidation) first, then budget. Revisions take only the budget lock. No path takes them the other way round, so these locks cannot deadlock each other.

Under READ COMMITTED, each statement after the lock sees every approval committed before the lock was granted, so the second of two approvals that together exceed the balance is refused.

## Consequences

- A voucher can be prepared and verified while funds are short; it fails at approval with `insufficient_funds`, naming the account, the available balance, and the amount.
- Approvals for one barangay and year run one at a time. They are short (a few hundred rows read), so the wait is negligible at barangay volume.
- The budget summary and disbursement history show approved vouchers immediately; the history lists only paid vouchers (released or closed).
