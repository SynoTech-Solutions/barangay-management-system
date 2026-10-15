# 0012. Versioned form templates with value snapshots, one placeholder syntax, and catalog-based saved reports

Date: 2026-10-23. Status: accepted.

## Context

Barangays design their own certificates, permits, and legal forms, and reprint them long after issue. A reprint must show what was issued, even after the template wording or the resident's data changes, and reports must keep every field that a form had when it was issued. Placeholders fill templates with resident and form data; unescaped values, or values that contain placeholder text and get expanded again, would let data change the document. Fillable PDF templates must be stored privately, and concurrent prints must never share an output file. Staff also want their own reports, but free-form SQL run with the application's credentials would bypass tenant isolation and permissions.

## Decision

### Versions and snapshots

- A template (`form_templates`) is a header: number, type, name, kind, active flag, and a pointer to its current version.
- Every save of the body, PDF file, field definitions, or PDF mappings writes a new `form_template_versions` row with its own `form_template_fields` and `form_pdf_mappings`. A save that changes nothing in the content writes no version. PostgreSQL triggers reject update and delete on the three version tables, as for approved budget versions (ADR 0009).
- A form transaction stores the version it used and a versioned JSON snapshot (`v` 1) of every field value (raw and formatted) and every resident, barangay, and signatory placeholder resolved at issue. Printing renders the stored version with the snapshot and the form's own number, OR number, amount, and issue date. Live resident data is never read at print time.
- Editing a pending form replaces field values and payment only.

### One placeholder syntax

- `{{namespace.key}}` with the namespaces `resident`, `barangay`, `signatory`, `document`, and `field`. The catalog is code (`PlaceholderCatalog`); unknown tokens are refused at save.
- A pure renderer replaces tokens in one pass and HTML-escapes every value. Values are never rescanned. Missing values stay visible and are reported.
- Clearance layouts stay built in and do not use templates.

### Safe rich text

- The template body is HTML restricted to an allowlist (paragraphs, headings, inline emphasis, lists, tables, alignment, line breaks), rebuilt from the PHP HTML5 parser (`Dom\HTMLDocument`) on every save and again before rendering. All attributes except `text-align` and cell spans are dropped, so scripts, event handlers, URLs, and style expressions cannot survive.
- The editor is a `contenteditable` element with a small toolbar using the browser's editing commands. It adds no dependency. The server is authoritative, so the editor's markup quality does not affect safety. TipTap or ProseMirror would give a cleaner JSON document model but add a large dependency for a feature the sanitizer already makes safe; revisit if staff need images or complex tables (plan D36).

### PDF filling through an adapter

- Uploaded PDFs go to private storage under a random name with their hash. A pure-PHP reader lists AcroForm fields (uncompressed objects and Flate object streams, full names, checkbox on-states) so uploading and mapping work without any binary.
- Filling and flattening go through `PdfFormFiller`. No maintained, free PHP library fills AcroForms: FPDI imports pages but drops form fields and needs a commercial parser for compressed cross-reference streams; TCPDF and FPDF only create documents; SetaPDF-FormFiller is commercial. The production adapter calls `qpdf` (Apache-2.0, packaged by every major distribution): `--update-from-json` sets `/V` and `/AS`, `--generate-appearances` draws text, and `--flatten-annotations=all` merges everything into the page, in one process call per print. `pdftk` was rejected because it needs a Java runtime.
- Without `BMS_PDF_QPDF_BINARY` the unconfigured adapter answers `pdf_filler_not_configured`; tests use a fake. The text printed for a check input on a PDF text field is configured per mapping, not hardcoded.

### Saved reports instead of a SQL builder

- The `sql-report-builder` flag (label "Saved report builder") gates saved reports and stays off by default.
- A report is a versioned JSON definition (`v` 1): a dataset from a code catalog, columns from that dataset's allowlist, conditions with typed operators, sort, and one optional grouping. SQL expressions exist only as constants in `SavedReportQuery`; user input reaches the database only as bound parameters. The base query is the dataset's Eloquent model, so tenant scoping cannot be skipped.
- Sensitive columns (political color tags, government ID numbers) need both a flag and a permission. Saving one without access is refused; running a saved report hides columns the viewer may not see and refuses reports whose conditions use them.

## Consequences

- Reprints and reports are stable however templates and residents change; storage grows by one version per content save and one JSON snapshot per form.
- Template authors use the `{{namespace.key}}` syntax; the placeholder help page and the editor panel list every placeholder with its meaning.
- PDF printing depends on an operating-system package. Deployments without it still upload and map PDFs, and print rich-text forms.
- New report needs are met by adding columns or datasets to the catalog in code, reviewed like any other query. Ad hoc SQL is not possible.
- No new Composer or npm package was added.
