# 0007. Money as fixed-point decimals, arithmetic in centavos

Date: 2026-10-18. Status: accepted.

## Context

Clearances, ID cards, cedulas, and the finance module add, total, and report amounts. Floating point gives totals like `0.30000000000000004`, and the finance module must reconcile to the centavo.

## Decision

- **Storage:** every amount column is `numeric(12,2)` (up to 9,999,999,999.99). Columns that can never be negative carry a `check` constraint.
- **Transport:** amounts travel as decimal strings with two places (`"150.00"`), in JSON, Inertia props, and form posts. Eloquent casts them with `decimal:2`, which yields strings. PHP code never casts an amount to `float`.
- **Validation:** Form Requests accept `decimal:0,2`, `min:0`, and a named maximum constant.
- **Arithmetic:** in SQL (`sum(amount)`, cast to `numeric(14,2)` and returned as text) for lists and reports; in PHP through `App\Domain\Money\Centavos`, which parses a decimal string into integer centavos, adds, and formats back. No `bcmath` or money library is needed for addition and totals.
- **Rounding:** input with more than two decimal places is rejected, so no rounding happens on entry. Multiplication and division (cedula rates, interest) arrive with the cedula and finance modules; they will round half-up to centavos in `Centavos` and get golden tests at that time.
- **Display:** the server sends the two-decimal string; the browser shows it as is.

ID card amounts already used `decimal(12,2)` with `decimal:2` casts and string transport, so they follow this ADR without a migration.

## Consequences

- Totals are exact. A footer sum on a list is one SQL aggregate, not a PHP loop over rows.
- Finance can join on stored OR numbers and amounts later without conversion.
- A field that needs more than 12 digits needs a new migration and an update here.
