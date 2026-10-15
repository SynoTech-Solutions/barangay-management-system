import type { IssuedDocumentFields, DocumentResident } from './certificates';
import type { SelectOption } from './residents';

export type FormInputType =
    | 'text'
    | 'resident'
    | 'table'
    | 'check'
    | 'date'
    | 'amount';

export type FormTemplateKind = 'rich-text' | 'pdf';

export type FormTemplate = {
    id: number;
    template_number: string;
    name: string;
    type_id: number;
    type?: string | null;
    kind: FormTemplateKind;
    kind_label: string;
    is_active: boolean;
    version?: number | null;
    updated_at: string | null;
};

export type FormFieldDraft = {
    key: string;
    label: string;
    input_type: FormInputType;
    is_required: boolean;
    picklist_id: number | null;
};

export type PdfField = {
    name: string;
    type: string;
    on_state: string | null;
};

export type PdfMappingDraft = {
    pdf_field: string;
    pdf_field_type?: string;
    token: string | null;
    checked_value: string | null;
    unchecked_value: string | null;
};

export type FormTemplateEditor = {
    id: number;
    version: number;
    body: string | null;
    pdf_file_name: string | null;
    pdf_fields: PdfField[];
    fields: FormFieldDraft[];
    mappings: PdfMappingDraft[];
};

export type FormTemplateVersionSummary = {
    id: number;
    version: number;
    created_at: string;
    created_by: string | null;
    field_count: number;
    pdf_file_name: string | null;
    is_current: boolean;
};

export type TokenGroup = {
    namespace: string;
    label: string;
    tokens: Array<{ token: string; label: string }>;
};

export type IssuableField = {
    key: string;
    label: string;
    input_type: FormInputType;
    is_required: boolean;
    picklist_id: number | null;
    options: SelectOption[];
};

export type IssuableTemplate = {
    id: number;
    name: string;
    type: string | null;
    type_id: number;
    kind: FormTemplateKind;
    version: number | null;
    fields: IssuableField[];
};

export type FormFieldValue = string | number | boolean | null;

export type SnapshotFieldValue = {
    key: string;
    label: string;
    input_type: FormInputType;
    value: FormFieldValue;
    display: string;
};

export type FormTransaction = IssuedDocumentFields & {
    id: number;
    transaction_number: string;
    form_template_id: number;
    template?: string | null;
    template_kind?: FormTemplateKind | null;
    template_type?: string | null;
    resident?: DocumentResident | null;
};

export type FormTransactionEditor = FormTransaction & {
    version: number;
    fields: IssuableField[];
    snapshot_fields: SnapshotFieldValue[];
};

export type FormTransactionFilters = {
    search: string;
    status: string | null;
    from: string | null;
    to: string | null;
    type_id: number | null;
    template_id: number | null;
};

export type FormPicklist = {
    id: number;
    name: string;
    is_active: boolean;
    items: Array<{ id: number; label: string; is_active: boolean }>;
};

export type ReportOperatorOption = {
    value: string;
    label: string;
    needs_value: boolean;
};

export type ReportColumnOption = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'money' | 'date' | 'boolean' | 'choice';
    groupable: boolean;
    operators: ReportOperatorOption[];
    choices: SelectOption<string>[];
};

export type ReportCatalog = {
    datasets: Array<{
        value: string;
        label: string;
        columns: ReportColumnOption[];
    }>;
    directions: SelectOption<string>[];
};

export type ReportFilterDraft = {
    column: string;
    operator: string;
    value: string | null;
};

export type ReportSortDraft = {
    column: string;
    direction: string;
};

export type ReportDefinition = {
    v: number;
    dataset: string;
    columns: string[];
    filters: ReportFilterDraft[];
    sort: ReportSortDraft[];
    group_by: string | null;
};

export type SavedReport = {
    id: number;
    name: string;
    dataset: string;
    dataset_label: string;
    definition: ReportDefinition;
    is_active: boolean;
    updated_at: string | null;
};
