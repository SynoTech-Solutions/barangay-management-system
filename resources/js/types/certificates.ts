import type { SelectOption } from './residents';

export type DocumentStatus = 'issued' | 'released' | 'cancelled';

export type IssuedDocumentFields = {
    status: DocumentStatus;
    status_label: string;
    ctc_number: string | null;
    ctc_place: string | null;
    ctc_date: string | null;
    or_number: string | null;
    amount: string;
    verified_by_name: string | null;
    certified_by_name: string | null;
    issued_by?: string | null;
    issued_at: string;
    released_by?: string | null;
    released_at: string | null;
    cancelled_by?: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    print_count: number;
    is_pending: boolean;
};

export type DocumentResident = {
    id: number;
    full_name: string;
    resident_number: string;
    is_removed: boolean;
};

export type BarangayClearance = IssuedDocumentFields & {
    id: number;
    clearance_number: string;
    control_number: string;
    resident?: DocumentResident | null;
    clearance_type_id: number;
    clearance_type?: string | null;
    purpose_id: number;
    purpose?: string | null;
    doc_stamp: string;
    total: string;
    expires_on: string;
};

export type BusinessClearance = IssuedDocumentFields & {
    id: number;
    clearance_number: string;
    control_number: string;
    business_id: number;
    business_name: string;
    business_address: string | null;
    owner_name: string;
    doc_stamp: string;
    total: string;
    expires_on: string;
    is_expired: boolean;
};

export type DocumentListFilters = {
    search: string;
    status: string | null;
    from: string | null;
    to: string | null;
    clearance_type_id: number | null;
    purpose_id: number | null;
};

export type PaymentTotals = {
    amount: string;
    doc_stamp: string;
    total: string;
};

export type BusinessOption = {
    id: number;
    business_number: string;
    name: string;
    owner_name: string;
    address: string;
    is_active: boolean;
};

export type Business = {
    id: number;
    business_number: string;
    name: string;
    owner_resident_id: number | null;
    owner?: DocumentResident | null;
    owner_name: string | null;
    owner_display_name: string | null;
    category_id: number;
    category?: string | null;
    nature_id: number;
    nature?: string | null;
    ownership_type_id: number;
    ownership_type?: string | null;
    purok_id: number | null;
    purok?: string | null;
    street_address: string | null;
    plate_number: string | null;
    operating_since: string | null;
    capitalization: string | null;
    gross_receipts: string | null;
    status: 'active' | 'inactive';
    status_label: string;
    is_active: boolean;
    last_expires_on: string | null;
    created_at: string | null;
};

export type BusinessListFilters = {
    search: string;
    status: string | null;
    category_id: number | null;
    nature_id: number | null;
    ownership_type_id: number | null;
    purok_id: number | null;
};

export type BusinessFormOptions = {
    lookups: Record<string, SelectOption[]>;
    puroks: SelectOption[];
    statuses: SelectOption<string>[];
};

export type DashboardCounter = { key: string; label: string; count: number };

export type DocumentPayment = {
    or_number: string | null;
    amount: string;
    doc_stamp: string | null;
    total: string;
    ctc: {
        number: string | null;
        place: string | null;
        date: string | null;
    } | null;
    print_count: number;
    is_reprint: boolean;
    qr: string | null;
};

export type PrintHeader = {
    barangay: string;
    municipality: string;
    province: string | null;
    telephone: string | null;
};

export type Certifier = {
    name: string | null;
    title: string | null;
    signature: string | null;
};

export type CommunityTaxBreakdown = {
    v: number;
    rule: string;
    tax_year: number;
    exempt: boolean;
    inputs: {
        business_gross_receipts: string;
        salaries: string;
        real_property_income: string;
    };
    basic: string;
    business: string;
    salary: string;
    real_property: string;
    additional: string;
    additional_capped: boolean;
    subtotal: string;
    interest_applied: boolean;
    interest_months: number;
    interest_basis_points: number;
    interest: string;
    total: string;
};

export type Cedula = Omit<
    IssuedDocumentFields,
    'ctc_number' | 'ctc_place' | 'ctc_date' | 'or_number'
> & {
    id: number;
    cedula_number: string;
    form_number: string | null;
    tax_year: number;
    place_of_issue: string;
    resident?: DocumentResident | null;
    is_exempt: boolean;
    business_gross_receipts: string;
    salaries: string;
    real_property_income: string;
    basic_tax: string;
    additional_tax: string;
    interest: string;
    breakdown: CommunityTaxBreakdown;
};

export type CedulaTemplate = {
    id: number;
    name: string;
    is_exempt: boolean;
    business_gross_receipts: string;
    salaries: string;
    real_property_income: string;
    is_active: boolean;
};

export type CedulaReference = {
    id: number;
    cedula_number: string;
    tax_year: number;
    ctc_number: string;
    ctc_place: string;
    ctc_date: string;
};

export type IndigencyCertificate = Omit<
    IssuedDocumentFields,
    'ctc_number' | 'ctc_place' | 'ctc_date' | 'or_number'
> & {
    id: number;
    certificate_number: string;
    resident?: DocumentResident | null;
    purpose_id: number;
    purpose?: string | null;
};

export type BlotterStatus = 'open' | 'resolved' | 'cancelled';

export type BlotterListItem = {
    id: number;
    blotter_number: string;
    incident_at: string;
    incident_type: string | null;
    status: BlotterStatus;
    status_label: string;
    complainant_count: number;
    respondent_count: number;
};

export type BlotterParty = {
    id: number;
    role: 'complainant' | 'respondent';
    role_label: string;
    resident_id: number | null;
    resident: {
        id: number;
        full_name: string;
        resident_number: string;
        birthdate: string | null;
        age: number | null;
        is_deceased: boolean;
    } | null;
    name: string | null;
    address: string | null;
    display_name: string;
};

export type Blotter = {
    id: number;
    blotter_number: string;
    incident_type_id: number;
    incident_type: string | null;
    incident_at: string;
    place: string | null;
    narrative: string;
    status: BlotterStatus;
    status_label: string;
    is_open: boolean;
    reported_at: string;
    recorded_by: string | null;
    resolved_at: string | null;
    resolved_by: string | null;
    resolution_notes: string | null;
    cancelled_at: string | null;
    cancelled_by: string | null;
    cancel_reason: string | null;
    parties: BlotterParty[];
    photos?: Array<{ id: number; created_at: string | null }>;
};

export type BlotterListFilters = {
    search: string;
    status: string | null;
    incident_type_id: number | null;
    from: string | null;
    to: string | null;
};

export type ResidentBlotter = {
    id: number;
    blotter_number: string;
    incident_at: string;
    incident_type: string | null;
    status: BlotterStatus;
    status_label: string;
    role: string;
    role_label: string;
};
