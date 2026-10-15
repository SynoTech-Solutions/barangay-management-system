# Forms engine and saved reports

Configurable certificates, permits, and legal forms (templates, field definitions, pick lists, form transactions, rich-text and fillable-PDF printing) and saved report definitions built from a column catalog instead of free-form SQL.

## What it does

| Area               | Behavior                                                                                                                                                                                                                                                           |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Template list      | Filter by template type and name; inactive templates hidden unless asked. Number `FT-000001` from the allocator at save                                                                                                                                            |
| Template kinds     | Rich text (`forms-rich-text-templates`) or fillable PDF (`pdf-form-templates`). The kind is chosen once at creation                                                                                                                                                |
| Template types     | Lookup type `form-template-type` (shared seed plus barangay values) in Administration → Lookup values                                                                                                                                                              |
| Rich-text editor   | `contenteditable` with a small toolbar (bold, italic, underline, headings, paragraph, lists, alignment, table, clear) and a placeholder panel that inserts tokens. The server sanitizes every save                                                                 |
| Preview            | Draft preview in the editor (`POST /forms/templates/preview`, JSON) and a print preview of any saved version, both with sample values from `lang/en/forms.php`. PDF versions preview as a filled PDF                                                               |
| Fields             | Per version: key (slug, `^[a-z][a-z0-9_]{0,39}$`), label, input type, required flag, order (up and down buttons). Keys and labels are validated on the server                                                                                                      |
| Placeholder help   | `/forms/tokens` and the editor side panel list every placeholder with its meaning                                                                                                                                                                                  |
| PDF template       | Upload up to `FormTemplateRequest::PDF_MAX_KILOBYTES` (5 MB) to private storage; AcroForm fields are read and listed; each fillable field is mapped to a placeholder or left blank; check text per field                                                           |
| Delete             | Deactivate and reactivate. Nothing is deleted; issued forms keep their version                                                                                                                                                                                     |
| New form           | Resident from the shared picker (complaint hold badge), template (active, kind enabled), values per field, optional OR number and amount. Number `FX-{year}-000001` at save. Auto print with `forms-auto-print-on-save`                                            |
| Input types        | `text` (memo), `resident` (picker; prints the resident's name), `table` (pick list), `check`, `date`, `amount`                                                                                                                                                     |
| Pick lists         | Barangay lists with values (`forms-table-picklists`); add, rename, hide, restore. A table field names its list                                                                                                                                                     |
| Edit               | Only while for releasing; resident and template version are fixed                                                                                                                                                                                                  |
| Print              | Rich text: HTML print page (Letter or A4) with reprint marker. PDF: filled and flattened PDF streamed inline with `Cache-Control: private, no-store`. Each print counts and is audited                                                                             |
| Report             | Filters: name or number, status, template type, template, date range. Pagination, total amount (not cancelled), CSV export (`grid-excel-export`), print view (`grid-print`). With a template filter, CSV and print add one column per field of its current version |
| Release and cancel | Shared lifecycle: bulk, all or nothing under row locks, cancel needs a reason, release behind `document-release-step`                                                                                                                                              |
| Resident page      | "Forms" tab with the resident's forms, edit, print, and "New form" pre-filled with the resident                                                                                                                                                                    |
| Dashboard          | "Forms for release" counter                                                                                                                                                                                                                                        |
| Collections        | Forms with an amount appear in the consolidated collection report as source "Form"                                                                                                                                                                                 |
| Saved reports      | Behind `sql-report-builder` (off). Pick a dataset, columns, conditions, sort, and optional grouping; save; run paginated; export CSV. No SQL is entered anywhere                                                                                                   |

Not built:

- Saved grid column visibility and order (`grid-layout-persistence`). Every grid shows a fixed column set.
- A visual report designer: `report-designer` stays off and has no screens.
- `custom-print-templates` stays off and has no screens.
- Placeholders in clearance layouts. Clearance layouts stay built in (`docs/modules/certificates.md`); the placeholder syntax applies to form templates only.
- Print settings for saved reports; the result screen and CSV are the outputs.

Built-in print pages cover the other printouts: list prints per module (for example `collections/ListPrint.vue`, `reports/ResidentsPrint.vue`, and `forms/transactions/ListPrint.vue`), the resident profile (`residents/Print.vue`, `resident-profile-print`), the disbursement voucher (`finance/vouchers/Print.vue`), and the liquidation report (`finance/liquidations/Print.vue`).

## Placeholder syntax

One syntax for every template: `{{namespace.key}}`, optional spaces inside the braces (`{{ resident.full_name }}`). Namespaces:

| Namespace   | Keys                                                                                                                                                                                                                                               | Source                                              |
| ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| `resident`  | `full_name`, `first_name`, `middle_name`, `last_name`, `name_extension`, `title`, `resident_number`, `address`, `street_address`, `purok`, `age`, `birthdate`, `birthdate_long`, `birthplace`, `gender`, `civil_status`, `nationality`, `religion` | Snapshot at issue                                   |
| `barangay`  | `name`, `municipality`, `province`, `locality`, `telephone`                                                                                                                                                                                        | Snapshot at issue                                   |
| `signatory` | `punong_barangay`, `punong_barangay_title`, `secretary`, `secretary_title`, `treasurer`, `treasurer_title`                                                                                                                                         | Snapshot at issue                                   |
| `document`  | `number`, `or_number`, `amount_paid`, `date`, `given_day`, `given_month`, `given_year`, `given_date`                                                                                                                                               | The form's own columns; dates from the issue date   |
| `field`     | The template version's field keys                                                                                                                                                                                                                  | Values entered on the form, formatted by input type |

The catalog is `App\Domain\Forms\PlaceholderCatalog`; labels are in `lang/en/forms.php`. `resident.address` follows `default-address` like the clearances. `resident.age` is whole years on the issue date. `document.given_date` reads "Given this 5th day of January, 2026 at Barangay Uno, Sample Town, Sample Province, Philippines." `document.date` and the `given_*` placeholders use the issue date, never the print date.

Government ID numbers are never offered as placeholders, and political color tags never print (ADR 0006). Physical, health, income, contact, deceased, and sector data are not offered yet; add them to the catalog if a real form needs one (plan D37).

Rendering (`App\Domain\Forms\TemplateRenderer`) is one pure pass: each token is replaced by its value HTML-escaped (`htmlspecialchars`, quotes included), with line breaks turned into `<br>`. Values are never scanned again, so a value that contains a token stays literal. A token without a value stays visible as typed and is listed as missing. Saving a template with an unknown token is refused (`form_template_unknown_tokens`).

Formatting per input type (`FieldValueFormatter`): `date` prints as "January 5, 2024" (stored `Y-m-d`); `amount` prints with thousands separators and two decimals through `Centavos`; `check` prints `forms.check.checked` or `forms.check.unchecked` ("Yes" or "No"); `resident` prints the chosen resident's name (first, middle, last, extension); `table` prints the chosen value's label; `text` prints as typed.

## Versions and snapshots

- Every save of a template's body, PDF file, fields, or PDF mappings writes a new `form_template_versions` row (`version` n + 1) with its own `form_template_fields` and `form_pdf_mappings`. A save that changes only the name or type does not. `form_templates.current_version_id` points at the newest.
- Saved versions are immutable: triggers on the three version tables reject update and delete. Old versions stay listed and previewable on the template page.
- A form stores `form_template_version_id` and `snapshot` (jsonb, `{"v":1, "fields": [...], "tokens": {...}}`): each field's key, label, input type, raw value, and formatted display, plus every resident, barangay, and signatory token resolved at issue. Printing renders the stored version with the snapshot and the form's own number, OR number, amount, and issue date; it never reads live resident data, so a reprint shows exactly what was issued and reports keep every field the form had. Readers accept `v` 1.
- A template header, its version, fields, and PDF mappings are saved in one transaction; the template number is allocated once, at creation. Bodies are stored in a `text` column; requests accept up to `FormTemplateRequest::BODY_MAX` (200,000) characters.
- The forms report adds field columns from the version's keys; no table is created per report.
- Editing a pending form replaces the field values and payment; the resident and signatory tokens keep their issue-time values (D10, D35).

## Sanitization

`App\Domain\Forms\RichTextSanitizer` rebuilds every rich-text body from the PHP 8.4 HTML5 parser (`Dom\HTMLDocument`) with an allowlist, on save and again before render:

- Kept elements: `p br hr h1-h4 strong b em i u s sub sup ul ol li table thead tbody tfoot tr th td blockquote div span`.
- Kept attributes: `text-align` (left, right, center, justify) rebuilt as a `style`; `colspan` and `rowspan` 1 to 20 on cells. Everything else is dropped, so `on*` handlers, `href`, `src`, and style expressions never survive.
- Removed with their content: `script`, `style`, `iframe`, `object`, `embed`, `svg`, `math`, form controls, `template`, and similar. Other unknown elements are unwrapped and their text kept.
- Text is re-escaped. Images are not supported yet (plan D36).

Print pages insert the rendered HTML with `v-html`; it is safe because the body is sanitized and every value is escaped.

## PDF templates

- Upload: `mimes:pdf`, at most 5 MB, stored under `StoragePaths::formTemplatePdf()` (`barangays/{id}/form-templates/{ulid}.pdf`) on the private disk with its SHA-256, size, and original file name. Replacing the file creates a new version; the old file stays for the old version.
- Field reading: `App\Domain\Forms\Pdf\AcroFormReader`, a small pure-PHP parser that reads uncompressed objects and Flate object streams, walks `/AcroForm /Fields` and `/Kids`, builds full names, and records checkbox on-states. Encrypted files, files without fields, and non-PDF files are refused (`pdf_form_unreadable`). Field types come from the file, never from the browser.
- Mapping: each fillable field (text, check box, choice) maps to a placeholder or stays blank. Mappings to fields that are not in the file are dropped on save.
- Check boxes: a check input mapped to a PDF check box ticks it (on-state from the file) or sets `/Off`. A check input mapped to a PDF text field prints the mapping's "text when checked" and "text when not checked" (defaults: the "Yes" label and blank). Forms that need other marks, such as `1` and `X`, set those texts per field; nothing is hardcoded.
- Fill and flatten: `App\Services\Forms\Pdf\PdfFormFiller` with two adapters. `QpdfFormFiller` writes the values with `qpdf --update-from-json`, generates appearances, and flattens all annotations in one `qpdf` run (exit codes 0 and 3 accepted, timeout `BMS_PDF_FILL_TIMEOUT_SECONDS`), working in a per-call temporary directory that is always removed. `UnconfiguredPdfFormFiller` answers `pdf_filler_not_configured` (HTTP 503) when `BMS_PDF_QPDF_BINARY` is empty. Tests use `Tests\Support\FakePdfFormFiller`; the qpdf integration test runs when `qpdf` is installed.
- Operational dependency: `qpdf` 11 or newer (verified with 12.4 in the development container). Install the distribution package and set `BMS_PDF_QPDF_BINARY` to its absolute path. Without it, rich-text forms work and PDF templates can be uploaded and mapped, but PDF printing and PDF preview answer 503.
- Each print streams a new file from memory; nothing is written next to other prints, so concurrent prints cannot overwrite each other and no filled PDF stays on disk.

No maintained PHP library fills AcroForms without a native tool: FPDI imports pages but drops form fields (and needs a paid parser for compressed PDFs), TCPDF and FPDF create but do not fill, and SetaPDF-FormFiller is commercial. See ADR 0012.

## Saved reports

`sql-report-builder` (module, default off; label "Saved report builder") gates `/reports/saved`. Definitions never contain SQL.

- Catalog: `App\Domain\Reports\DatasetCatalog` lists the columns of seven datasets with a type (`text`, `number`, `money`, `date`, `boolean`, `choice`), groupability, and sensitivity. SQL expressions live only in `App\Queries\SavedReportQuery::EXPRESSIONS`, typed `literal-string`, so PHPStan refuses any user text in raw SQL.

| Dataset               | Needs                                    | Columns                                                                                                                                                                                |
| --------------------- | ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `residents`           | `residents.view`                         | Number, names, birthdate, age, gender, civil status, purok, street, mobile, email, voter and precinct, sector flags, deceased, registered on; color tag and government IDs (sensitive) |
| `barangay-clearances` | `clearances.view`, `barangay-clearance`  | Numbers, resident, status, type, purpose, OR, amount, documentary stamp, issued on, valid until                                                                                        |
| `business-clearances` | `clearances.view`, `business-clearance`  | Numbers, business, owner, status, OR, amount, documentary stamp, issued on, valid until                                                                                                |
| `cedulas`             | `cedula.view`, `cedula`                  | Numbers, resident, tax year, status, exempt, basic and additional tax, interest, amount, issued on                                                                                     |
| `collections`         | `collections.view`, `other-collections`  | Number, type, payer, OR, amount, paid on, cancelled                                                                                                                                    |
| `vouchers`            | `vouchers.view`, `finance`               | Number, date, payee, payment type, status, particulars, amount (sum of items)                                                                                                          |
| `form-transactions`   | `form-transactions.view`, `forms-engine` | Number, template, template type, resident, status, OR, amount, issued on, released on                                                                                                  |

- Operators per type (`ReportOperator::for()`): text `is`, `is not`, `contains`, `starts with`, `is empty`, `is not empty`; number, money, and date add `more than`, `at least`, `less than`, `at most`; boolean `is yes`, `is no`; choice `is`, `is not` with the enum values. Values are validated per type (`FilterValueRule`) and always bound as parameters. Text equality ignores case.
- Grouping: one groupable column; the result is the group value, a count, and the sum of each selected money column.
- Definition JSON (`saved_reports.definition`, jsonb): `{"v":1,"dataset":"residents","columns":[...],"filters":[{"column","operator","value"}],"sort":[{"column","direction"}],"group_by":null}`. `ReportDefinition::fromArray()` accepts `v` 1 and refuses anything else.
- Running: the base query is the dataset's Eloquent model, so `BelongsToBarangay` (and soft deletes for residents) always apply; joins only follow foreign keys. Results are paginated through `PaginationSettings::clamp()`; CSV streams in chunks, guards formula prefixes, and writes an `exported` audit row with the dataset and columns.
- Sensitive columns: the color tag needs `political-color-tagging` and `political-tags.view`; government ID columns need `report-government-id-columns` (off) and `reports.government-ids` (barangay-admin only). Saving a definition with a column the user may not see is refused. At run time hidden columns are dropped from the output; a condition or grouping on a hidden column makes the report unavailable (`saved_report_unavailable`) instead of silently changing its rows.
- Deactivate instead of delete; deactivated reports answer 404.

## Data placement

All tables are tenant tables: `barangay_id` is not null, every index starts with it, and models use `BelongsToBarangay`.

| Table                    | Contents                                                                                                                                                                                                                                   |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `form_templates`         | `template_number` (unique), `type_id` (lookup), `name` (unique ignoring case), `kind`, `is_active`, `current_version_id`, creator and updater                                                                                              |
| `form_template_versions` | Template, `version` (unique per template), sanitized `body` or `pdf_path`, `pdf_file_name`, `pdf_size_bytes`, `pdf_sha256`, creator, `created_at`. Immutable                                                                               |
| `form_template_fields`   | Version, `key` (unique per version), `label`, `input_type`, `is_required`, `position`, `form_picklist_id`. Immutable                                                                                                                       |
| `form_pdf_mappings`      | Version, `pdf_field` (unique per version), `pdf_field_type`, `pdf_on_state`, `token`, `checked_value`, `unchecked_value`. Immutable                                                                                                        |
| `form_picklists`         | `name` (unique ignoring case), `is_active`                                                                                                                                                                                                 |
| `form_picklist_items`    | List, `label` (unique per list ignoring case), `is_active`                                                                                                                                                                                 |
| `form_transactions`      | `transaction_number`, template, version, resident, OR number, amount, `snapshot` (jsonb `v` 1), signatory snapshots, lifecycle and print columns. Extends `IssuedDocument`; OR numbers go through `official_receipts` (`form-transaction`) |
| `saved_reports`          | `name` (unique ignoring case), `dataset`, `definition` (jsonb `v` 1), `is_active`, creator and updater                                                                                                                                     |

## Flags

| Flag                              | Default | Effect when off                                                                                   |
| --------------------------------- | ------- | ------------------------------------------------------------------------------------------------- |
| `forms-engine`                    | on      | Every `/forms` route answers 404; sidebar section, resident tab, and counter hidden               |
| `forms-rich-text-templates`       | on      | Rich-text templates cannot be created, edited, previewed, or issued (`form_template_unavailable`) |
| `pdf-form-templates`              | on      | Same for PDF templates; the upload, inspect, and source routes answer 404                         |
| `forms-table-picklists`           | on      | "Pick from a list" is not offered for new fields; pick-list routes answer 404                     |
| `forms-auto-print-on-save`        | on      | After saving, the list opens instead of the print page                                            |
| `document-release-step`           | on      | Forms are released at once; release route answers 404 (shared with certificates)                  |
| `unresolved-complaint-hold`       | on      | Respondents in open blotters can get forms                                                        |
| `grid-excel-export`, `grid-print` | on      | CSV export and print view of the forms report (and CSV of saved reports) answer 404               |
| `sql-report-builder`              | off     | Every `/reports/saved` route answers 404                                                          |
| `report-government-id-columns`    | off     | Government ID columns are not offered in saved reports; requires `sql-report-builder`             |

## Permissions

| Permission                              | Allows                                                                   | Built-in roles                   |
| --------------------------------------- | ------------------------------------------------------------------------ | -------------------------------- |
| `form-templates.manage`                 | Templates, versions, previews, placeholder help, pick lists              | barangay-admin, secretary (D34)  |
| `form-transactions.view`                | Forms report, CSV, print view, resident tab, counter                     | every barangay role              |
| `form-transactions.create`              | New form, edit pending, print and reprint                                | barangay-admin, secretary, clerk |
| `form-transactions.release` / `.cancel` | Bulk release / cancel                                                    | barangay-admin, secretary        |
| `reports.view`                          | List, run, and export saved reports (plus each dataset's own permission) | every barangay role              |
| `reports.design`                        | Create, edit, deactivate saved reports                                   | barangay-admin, secretary        |
| `reports.government-ids`                | Government ID columns in saved reports                                   | barangay-admin                   |

## Audit

`form-template-created`, `form-template-updated` (name, type), `form-template-versioned` (version number and field keys), `form-template-deactivated` and `-reactivated`, `form-picklist-saved`, `form-transaction-issued`, `-updated`, `-released`, `-cancelled`, `-printed`, `saved-report-saved`, `saved-report-deactivated`, and `exported` for both CSVs. Audit rows hold field keys only (`field_keys`, `changed_field_keys`), never the template body, field values, or rendered output; saved-report rows hold columns and conditions without their values.
