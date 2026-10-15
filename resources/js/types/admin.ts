export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type Paginated<T> = {
    data: T[];
    meta: PaginationMeta;
};

export type Role = {
    id: number;
    key: string;
    label: string;
    scope: 'barangay' | 'global';
    is_system: boolean;
};

export type ManagedUser = {
    id: number;
    name: string;
    username: string;
    designation: string | null;
    email: string | null;
    is_active: boolean;
    last_login_at: string | null;
    roles?: Role[];
};

export type FeatureState = {
    flag: string;
    label: string;
    description: string;
    area: string;
    area_label: string;
    kind: string;
    owner: string;
    remove_after: string | null;
    default_enabled: boolean;
    requires: string[];
    enabled: boolean;
    source: string;
    source_label: string;
    blocked_by: string | null;
};

export type LookupValue = {
    id: number;
    label: string;
    is_active: boolean;
    is_global: boolean;
};

export type Option = {
    value: string;
    label: string;
};

export type AuditEvent = {
    id: number;
    action: string;
    action_label: string;
    actor: string | null;
    subject_type: string | null;
    subject_id: string | null;
    changes: {
        v: number;
        before: Record<string, unknown> | null;
        after: Record<string, unknown> | null;
    } | null;
    request_id: string | null;
    created_at: string;
};

export type BarangaySummary = {
    id: number;
    name: string;
    psgc_code: string;
    status: 'active' | 'suspended';
    municipality?: string;
};

export type Signatory = {
    office: string;
    office_label: string;
    name: string | null;
    title: string | null;
    has_signature: boolean;
};

export type SearchResult = {
    id: number;
    label: string;
    status?: string;
};
