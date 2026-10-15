<?php

return [
    'account_kinds' => [
        'group' => 'Account group',
        'title' => 'Account title',
        'subsidiary' => 'Subsidiary account',
    ],
    'group_types' => [
        'income' => 'Income',
        'expense' => 'Expense',
        'other' => 'Other',
    ],
    'budget_statuses' => [
        'draft' => 'Draft',
        'approved' => 'Approved',
    ],
    'collection_sources' => [
        'barangay-clearance' => 'Barangay clearance',
        'business-clearance' => 'Business clearance',
        'cedula' => 'Cedula',
        'id-card' => 'Barangay ID',
        'indigency-certificate' => 'Indigency certificate',
        'other-collection' => 'Other collection',
        'form-transaction' => 'Form',
    ],
    'budgets' => [
        'default_title' => 'Budget :year',
    ],
    'validation' => [
        'voucher_date_in_budget_year' => 'The voucher date must fall in the :year budget year.',
        'check_number_taken' => 'This check number is already used on the selected bank account.',
    ],
    'payment_types' => [
        'cash-advance' => 'Cash advance',
        'reimbursement' => 'Reimbursement',
        'others' => 'Others',
    ],
    'voucher_statuses' => [
        'prepared' => 'Prepared',
        'verified' => 'Verified',
        'approved' => 'Approved',
        'released' => 'Released, unliquidated',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ],
    'check_statuses' => [
        'for-release' => 'For releasing',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
    ],
    'liquidation_statuses' => [
        'for-clearing' => 'For clearing',
        'cleared' => 'Cleared',
    ],
    'export' => [
        'paid_on' => 'Payment date',
        'source_label' => 'Source',
        'type' => 'Type',
        'reference' => 'Reference number',
        'or_number' => 'OR number',
        'payer' => 'Paid by',
        'amount' => 'Amount',
        'doc_stamp' => 'Documentary stamp',
        'total' => 'Total',
    ],
];
