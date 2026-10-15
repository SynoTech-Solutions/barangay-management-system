# Finance

Part A: chart of accounts, bank accounts, vendors, budgets (prepare, approve, copy, versioned revisions, summary, disbursement history), other collections, collection types, and the consolidated collection report. Part B: disbursement vouchers, checks, and cash-advance liquidation (see [Part B](#part-b-vouchers-checks-liquidation)).

## What it does

| Area                           | Behavior                                                                                                                                                                                                                                                                                              |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Chart of accounts              | Tree of groups (income, expense, other), account titles, and subsidiary accounts, shown depth first with natural code order. Add a group (code, name, type), a title (code, name, parent group or title), or a subsidiary (series under a title or subsidiary; code becomes `{parent code}-{series}`) |
| Edit                           | Name and remarks; group type for groups. Code, kind, and parent never change after creation                                                                                                                                                                                                           |
| Remove                         | Unreferenced accounts are deleted. Accounts with children or budget lines are deactivated instead. Predefined (locked) accounts are never deleted                                                                                                                                                     |
| Bank accounts                  | Account number (unique per barangay), account name, depository bank, branch address; deactivate and reactivate; print list                                                                                                                                                                            |
| Vendors                        | Company name (unique per barangay ignoring case), address, contact number, optional email, contact person, position, TIN; deactivate and reactivate; search; print list ("Supplier list")                                                                                                             |
| Budget period                  | One budget per fiscal year. Title defaults to "Budget {year}". The year never changes after creation                                                                                                                                                                                                  |
| Prepare                        | Amounts per posting account (active, not a group, no sub-accounts). Zero amounts are not stored. Group and title totals, income, expense, and other totals are computed                                                                                                                               |
| Approve                        | Draft to approved. The original version becomes immutable                                                                                                                                                                                                                                             |
| Copy previous                  | Into an empty draft from another approved budget's latest approved version. Accounts that are no longer active posting accounts are skipped                                                                                                                                                           |
| Revise                         | A new approved version: changed amounts plus a remark per changed account and optional version remarks. The total must not change. History shows every version with per-account deltas                                                                                                                |
| Unappropriated balance         | Income total minus expense total, computed on every load and never stored                                                                                                                                                                                                                             |
| Budget summary                 | Per account: appropriation (latest approved version), disbursed (from `FundConsumption`), available; rolled up through the tree; zero rows hidden unless asked                                                                                                                                        |
| Disbursement history           | Per posting account, from `FundConsumption::disbursements()`: paid (released or closed) voucher items with check and liquidation numbers                                                                                                                                                              |
| Collection types               | Name unique per barangay ignoring case; hide and restore instead of delete                                                                                                                                                                                                                            |
| Other collection               | Type, payer (resident from the picker or typed name), OR number (required), amount (> 0), payment date (not in the future), remarks. Number `OC-{year}-000001` from the payment year                                                                                                                  |
| Collection list                | Filters: name, number or OR number, type, payment date range, include cancelled. Footer total excludes cancelled. Edit, cancel with a reason, print                                                                                                                                                   |
| Consolidated collection report | One `UNION ALL` over barangay clearances, business clearances, cedulas, ID cards, indigency certificates, and other collections; by payment date; amount plus documentary stamp; totals by source and by type; detail rows; print; CSV export                                                         |

Not built: an accounting tickets viewer (`accounting-tickets-viewer` stays off until the source of tickets is defined), accounting reports (`accounting-reports` stays off until their layouts are defined), saved grid column layouts (`grid-layout-persistence`), a budget report printout, and a chart of accounts Excel export.

## Data placement

All tables are tenant tables: `barangay_id` is not null, every index starts with it, and models use `BelongsToBarangay`. Amounts are `numeric(12,2)` (ADR 0007).

| Table               | Contents                                                                                                                                                                                                                                              |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `accounts`          | Adjacency list: `parent_id`, `group_id` (root group), `kind` (`group`, `title`, `subsidiary`), `group_type` (groups only), `code` (unique per barangay), `name`, `level`, `remarks`, `is_locked`, `is_active`. Check constraints keep the group shape |
| `budgets`           | `fiscal_year` (unique per barangay), `title`, `status` (`draft`, `approved`), creator, approver and time                                                                                                                                              |
| `budget_versions`   | `number` (1 = original), `remarks`, creator, approver and time. Approved rows reject update and delete (trigger)                                                                                                                                      |
| `budget_lines`      | Version, account, `amount` (not negative), `remarks` (revision reason). Lines of an approved version reject insert, update, and delete (trigger)                                                                                                      |
| `bank_accounts`     | Account number (unique per barangay), account name, depository bank, branch address, `is_active`                                                                                                                                                      |
| `vendors`           | Name (unique per barangay on `lower(name)`), address, contact number, email, contact person, position, TIN, `is_active`                                                                                                                               |
| `collection_types`  | Name (unique per barangay on `lower(name)`), `is_active`                                                                                                                                                                                              |
| `other_collections` | Number, type, optional resident, `payer_name` snapshot, OR number (partial unique index among rows that are not cancelled), amount (`> 0`), `paid_on`, remarks, recorder and time, updater, cancel actor, time, and reason                            |

## Shared pieces

- **Money.** Arithmetic in integer centavos through `App\Domain\Money\Centavos`; SQL sums cast to `numeric(14,2)` text. The browser parses decimal strings to centavos only to show the running revision difference (`resources/js/lib/money.ts`); the server re-checks.
- **Fund balance.** `App\Domain\Finance\FundBalance` implements the fund-balance rule: `available = final appropriation − Σ consumption of items that are not cancelled`, a missing appropriation counts as zero, the actual (liquidated) amount replaces the original, negative inputs are rejected, and `canCover()` refuses zero, negative, and overdrawn cases. See ADR 0009.
- **`FundConsumption`** (`App\Services\Finance\FundConsumption`): the read port Part B implements. `entriesByAccount(Budget)` returns `ConsumptionEntry` lists per account; `disbursements(Budget, Account)` returns `DisbursementRecord` rows for the disbursement history. `VoucherFundConsumption` is bound in `AppServiceProvider` (Part B).
- **Tree math.** `App\Domain\Finance\AccountTree` (roll-up, leaves, depth-first order) and `BudgetTotals` (income, expense, other, unappropriated) are pure.
- **OR numbers.** Unique per barangay across ID cards, both clearance types, and other collections through the `official_receipts` registry and its partial unique index (ADR 0010). `AvailableOrNumber` reads the registry for field-level errors.
- **Standard chart.** `App\Domain\Finance\StandardChart` (generic public-sector categories). `ChartOfAccounts::installStandard()` copies it into a barangay as locked accounts at onboarding, in the development seeder, and through `php artisan bms:finance:install-chart [--barangay=PSGC]` for barangays created earlier. It does nothing when the barangay already has accounts.

## Flags

| Flag                             | Effect when off                                                                                                                              |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `finance`                        | Every `/finance` route (chart, budgets, bank accounts, vendors) answers 404; sidebar items hidden                                            |
| `budget-copy-previous`           | The copy route answers 404; the copy panel is hidden                                                                                         |
| `budget-revision`                | The revision route answers 404; the Revise button is hidden                                                                                  |
| `budget-unappropriated-row`      | `totals.unappropriated` is null and not shown                                                                                                |
| `gl-locked-account-edit` (off)   | Locked accounts cannot be edited or deactivated by anyone. On: holders of `chart-of-accounts.edit-locked` may edit them. Nobody deletes them |
| `other-collections`              | Every `/collections` route except the report answers 404                                                                                     |
| `consolidated-collection-report` | `/collections/report` routes answer 404                                                                                                      |
| `grid-excel-export`              | The report CSV export answers 404                                                                                                            |

## Permissions

| Permission                      | Allows                                                      | Built-in roles                        |
| ------------------------------- | ----------------------------------------------------------- | ------------------------------------- |
| `budget.view`                   | Budget list, worksheet, summary, history; chart of accounts | every barangay role                   |
| `budget.prepare`                | Create, rename, prepare, copy                               | barangay-admin, treasurer             |
| `budget.approve`                | Approve                                                     | barangay-admin                        |
| `budget.revise`                 | Revise                                                      | barangay-admin, treasurer             |
| `chart-of-accounts.manage`      | Add, edit, deactivate, delete accounts                      | barangay-admin, bookkeeper            |
| `chart-of-accounts.edit-locked` | Edit locked accounts while the flag is on                   | barangay-admin                        |
| `checks.view`                   | Bank account list and print                                 | every barangay role                   |
| `bank-accounts.manage` (new)    | Add, edit, deactivate bank accounts                         | barangay-admin, treasurer             |
| `vouchers.view`                 | Vendor list and print                                       | every barangay role                   |
| `suppliers.manage`              | Add, edit, deactivate vendors                               | barangay-admin, treasurer             |
| `collections.view`              | Collection list, print, consolidated report                 | every barangay role                   |
| `collections.create`            | Record and edit collections                                 | barangay-admin, treasurer, clerk      |
| `collections.cancel`            | Cancel collections                                          | barangay-admin, treasurer             |
| `collection-types.manage` (new) | Manage collection types                                     | barangay-admin, treasurer             |
| `collections.export` (new)      | Consolidated report CSV                                     | barangay-admin, treasurer, bookkeeper |

## Decisions

- **Chart scope (D19).** Each barangay gets its own copy of the standard chart instead of shared rows plus barangay additions. Adding a subsidiary converts its parent into a header, and locked entries may be edited behind a flag; with shared rows either action in one barangay would change every barangay's chart. A per-barangay copy keeps `accounts` a plain tenant table.
- **Versioned revisions (D20).** Revisions never overwrite. See ADR 0009.
- **Budget lines per version (D21).** Preparation stores lines on version 1 of a draft budget; approval freezes them. Only posting accounts take amounts; titles and groups are computed sums, so no subtotal or unappropriated rows are stored. Indentation in the tree is presentation only.
- **Zero-sum scope (D22).** A revision keeps the total of all lines unchanged. Moving money between income and expense accounts is therefore allowed; per-group balancing waits for the product owner.
- **Subsidiaries under budgeted accounts (D23).** Refused with `account_has_budget_lines`, so a posting account with amounts never turns into a header and strands them.
- **Payment date.** Documents use their issue date (`issued_at`, UTC, as stored); other collections use `paid_on`. Edits never move a collection to another date.
- **Edit metadata.** Editing a collection, check, or voucher keeps the recorder and time; `updated_by` is separate.
- **Removal.** Referenced accounts, bank accounts, vendors, and collection types are deactivated or hidden instead of deleted, so old documents keep valid references. Locked accounts are never deleted.
- **Audit.** Every write has a structured audit row; bank `account_number` and vendor `tin` are redacted.
- **Report rows.** Rows with amount plus documentary stamp equal to zero are left out because they carry no money. Indigency certificates appear only if an amount was recorded.
- **Collection numbers.** `OC-{payment year}-{counter}` from `DocumentNumberAllocator` inside the save transaction, so concurrent clerks never get the same number and a rolled-back save takes none. The series restarts each payment year.

## Part B: vouchers, checks, liquidation

### What it does

| Area                  | Behavior                                                                                                                                                                                                                         |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Voucher header        | Approved budget, payment type (cash advance, reimbursement, others), date inside the budget year, payee (vendor, resident, or typed name; vendor address and TIN are copied), particulars, responsibility center, office or unit |
| Voucher items         | Fund source (active posting account outside income groups, picked from the budget summary with its available balance), description, amount (> 0). Up to 50 items                                                                 |
| Number                | `DV-{budget year}-000001` from `DocumentNumberAllocator` inside the save transaction. Opening the form takes nothing                                                                                                             |
| Workflow              | Prepared → verified (bookkeeper) → approved (barangay-admin) → paid. Edits only while prepared or verified; an edit sends a verified voucher back to prepared                                                                    |
| Budget control        | At approval, inside the transaction, under a lock on the budget row: every account's share must be covered by its available balance (ADR 0011)                                                                                   |
| Paid                  | Releasing the voucher's check pays it: cash advances become "released, unliquidated"; other types close. "Others" closes at approval without a check while `voucher-others-auto-release` is on                                   |
| Cancel                | Reason required. Allowed until paid. An unreleased check is cancelled in the same transaction; a released check, a liquidation, or an "Others" payment blocks it. Funds return because balances are derived                      |
| Voucher print         | Payment type boxes, payee, items padded to 8 rows, total of original amounts, amount in words, check details, signatories captured at preparation and refreshed at approval. Counted and audited                                 |
| Check for a voucher   | Bank account, check number, check date, remarks. Amount is the voucher total and is not editable. One active check per voucher                                                                                                   |
| Direct check          | Vendor, amount (≥ 1.00), bank account, number, date. No voucher and no budget effect                                                                                                                                             |
| Check number          | Unique per bank account among checks that are not cancelled (partial unique index `checks_number_per_bank_unique`)                                                                                                               |
| Edit, cancel, release | Only while for releasing. Cancel needs a reason. Release needs the receiver's name and an explicit confirmation; the voucher is paid in the same transaction                                                                     |
| Check print           | Payee, date, grouped amount, amount in words, each in `*** … ***`, positioned on the check stock with named mm custom properties in `finance/checks/Print.vue`. Counted and audited                                              |
| Amount in words       | `App\Domain\Finance\AmountInWords`: half-up to centavos, single spaces, "Zero Pesos Only", "One Cent Only", negative and non-finite input rejected, uppercase as an option                                                       |
| Unliquidated list     | Released cash-advance vouchers without a liquidation                                                                                                                                                                             |
| Liquidation           | Date, actual expense for every item, refund or reimbursement computed by `LiquidationSettlement`. A refund needs an OR number (registry, ADR 0010) and OR date. Number `LR-{budget year}-000001`. The voucher closes             |
| Clearing              | Bookkeeper clears a liquidation; only then do actual amounts replace original amounts in fund balances. A reimbursement must be covered by the account's balance. Cleared liquidations are read-only                             |
| Liquidation print     | One line per item with the original amount in words; actual amounts in the column; refund, reimbursement, OR number and date (blank lines when none)                                                                             |
| Dashboard counters    | Vouchers for verification and for approval, checks for release, unliquidated cash advances, liquidations for clearing, each shown only with its permission and flags                                                             |

### Data placement

| Table               | Contents                                                                                                                                                                                                                                                           |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `vouchers`          | Number (unique per barangay), budget, payment type and status (check constraints), date, payee snapshot and optional vendor or resident, particulars, signatory name snapshots, actor and time per step, cancel reason, prints                                     |
| `voucher_items`     | Voucher, account, description, `amount` (`> 0`), position                                                                                                                                                                                                          |
| `checks`            | Optional voucher, bank account, optional vendor, payee snapshot, number, date, amount (`>= 1`), status, issue, update, release (receiver name), cancel actor, time, and reason, prints. Partial unique indexes on number per bank and one active check per voucher |
| `liquidations`      | Voucher (unique per barangay: one liquidation per voucher), number, date, OR number and date, status, recorder, updater, clearer, prints                                                                                                                           |
| `liquidation_items` | Liquidation, voucher item (unique), `actual_amount` (`>= 0`). Refund and reimbursement are computed, never stored                                                                                                                                                  |

### Flags

| Flag                          | Effect when off                                                                                   |
| ----------------------------- | ------------------------------------------------------------------------------------------------- |
| `direct-check-issuance`       | `/finance/checks/direct` answers 404; the button is hidden                                        |
| `voucher-others-auto-release` | "Others" vouchers need a check like any other voucher                                             |
| `cash-advance-liquidation`    | Every `/finance/liquidations` route answers 404; cash advances close when their check is released |
| `liquidation-clearing`        | The clear route answers 404; a liquidation is cleared when it is saved                            |
| `check-printing`              | The check print route answers 404                                                                 |
| `amount-in-words-uppercase`   | (Off by default.) On: amounts in words are uppercase on checks, vouchers, and liquidation reports |
| `dv-print-requires-check`     | Vouchers that need a check print before the check exists                                          |

### Permissions

| Permission                  | Allows                                                                | Built-in roles             |
| --------------------------- | --------------------------------------------------------------------- | -------------------------- |
| `vouchers.view`             | Voucher, check, and liquidation lists; voucher and liquidation prints | every barangay role        |
| `vouchers.create`           | Prepare and edit vouchers                                             | barangay-admin, treasurer  |
| `vouchers.verify`           | Verify                                                                | barangay-admin, bookkeeper |
| `vouchers.approve` (new)    | Approve                                                               | barangay-admin             |
| `vouchers.cancel`           | Cancel                                                                | barangay-admin, treasurer  |
| `checks.view`               | Check list                                                            | every barangay role        |
| `checks.issue`              | Issue and edit voucher checks                                         | barangay-admin, treasurer  |
| `checks.issue-direct` (new) | Issue and edit direct checks                                          | barangay-admin             |
| `checks.release`            | Release                                                               | barangay-admin, treasurer  |
| `checks.cancel` (new)       | Cancel                                                                | barangay-admin, treasurer  |
| `checks.print`              | Print checks                                                          | barangay-admin, treasurer  |
| `liquidations.manage`       | Record and edit liquidations                                          | barangay-admin, treasurer  |
| `liquidations.clear`        | Clear liquidations                                                    | barangay-admin, bookkeeper |

### Decisions

- **Approval-time budget control (ADR 0011).** Consumption starts at approval; the check runs under a lock on the budget row, which also serializes liquidation clearing and revisions. Header and items are saved in one transaction, so nothing consumes funds before approval.
- **Printed amounts.** The voucher prints the approved item amounts; the liquidation report shows both approved and actual amounts. Actual amounts count in fund balances only after clearing.
- **Fund source per item (D29).** Each item names its account, so one voucher can span accounts; a voucher with one item charges a single account.
- **"Others" (D26).** Paid without a check and closed at approval while `voucher-others-auto-release` is on; never listed for liquidation.
- **Cancel (D30).** Cancelling returns the funds and cancels an unreleased check; paid vouchers cannot be cancelled.
- **Cash advances without liquidation (D28).** With `cash-advance-liquidation` off, cash advances close when the check is released instead of waiting forever for a liquidation.
- **Check geometry (D27).** A 203 mm × 76 mm layout with named positions, until real stock is measured.
