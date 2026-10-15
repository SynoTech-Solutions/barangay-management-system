# 0009. Derived fund balances and versioned budgets

Date: 2026-10-20. Status: accepted.

## Context

A barangay budget is approved once and revised during the year, and auditors need to see every approved version and what each revision moved. Overwriting amounts in place loses that history. Fund balances appear on several screens (budget summary, voucher items, approval), and they must agree; a balance stored on a document or recomputed by different code paths drifts from the source rows.

## Decision

### Budgets are versions of lines

- `budgets` holds the fiscal year, title, and status (`draft`, `approved`).
- `budget_versions` numbers versions from 1. Version 1 is prepared while the budget is a draft and approved once. Each revision inserts version n + 1 with its full set of lines and approves it in the same transaction.
- `budget_lines` belong to one version. Only posting accounts carry lines; group and title totals are computed.
- The effective appropriation is the latest approved version.
- A revision must keep the total unchanged (`BudgetRevision::imbalance() === 0`), needs a remark on every changed account, and may only increase active posting accounts.
- PostgreSQL triggers (`budget_versions_immutable`, `budget_lines_immutable`) reject update and delete of an approved version and insert, update, or delete of its lines. No code path updates them; the tests prove the database refuses.

### Balances are derived, never stored

- `App\Domain\Finance\FundBalance::of(appropriation, entries)` is the only implementation of the fund-balance rule: `available = appropriation − Σ effective amount of entries that are not cancelled`. A missing appropriation is zero. An entry's effective amount is its actual (liquidated) amount when present, else its original amount. Negative inputs are rejected. `canCover(amount)` is true only for a positive amount not above the available balance, so a zero or overdrawn fund covers nothing.
- Inputs come from source rows on every read: budget lines of the effective version and consumption entries from `FundConsumption`. There is no running counter, cached balance, or balance column. The unappropriated balance (`BudgetTotals::unappropriated()`) is computed the same way.
- Part B implements `FundConsumption` over voucher items. Cancelled vouchers report their items as cancelled, so their money returns to the fund.

## Consequences

- One source of truth: changing a line or cancelling a voucher changes every balance on the next read, with nothing to reconcile.
- History is complete: any version's amounts and the delta between two versions can be shown.
- Summaries read all lines and entries of a budget per request. A barangay budget has tens to a few hundred accounts, so this stays cheap; if Part B volumes grow, `FundConsumption` aggregates in SQL and still returns entries per account.
- A data fix to an approved version needs a new revision, or a migration that disables the trigger on purpose.
