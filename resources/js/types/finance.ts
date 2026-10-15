export type AccountKind = 'group' | 'title' | 'subsidiary';

export type AccountGroupType = 'income' | 'expense' | 'other';

export type Account = {
    id: number;
    parent_id: number | null;
    kind: AccountKind;
    kind_label: string;
    group_type: AccountGroupType | null;
    group_type_label: string | null;
    code: string;
    name: string;
    level: number;
    remarks: string | null;
    is_locked: boolean;
    is_active: boolean;
    is_posting: boolean;
    children_count: number;
};

export type BudgetSummary = {
    id: number;
    fiscal_year: number;
    title: string;
    status: 'draft' | 'approved';
    status_label: string;
    is_draft: boolean;
    approved_by?: string | null;
    approved_at: string | null;
};

export type BudgetRow = {
    id: number;
    code: string;
    name: string;
    level: number;
    kind: AccountKind;
    group_type: AccountGroupType;
    is_posting: boolean;
    is_active: boolean;
    amount: string;
    total: string;
};

export type BudgetTotals = {
    income: string;
    expense: string;
    other: string;
    unappropriated: string | null;
};

export type BudgetChange = {
    code: string;
    name: string;
    previous: string;
    amount: string;
    delta: string;
    remarks: string | null;
};

export type BudgetVersionHistory = {
    number: number;
    is_revision: boolean;
    remarks: string | null;
    created_by: string | null;
    approved_at: string | null;
    total: string;
    changes: BudgetChange[];
};

export type BalanceRow = {
    id: number;
    code: string;
    name: string;
    level: number;
    kind: AccountKind;
    group_type: AccountGroupType;
    is_posting: boolean;
    appropriation: string;
    consumed: string;
    available: string;
    is_overdrawn: boolean;
    is_zero: boolean;
};

export type DisbursementRow = {
    voucher_number: string;
    date: string;
    payee: string;
    particulars: string;
    amount: string;
    check_number: string | null;
    liquidation_number: string | null;
};

export type BankAccount = {
    id: number;
    account_number: string;
    account_name: string;
    depository_bank: string;
    branch_address: string | null;
    is_active: boolean;
};

export type Vendor = {
    id: number;
    name: string;
    address: string;
    contact_number: string;
    email: string | null;
    contact_person: string | null;
    position: string | null;
    tin: string | null;
    is_active: boolean;
};

export type CollectionType = {
    id: number;
    name: string;
    is_active: boolean;
    collections_count: number;
};

export type OtherCollection = {
    id: number;
    collection_number: string;
    collection_type_id: number;
    collection_type: string | null;
    resident_id: number | null;
    payer_name: string;
    or_number: string;
    amount: string;
    paid_on: string;
    remarks: string | null;
    recorded_by: string | null;
    recorded_at: string;
    is_cancelled: boolean;
    cancelled_by: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
};

export type OtherCollectionFilters = {
    search: string;
    collection_type_id: number | null;
    include_cancelled: boolean;
    from: string | null;
    to: string | null;
};

export type CollectionReportRow = {
    source: string;
    source_label: string;
    source_id: number;
    reference: string;
    or_number: string | null;
    payer: string;
    type: string;
    amount: string;
    doc_stamp: string;
    total: string;
    paid_on: string;
};

export type CollectionSums = {
    count: number;
    amount: string;
    doc_stamp: string;
    total: string;
};

export type CollectionGroup = CollectionSums & {
    source: string;
    label: string;
};

export type CollectionReportFilters = {
    from: string;
    to: string;
    source: string | null;
};

export type PaymentType = 'cash-advance' | 'reimbursement' | 'others';

export type VoucherStatus =
    | 'prepared'
    | 'verified'
    | 'approved'
    | 'released'
    | 'closed'
    | 'cancelled';

export type CheckStatus = 'for-release' | 'released' | 'cancelled';

export type LiquidationStatus = 'for-clearing' | 'cleared';

export type VoucherItem = {
    id: number;
    account_id: number;
    account: string | null;
    description: string;
    amount: string;
};

export type Voucher = {
    id: number;
    voucher_number: string;
    budget_id: number;
    payment_type: PaymentType;
    payment_type_label: string;
    status: VoucherStatus;
    status_label: string;
    voucher_date: string;
    vendor_id: number | null;
    resident_id: number | null;
    payee_name: string;
    payee_address: string | null;
    payee_tin: string | null;
    particulars: string;
    responsibility_center: string | null;
    office_unit: string | null;
    total: string | null;
    items: VoucherItem[];
    check: {
        id: number;
        check_number: string;
        status: CheckStatus;
        status_label: string;
    } | null;
    liquidation_number: string | null;
    prepared_by: string | null;
    prepared_at: string;
    verified_by: string | null;
    verified_at: string | null;
    approved_by: string | null;
    approved_at: string | null;
    released_at: string | null;
    closed_at: string | null;
    cancelled_by: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    print_count: number;
};

export type VoucherFilters = {
    search: string;
    status: VoucherStatus | null;
    payment_type: PaymentType | null;
    from: string | null;
    to: string | null;
};

export type BudgetOption = { value: number; label: string; year: number };

export type FundSource = { value: number; label: string; available: string };

export type Check = {
    id: number;
    voucher_id: number | null;
    voucher_number: string | null;
    is_direct: boolean;
    vendor_id: number | null;
    payee_name: string;
    bank_account_id: number;
    bank_account: string | null;
    check_number: string;
    check_date: string;
    amount: string;
    remarks: string | null;
    status: CheckStatus;
    status_label: string;
    issued_by: string | null;
    issued_at: string;
    released_by: string | null;
    released_at: string | null;
    received_by_name: string | null;
    cancelled_by: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    print_count: number;
};

export type CheckFilters = { search: string; status: CheckStatus | null };

export type LiquidationItem = {
    voucher_item_id: number;
    original_amount: string;
    actual_amount: string;
};

export type Liquidation = {
    id: number;
    liquidation_number: string;
    voucher_id: number;
    voucher_number: string | null;
    payee_name: string | null;
    voucher_date: string | null;
    liquidated_on: string;
    or_number: string | null;
    or_date: string | null;
    status: LiquidationStatus;
    status_label: string;
    advanced: string;
    actual: string;
    refund: string;
    reimbursement: string;
    items: LiquidationItem[];
    cleared_by: string | null;
    cleared_at: string | null;
    print_count: number;
};

export type LiquidationFilters = {
    search: string;
    status: LiquidationStatus | null;
};
