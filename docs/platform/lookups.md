# Lookup values

Dropdown values (religions, sectors, clearance purposes, and so on) live in `lookup_values`, keyed by `type` (`App\Enums\LookupType`).

- `barangay_id` null: shared value, seeded from `database/seeders/data/lookups.php` and maintained by the central office.
- `barangay_id` set: added by that barangay; visible only there.

A barangay sees shared values plus its own. It can add, rename, hide, and restore its own values, but not shared ones (`global_lookup_read_only`).

Labels are unique per scope and type, ignoring case and repeated spaces (`lookup_label_taken`). Values are never deleted, only hidden (`is_active = false`), so old records keep a valid reference; hidden values are left out of new-entry dropdowns.

Seeded values are curated: full ABO/Rh blood types, one entry for the Aglipayan church (IFI), one hypertension entry, income brackets as ranges instead of free-typed amounts, and no purpose entries that embed a person's name. Business categories, natures, and ownership types are seeded from a generic business-permit list so a new barangay starts with a usable set.
