export type SelectOption<T extends string | number = number> = {
    value: T;
    label: string;
};

export type ResidentOption = {
    id: number;
    resident_number: string;
    full_name: string;
    birthdate: string | null;
    age: number | null;
    purok?: string | null;
    is_deceased: boolean;
    on_hold?: boolean;
};

export type ResidentListItem = {
    id: number;
    resident_number: string;
    full_name: string;
    birthdate: string | null;
    age: number | null;
    is_senior: boolean;
    gender: string | null;
    gender_label: string | null;
    civil_status_label: string | null;
    purok?: string | null;
    mobile_number: string | null;
    is_registered_voter: boolean;
    is_pwd: boolean;
    is_indigent: boolean;
    is_single_parent: boolean;
    is_deceased: boolean;
    is_incomplete: boolean;
    is_removed: boolean;
    residing_since: string | null;
    length_of_stay: { years: number; months: number } | null;
};

export type LookupLabels = Record<string, string | null>;

export type ResidentDetail = {
    id: number;
    resident_number: string;
    full_name: string;
    title_id: number | null;
    last_name: string;
    first_name: string;
    middle_name: string | null;
    name_extension: string | null;
    nickname: string | null;
    birthdate: string | null;
    birthplace_municipality_id: number | null;
    gender: string | null;
    gender_label: string | null;
    civil_status: string | null;
    civil_status_label: string | null;
    nationality_id: number | null;
    religion_id: number | null;
    blood_type_id: number | null;
    body_type_id: number | null;
    health_issue_id: number | null;
    profession_id: number | null;
    skill_id: number | null;
    income_bracket_id: number | null;
    height_cm: string | null;
    weight_kg: string | null;
    national_id: string | null;
    drivers_license: string | null;
    tin: string | null;
    sss_gsis: string | null;
    pagibig: string | null;
    philhealth: string | null;
    is_registered_voter: boolean;
    voter_id: string | null;
    precinct_number: string | null;
    is_living_with_parents: boolean;
    is_senior_citizen_member: boolean;
    is_pwd: boolean;
    is_single_parent: boolean;
    is_indigent: boolean;
    purok_id: number | null;
    purok?: string | null;
    street_address: string | null;
    is_permanent_resident: boolean;
    residence_type_id: number | null;
    residing_since: string | null;
    alternate_address: string | null;
    mobile_number: string | null;
    landline_number: string | null;
    email: string | null;
    facebook_url: string | null;
    is_deceased: boolean;
    deceased_on: string | null;
    cause_of_death: string | null;
    age: number | null;
    is_senior: boolean;
    is_incomplete: boolean;
    labels: LookupLabels;
    birthplace?: SelectOption | null;
    spouse?: ResidentOption | null;
    sectors?: { id: number; label: string }[];
    has_photo: boolean;
    photo_version: number | null;
    created_by?: string | null;
    updated_by?: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type FamilyMember = {
    id: number;
    relationship_id: number;
    relationship?: string;
    full_name: string;
    birthdate: string | null;
    age: number | null;
    related_resident: ResidentOption | null;
};

export type ResidentEducation = {
    id: number;
    school_id: number;
    school?: string;
    course_id: number;
    course?: string;
    started_on: string | null;
    ended_on: string | null;
};

export type ResidentWorkHistory = {
    id: number;
    company_id: number;
    company?: string;
    position_id: number;
    position?: string;
    employment_status_id: number;
    employment_status?: string;
    started_on: string | null;
    ended_on: string | null;
    is_current: boolean;
};

export type ResidentFormOptions = {
    lookups: Record<string, SelectOption[]>;
    puroks: SelectOption[];
    genders: SelectOption<string>[];
    civil_statuses: SelectOption<string>[];
};

export type ResidentListFilters = {
    search: string;
    purok_id: number | null;
    gender: string | null;
    civil_status: string | null;
    voter: boolean | null;
    flags: string[];
    sector_id: number | null;
    include_incomplete: boolean;
    removed: boolean;
    sort: string;
    direction: 'asc' | 'desc';
};

export type Purok = {
    id: number;
    name: string;
    is_active: boolean;
    residents_count: number;
};

export type IdCard = {
    id: number;
    card_number: string;
    status: 'issued' | 'released' | 'cancelled';
    status_label: string;
    resident?: {
        id: number;
        full_name: string;
        resident_number: string;
        is_removed: boolean;
    } | null;
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

export type IdCardLedgerFilters = {
    search: string;
    status: string | null;
    from: string | null;
    to: string | null;
};

export type PoliticalTag = { name: string; color_hex: string };

export type ColorOption = SelectOption & { color_hex: string };

export type StatisticsRow = { key: string; label: string; total: number };

export type ReportAttribute = {
    value: string;
    label: string;
    options: SelectOption[];
};

export type ReportFilters = ResidentListFilters & {
    age_from: number | null;
    age_to: number | null;
    attribute: string | null;
    attribute_values: number[];
};

export type ReportOptions = {
    puroks: SelectOption[];
    sectors: SelectOption[];
    genders: SelectOption<string>[];
    civil_statuses: SelectOption<string>[];
    flags: SelectOption<string>[];
    sorts: SelectOption<string>[];
    attributes: ReportAttribute[];
};

export type SignatoryPrint = {
    name: string;
    title: string | null;
    signature: string | null;
} | null;
