<?php

return [
    'self-service-profile' => [
        'label' => 'Self-service profile',
        'description' => 'Users can edit their own name, username, and password.',
    ],
    'user-theme' => [
        'label' => 'Personal theme',
        'description' => 'Users can choose light, dark, or system appearance.',
    ],
    'default-address' => [
        'label' => 'Default resident address',
        'description' => 'Build resident addresses from purok, barangay, municipality, and province instead of the street field.',
    ],
    'database-reset' => [
        'label' => 'Barangay data reset',
        'description' => 'Administrators can wipe this barangay\'s transactional data after re-entering their password.',
    ],
    'lookup-admin-inline' => [
        'label' => 'Inline lookup editing',
        'description' => 'Open lookup editors from dropdowns on data-entry forms.',
    ],
    'grid-excel-export' => [
        'label' => 'Spreadsheet export',
        'description' => 'Export lists to spreadsheet files. Exports are logged.',
    ],
    'grid-print' => [
        'label' => 'List printing',
        'description' => 'Print data lists.',
    ],
    'grid-layout-persistence' => [
        'label' => 'Saved list layouts',
        'description' => 'Remember each user\'s column order and visibility.',
    ],
    'report-print-settings' => [
        'label' => 'Report print settings',
        'description' => 'Per-report title, orientation, and paper overrides.',
    ],
    'dashboard-pending-counters' => [
        'label' => 'Dashboard counters',
        'description' => 'Show counts of items waiting for release or action.',
    ],
    'cross-barangay-reporting' => [
        'label' => 'Cross-barangay reporting',
        'description' => 'Central and municipal staff can view reports that combine barangays.',
    ],
    'audit-log-archive-import' => [
        'label' => 'Audit archive import',
        'description' => 'Import a read-only archive of audit events, kept apart from the live audit log.',
    ],
    'resident-photo' => [
        'label' => 'Resident photos',
        'description' => 'Capture and store resident photos.',
    ],
    'biometric-capture' => [
        'label' => 'Fingerprint capture',
        'description' => 'Capture and store fingerprint images. Requires a consent record.',
    ],
    'biometric-verification' => [
        'label' => 'Fingerprint matching',
        'description' => 'Match fingerprints against stored templates.',
    ],
    'political-color-tagging' => [
        'label' => 'Political color tags',
        'description' => 'Tag residents with a color category. This is sensitive personal information.',
    ],
    'resident-id-cards' => [
        'label' => 'Barangay IDs',
        'description' => 'Issue, release, and print barangay ID cards.',
    ],
    'id-card-validity' => [
        'label' => 'ID validity date',
        'description' => 'Print a one-year "Valid until" date on barangay IDs.',
    ],
    'pvc-id-printing' => [
        'label' => 'PVC ID layout',
        'description' => 'Use the PVC card layout instead of paper.',
    ],
    'issuance-requires-photo' => [
        'label' => 'Photo required for issuance',
        'description' => 'Block clearances and IDs for residents without a photo.',
    ],
    'resident-work-education' => [
        'label' => 'Work and education history',
        'description' => 'Record residents\' education and work history.',
    ],
    'sector-tagging' => [
        'label' => 'Sector tagging',
        'description' => 'Tag residents with sectors and show sector statistics.',
    ],
    'family-member-linking' => [
        'label' => 'Linked households',
        'description' => 'Link family members as resident records.',
    ],
    'resident-quick-add' => [
        'label' => 'Quick resident registration',
        'description' => 'Register a resident with minimal details, for example from the blotter.',
    ],
    'resident-bulk-edit' => [
        'label' => 'Bulk resident changes',
        'description' => 'Change purok (and color tag) for many residents at once.',
    ],
    'resident-profile-print' => [
        'label' => 'Resident profile printout',
        'description' => 'Print a resident\'s profile.',
    ],
    'blank-profile-form' => [
        'label' => 'Blank intake form',
        'description' => 'Print a blank resident intake form.',
    ],
    'unresolved-complaint-hold' => [
        'label' => 'Complaint hold',
        'description' => 'Block clearances for residents with an unresolved blotter case.',
    ],
    'sms' => [
        'label' => 'Text messaging',
        'description' => 'Queue and send text messages through the configured gateway.',
    ],
    'sms-groups' => [
        'label' => 'SMS groups',
        'description' => 'Manage recipient groups and send to a group.',
    ],
    'sms-templates' => [
        'label' => 'SMS templates',
        'description' => 'Reusable message templates.',
    ],
    'sms-from-resident-lists' => [
        'label' => 'SMS from resident lists',
        'description' => 'Send messages or add to groups from resident lists.',
    ],
    'sms-gsm-modem' => [
        'label' => 'GSM modem transport',
        'description' => 'Not built. Reserved for a directly attached GSM modem transport.',
    ],
    'sms-inbox' => [
        'label' => 'SMS inbox',
        'description' => 'Not built. Reserved for capturing incoming replies.',
    ],
    'barangay-clearance' => [
        'label' => 'Barangay clearance',
        'description' => 'Issue, release, and print barangay clearances.',
    ],
    'business-clearance' => [
        'label' => 'Business clearance',
        'description' => 'Issue business clearances and track their expiry.',
    ],
    'business-registry' => [
        'label' => 'Business registry',
        'description' => 'Register businesses and report on them.',
    ],
    'cedula' => [
        'label' => 'Community tax certificate',
        'description' => 'Record community tax certificates (cedula).',
    ],
    'cedula-templates' => [
        'label' => 'Cedula presets',
        'description' => 'Preset amounts for community tax certificates.',
    ],
    'cedula-interest' => [
        'label' => 'Cedula interest',
        'description' => 'Add an interest surcharge to community tax totals.',
    ],
    'indigency-certificate' => [
        'label' => 'Indigency certificate',
        'description' => 'Issue indigency certificates.',
    ],
    'blotter' => [
        'label' => 'Blotter',
        'description' => 'Record, resolve, and cancel complaints.',
    ],
    'blotter-photo-attachment' => [
        'label' => 'Blotter photos',
        'description' => 'Attach incident photos to blotter entries.',
    ],
    'clearance-ctc-reference' => [
        'label' => 'Cedula reference on clearance',
        'description' => 'Show the community tax certificate number, place, and date on clearances.',
    ],
    'clearance-doc-stamp' => [
        'label' => 'Documentary stamp fee',
        'description' => 'Record a documentary stamp fee on clearances.',
    ],
    'clearance-fingerprints' => [
        'label' => 'Fingerprints on clearance',
        'description' => 'Print thumbprints on clearances.',
    ],
    'clearance-qr-code' => [
        'label' => 'QR code on clearance',
        'description' => 'Print a QR code carrying the clearance number on clearances.',
    ],
    'document-release-step' => [
        'label' => 'Release step',
        'description' => 'Track issuance and release as separate steps. When off, documents are released as soon as they are issued.',
    ],
    'captain-signature-on-clearance' => [
        'label' => 'Punong Barangay signature',
        'description' => 'Print the Punong Barangay\'s signature image on clearances.',
    ],
    'secretary-approval-block' => [
        'label' => 'Secretary approval block',
        'description' => 'Show the secretary\'s "Verified by" block on barangay clearances.',
    ],
    'custom-print-templates' => [
        'label' => 'Custom print layouts',
        'description' => 'Use barangay-supplied layouts instead of the built-in ones.',
    ],
    'template-shortcodes' => [
        'label' => 'Template placeholders',
        'description' => 'Fill placeholders in layouts with resident data.',
    ],
    'report-designer' => [
        'label' => 'Layout designer',
        'description' => 'Design print layouts in the app.',
    ],
    'forms-engine' => [
        'label' => 'Forms and certificates',
        'description' => 'Configurable form templates and transactions.',
    ],
    'forms-rich-text-templates' => [
        'label' => 'Rich-text form templates',
        'description' => 'Write form templates as rich text.',
    ],
    'pdf-form-templates' => [
        'label' => 'PDF form templates',
        'description' => 'Fill uploaded fillable PDFs.',
    ],
    'forms-table-picklists' => [
        'label' => 'Form pick lists',
        'description' => 'Pick-list inputs on form templates.',
    ],
    'forms-auto-print-on-save' => [
        'label' => 'Print on save',
        'description' => 'Print a form transaction right after saving it.',
    ],
    'sql-report-builder' => [
        'label' => 'Saved report builder',
        'description' => 'Build and save reports from predefined datasets, columns, and filters. No SQL is ever entered.',
    ],
    'report-government-id-columns' => [
        'label' => 'Government IDs in saved reports',
        'description' => 'Allow government ID number columns in saved reports for users permitted to see them.',
    ],
    'finance' => [
        'label' => 'Finance',
        'description' => 'Budget, vouchers, checks, and the chart of accounts.',
    ],
    'direct-check-issuance' => [
        'label' => 'Checks without voucher',
        'description' => 'Issue checks to a supplier without a voucher.',
    ],
    'budget-copy-previous' => [
        'label' => 'Copy previous budget',
        'description' => 'Start a budget from the previous one.',
    ],
    'budget-revision' => [
        'label' => 'Budget revision',
        'description' => 'Revise approved budgets with remarks.',
    ],
    'budget-unappropriated-row' => [
        'label' => 'Unappropriated balance',
        'description' => 'Show the computed unappropriated balance.',
    ],
    'voucher-others-auto-release' => [
        'label' => 'Auto-release "Others" vouchers',
        'description' => 'Vouchers with payment type "Others" skip check issuance.',
    ],
    'cash-advance-liquidation' => [
        'label' => 'Cash advance liquidation',
        'description' => 'Liquidate cash advances with refunds and reimbursements.',
    ],
    'liquidation-clearing' => [
        'label' => 'Liquidation clearing',
        'description' => 'Require a separate approval to clear a liquidation.',
    ],
    'check-printing' => [
        'label' => 'Check printing',
        'description' => 'Print checks on pre-printed stock.',
    ],
    'amount-in-words-uppercase' => [
        'label' => 'Uppercase amount in words',
        'description' => 'Print amounts in words in capital letters.',
    ],
    'dv-print-requires-check' => [
        'label' => 'Voucher printing needs a check',
        'description' => 'Require an issued check before printing a voucher.',
    ],
    'other-collections' => [
        'label' => 'Other collections',
        'description' => 'Record miscellaneous collections.',
    ],
    'consolidated-collection-report' => [
        'label' => 'Consolidated collection report',
        'description' => 'Report all collections across modules.',
    ],
    'accounting-tickets-viewer' => [
        'label' => 'Accounting tickets',
        'description' => 'Read-only accounting ticket viewer.',
    ],
    'accounting-reports' => [
        'label' => 'Accounting reports',
        'description' => 'Government accounting reports.',
    ],
    'gl-locked-account-edit' => [
        'label' => 'Edit predefined accounts',
        'description' => 'Allow editing predefined chart-of-accounts titles.',
    ],

    'areas' => [
        'platform' => 'Platform',
        'residents' => 'Residents',
        'sms' => 'Text messaging',
        'certificates' => 'Certificates and clearances',
        'forms' => 'Forms',
        'finance' => 'Finance',
        'migration' => 'Data import',
    ],

    'sources' => [
        'default' => 'Default',
        'global-override' => 'Set for all barangays',
        'barangay-override' => 'Set for this barangay',
        'forced-off' => 'Turned off by the operator',
        'missing-dependency' => 'Needs another feature',
    ],
];
