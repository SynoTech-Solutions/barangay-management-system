# 0005. Resident registry model

Date: 2026-10-16. Status: accepted.

## Context

The registry must hold family members who may or may not be registered residents, sector membership that feeds statistics, birthdates that are sometimes unknown, a senior status that changes with age, and namesakes who share a full name. Fingerprint capture has been requested but needs hardware, consent, and legal review.

## Decision

- **Linked family model.** `family_members` rows belong to a resident and may point to another resident through `related_resident_id`. Free-text members stay allowed. Linking is behind `family-member-linking`.
- **Sectors in a join table.** `resident_sector` references `lookup_values` of type `sector`. No delimited strings.
- **Nullable birthdate.** Unknown is `null`. Family member birthdates are dates, not birth years.
- **Derived senior status.** Age and senior-by-age (60+) are computed by the pure `ResidentAge` class from a reference date. Membership in the senior citizens association stays a separate manual flag.
- **Duplicate warning, not a block.** Same barangay, same normalized last and first name, same birthdate, not removed: the save fails with a `duplicate` validation error until the user resends with `confirm_duplicate`. Namesakes exist; a hard block would force fake spellings.
- **No fingerprints yet.** A browser cannot drive a USB fingerprint reader directly; capture needs a local device bridge behind a `BiometricCaptureDevice` port, a consent record, and legal review of biometric processing. Until those exist, no fingerprint columns, tables, or routes exist, and `biometric-capture` and `biometric-verification` stay off.

## Consequences

- Household reports can follow resident links instead of matching names.
- Sector statistics are exact joins; adding a sector is a lookup value, not a schema change.
- Age-based reports need a reference date and cannot use a stored flag.
- Duplicate records remain possible by design; the audit row shows who confirmed the save.
- Adding biometrics later is a new migration plus a consent record, behind its own flag and permission.
