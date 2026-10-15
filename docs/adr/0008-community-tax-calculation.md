# 0008. Community tax computed by the system, breakdown stored per certificate

Date: 2026-10-19. Status: accepted.

## Context

The community tax has a basic amount, an additional amount derived from income components, statutory caps, and interest for late payment. If staff type every amount, the additional tax can be counted twice (once as a typed total and again through its components) and the statutory limits go unenforced. Decision D6 defaults to computing the tax per the Local Government Code of 1991 (RA 7160), sections 157 to 161.

## Decision

`App\Domain\Cedula\CommunityTaxCalculator` is pure: it receives the tax year, the payment date, the inputs in centavos, and whether interest applies. It never reads the clock, config, or flags.

Individuals only. Cedulas are issued to residents; juridical persons wait for a decision (`docs/plan.md` D16).

| Item              | Rule                                                                                                                                                  | Constant                                       |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| Basic tax         | PHP 5.00. PHP 1.00 when the taxpayer is exempt and pays voluntarily                                                                                   | `BASIC_TAX`, `EXEMPT_BASIC_TAX`                |
| Additional tax    | PHP 1.00 for every full PHP 1,000 of each component: business gross receipts, salaries or professional earnings, real property income                 | `INCOME_BRACKET`, `TAX_PER_BRACKET`            |
| Bracket fractions | Each component is floored to full brackets before the rate applies. PHP 1,999.99 is one bracket. Components are floored separately                    | `bracketTax()`                                 |
| Cap               | The sum of the three components is capped at PHP 5,000.00                                                                                             | `ADDITIONAL_TAX_CAP`                           |
| "Additional"      | Always the sum of the components. There is no separate additional field, so double counting is impossible                                             |                                                |
| Interest          | Behind `cedula-interest` (default off). 2% per month (24% per year) on basic plus additional when paid after the last day of February of the tax year | `DUE_MONTH`, `INTEREST_BASIS_POINTS_PER_MONTH` |
| Month count       | Full or partial months since February: any day in March is 1 month, December is 10, February of the next year is 12                                   | `interestMonths()`                             |
| Interest cap      | 12 months (24%)                                                                                                                                       | `MAX_INTEREST_MONTHS`                          |
| Rounding          | Integer centavos (ADR 0007). `Centavos::percentOf()` rounds half up; with whole-peso subtotals and 2% steps interest is always exact                  |                                                |

The tax year is the issue year. February 29 counts as on time in leap years.

## Stored breakdown

Each `cedulas` row stores the inputs, every computed amount as `numeric(12,2)` columns (basic, each component, additional, interest, total in `amount`), and a `breakdown` jsonb document with `"v": 1`, the rule name (`lgc-1991-individual`), inputs, amounts, whether the cap applied, interest months, and basis points. Lists, prints, and the CTC lookup read the stored values, so a later rule or flag change never alters an issued certificate. A new rule adds a new `rule` name and, if the shape changes, `"v": 2`; readers accept every version listed here.

| v   | Shape                                                                                                                                                                                          |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | `rule`, `tax_year`, `exempt`, `inputs{3}`, component taxes, `additional`, `additional_capped`, `subtotal`, `interest_applied`, `interest_months`, `interest_basis_points`, `interest`, `total` |

## Consequences

- Staff no longer type tax amounts; they type income figures. The form shows the computation before saving through `GET /cedulas/quote`.
- Presets (`cedula-templates`) hold income figures, not tax amounts.
- Cedulas are not editable: cancel and issue a new one, so an issued certificate never changes after printing.
- This ADR is not legal advice; the product owner confirms the rule set under D6.
