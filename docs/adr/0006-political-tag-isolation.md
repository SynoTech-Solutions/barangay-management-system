# 0006. Political color tags isolated and purgeable

Date: 2026-10-17. Status: accepted.

## Context

Barangays asked to tag residents with a color whose labels are local political groupings, which makes a tag sensitive personal information. Whether to offer it at all, its legal basis, and the consent approach are unresolved. The feature must be buildable without making the data hard to remove if the legal basis is never recorded or is withdrawn.

## Decision

- Store tags outside the resident record: `color_categories` (barangay-defined name and hex color) and `resident_political_tags` (one row per resident). A tenant table instead of a `lookup_values` type, because the categories are barangay-specific, carry a display color, and must be deletable together with the tags.
- `political-color-tagging` defaults off. Viewing needs `political-tags.view`; changing needs `political-tags.manage`. Among barangay roles only barangay-admin holds them.
- Colors are added to responses only when the flag is on and the user may view them. Exports, prints, reports, and ID cards never include them.
- Every change is audited per resident. `bms:political-tags:purge --barangay=<PSGC>` deletes all tags and categories of a barangay in one transaction and audits the counts.
- The flag must stay off until the product owner records a legal basis. This ADR is not legal advice.

## Consequences

- Turning the flag off hides the data immediately; purging removes it. Audit rows keep only category IDs, not names.
- Reads of tags go through `PoliticalTagQuery`; callers check the flag and permission before using it.
