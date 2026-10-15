import type { ResidentOption, SelectOption } from './residents';

export type SmsStatus = 'queued' | 'sending' | 'sent' | 'failed' | 'cancelled';

export type SmsMessage = {
    id: number;
    recipient: string | null;
    resident: { id: number; full_name: string; is_removed: boolean } | null;
    body: string | null;
    segments: number;
    encoding: string;
    status: SmsStatus;
    status_label: string;
    attempts: number;
    last_error_code: string | null;
    provider_message_id: string | null;
    queued_at: string;
    sent_at: string | null;
    failed_at: string | null;
    cancelled_at: string | null;
    queued_by?: string | null;
    cancelled_by?: string | null;
    source?: string | null;
    is_pruned: boolean;
    can_cancel: boolean;
    can_retry: boolean;
};

export type SmsListFilters = {
    search: string | null;
    status: string | null;
    from: string | null;
    to: string | null;
};

export type SmsTemplateOption = { id: number; title: string; body: string };

export type SmsTemplate = SmsTemplateOption & { updated_at: string | null };

export type SmsGroup = {
    id: number;
    name: string;
    is_active: boolean;
    members_count: number | null;
};

export type SmsGroupMember = {
    id: number;
    resident: {
        id: number;
        resident_number: string;
        full_name: string;
        is_removed: boolean;
        is_deceased: boolean;
    } | null;
    label: string | null;
    number: string | null;
    is_reachable: boolean;
};

export type SmsRecipientSummary = {
    matched: number;
    recipients: number;
    skipped: number;
    duplicates: number;
};

export type SmsComposePrefill = {
    resident: ResidentOption | null;
    numbers: string[];
    reply_resident: ResidentOption | null;
};

export type SmsGroupOption = SelectOption;
