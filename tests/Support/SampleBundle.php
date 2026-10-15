<?php

namespace Tests\Support;

final class SampleBundle
{
    public static function make(): BundleWriter
    {
        return BundleWriter::make()
            ->with('lookups', [
                ['source_key' => 'LK-1', 'type' => 'religion', 'label' => 'Roman Catholic'],
                ['source_key' => 'LK-2', 'type' => 'religion', 'label' => 'Sample Fellowship Church', 'sort_order' => 5],
            ])
            ->with('puroks', [
                ['source_key' => 'P-1', 'name' => 'Purok Uno'],
                ['source_key' => 'P-2', 'name' => 'Purok Dos'],
            ])
            ->with('users', [
                ['source_key' => 'U-1', 'username' => 'sample.admin', 'name' => 'Sample Admin', 'role' => 'barangay-admin'],
                ['source_key' => 'U-2', 'username' => 'sample.clerk', 'name' => 'Sample Clerk', 'designation' => 'Clerk', 'role' => 'clerk', 'created_at' => '2020-01-02T08:00:00+08:00'],
            ])
            ->with('color_categories', [
                ['source_key' => 'C-1', 'name' => 'Blue Group', 'color_hex' => '#2563EB'],
            ])
            ->with('residents', [
                [
                    'source_key' => 'R-1', 'source_number' => 'RES-000010', 'last_name' => 'Sample', 'first_name' => 'Juan', 'middle_name' => 'Test',
                    'birthdate' => '1960-01-15', 'gender' => 'male', 'civil_status' => 'married', 'religion' => 'roman   catholic', 'title' => 'Mr.',
                    'purok_key' => 'P-1', 'spouse_key' => 'R-2', 'mobile_number' => '09170000001', 'is_senior_citizen_member' => true,
                    'height_cm' => 170, 'is_registered_voter' => true, 'voter_id' => 'VIN-SAMPLE-1', 'sectors' => ['Farmer', 'Women'],
                    'photo' => 'residents/r-1.png', 'photo_taken_at' => '2019-03-05T09:30:00+08:00', 'color_category_key' => 'C-1',
                    'created_at' => '2019-03-05T09:15:00+08:00',
                ],
                ['source_key' => 'R-2', 'source_number' => 'RES-000011', 'last_name' => 'Sample', 'first_name' => 'Maria', 'gender' => 'female', 'spouse_key' => 'R-1', 'color_category_key' => 'C-1'],
                ['source_key' => 'R-3', 'last_name' => 'Example', 'first_name' => 'Pedro', 'purok_key' => 'P-404', 'religion' => 'Sample Fellowship Church', 'deleted_at' => '2022-01-01T00:00:00+08:00'],
                ['source_key' => 'R-4', 'last_name' => '', 'first_name' => ''],
            ])
            ->with('family_members', [
                ['source_key' => 'F-1', 'resident_key' => 'R-1', 'relationship' => 'Son', 'full_name' => 'Jose Sample', 'birth_year' => 1985],
                ['source_key' => 'F-2', 'resident_key' => 'R-404', 'relationship' => 'Son', 'full_name' => 'Nobody Sample'],
            ])
            ->with('educations', [
                ['source_key' => 'E-1', 'resident_key' => 'R-1', 'school' => 'Sample Elementary School', 'course' => 'Elementary', 'ended_on' => '1972-03-31'],
            ])
            ->with('work_histories', [
                ['source_key' => 'W-1', 'resident_key' => 'R-1', 'company' => 'Sample Farms', 'position' => 'Farmer', 'employment_status' => 'Permanent', 'is_current' => true, 'ended_on' => '2020-01-01'],
            ])
            ->with('id_cards', [
                ['source_key' => 'ID-1', 'resident_key' => 'R-1', 'source_number' => 'ID-2024-000010', 'status' => 'released', 'issued_at' => '2024-02-01T09:00:00+08:00', 'or_number' => 'or-100', 'amount' => '100.00'],
                ['source_key' => 'ID-2', 'resident_key' => 'R-2', 'source_number' => 'ID-2024-000011', 'status' => 'cancelled', 'issued_at' => '2024-02-02T09:00:00+08:00', 'or_number' => 'OR-100', 'amount' => '100.00'],
            ])
            ->with('barangay_clearances', [
                ['source_key' => 'BC-1', 'resident_key' => 'R-1', 'source_number' => 'BC-2024-000007', 'source_control_number' => 'BCC-2024-000007', 'clearance_type' => 'Local employment', 'purpose' => 'Employment', 'status' => 'issued', 'issued_at' => '2024-03-01T10:00:00+08:00', 'or_number' => 'OR-200', 'amount' => '50.00', 'doc_stamp' => '10.10'],
                ['source_key' => 'BC-2', 'resident_key' => 'R-2', 'source_number' => 'BC-2023-000012', 'source_control_number' => 'BCC-2023-000012', 'clearance_type' => 'Local employment', 'purpose' => 'Employment', 'status' => 'released', 'issued_at' => '2023-05-01T10:00:00+08:00', 'or_number' => 'OR-200'],
                ['source_key' => 'BC-3', 'resident_key' => 'R-3', 'clearance_type' => 'Local employment', 'purpose' => 'Travel', 'status' => 'issued', 'issued_at' => '2024-03-02T10:00:00+08:00'],
            ])
            ->with('indigency_certificates', [
                ['source_key' => 'IC-1', 'resident_key' => 'R-2', 'purpose' => 'Medical assistance', 'status' => 'issued', 'issued_at' => '2024-03-03T10:00:00+08:00'],
            ])
            ->with('businesses', [
                ['source_key' => 'B-1', 'source_number' => 'BUS-000003', 'name' => 'Sample Store', 'owner_resident_key' => 'R-1', 'category' => 'Retail', 'nature' => 'Sari-sari store', 'ownership_type' => 'Sole proprietorship', 'registered_at' => '2021-06-01T08:00:00+08:00'],
            ])
            ->with('business_clearances', [
                ['source_key' => 'BZ-1', 'business_key' => 'B-1', 'status' => 'issued', 'issued_at' => '2024-04-01T10:00:00+08:00', 'or_number' => 'OR-300', 'amount' => '500.00'],
            ])
            ->with('cedulas', [
                ['source_key' => 'CT-1', 'resident_key' => 'R-1', 'source_number' => 'CTC-2024-000031', 'form_number' => 'CC-1', 'status' => 'issued', 'issued_at' => '2024-01-10T09:00:00+08:00', 'basic_tax' => '5.00', 'amount' => '5.00'],
                ['source_key' => 'CT-2', 'resident_key' => 'R-2', 'form_number' => 'CC-1', 'status' => 'issued', 'issued_at' => '2024-01-11T09:00:00+08:00', 'basic_tax' => '5.00', 'amount' => '5.00'],
            ])
            ->with('blotters', [
                [
                    'source_key' => 'BL-1', 'incident_type' => 'Noise complaint', 'reported_at' => '2024-05-01T20:00:00+08:00', 'narrative' => 'Loud music after curfew.', 'status' => 'open',
                    'parties' => [['role' => 'respondent', 'resident_key' => 'R-3'], ['role' => 'complainant', 'name' => 'Sample Neighbor']],
                    'photos' => ['blotters/bl-1.jpg'],
                ],
                ['source_key' => 'BL-2', 'incident_type' => 'Noise complaint', 'reported_at' => '2024-05-02T20:00:00+08:00', 'narrative' => 'Settled.', 'status' => 'resolved', 'resolved_at' => '2024-05-03T10:00:00+08:00'],
            ])
            ->with('collection_types', [
                ['source_key' => 'CTY-1', 'name' => 'Facility rental'],
            ])
            ->with('other_collections', [
                ['source_key' => 'OC-1', 'source_number' => 'OC-2024-000004', 'collection_type_key' => 'CTY-1', 'payer_name' => 'Sample Payer', 'or_number' => 'OR-400', 'amount' => '250.00', 'recorded_at' => '2024-06-01T10:00:00+08:00'],
                ['source_key' => 'OC-2', 'collection_type_key' => 'CTY-1', 'payer_name' => 'Sample Payer', 'or_number' => 'OR-100', 'amount' => '250.00', 'recorded_at' => '2024-06-02T10:00:00+08:00'],
            ])
            ->with('accounts', [
                ['source_key' => 'A-G1', 'code' => '9', 'name' => 'Expenses', 'kind' => 'group', 'group_type' => 'expense'],
                ['source_key' => 'A-T1', 'code' => '9-01', 'name' => 'Sample Title', 'kind' => 'title', 'parent_key' => 'A-G1'],
                ['source_key' => 'A-S1', 'code' => '9-01-01', 'name' => 'Sample Supplies', 'kind' => 'subsidiary', 'parent_key' => 'A-T1'],
            ])
            ->with('budgets', [
                ['source_key' => 'BG-1', 'fiscal_year' => 2024, 'title' => 'Budget 2024', 'status' => 'approved', 'versions' => [
                    ['number' => 1, 'lines' => [['account_key' => 'A-S1', 'amount' => '10000.00'], ['account_key' => 'A-T1', 'amount' => '1.00']]],
                ]],
            ])
            ->with('bank_accounts', [
                ['source_key' => 'BA-1', 'account_number' => '000-111-222', 'account_name' => 'Sample Fund', 'depository_bank' => 'Sample Bank'],
            ])
            ->with('vendors', [
                ['source_key' => 'V-1', 'name' => 'Sample Supplier', 'address' => 'Sample Street', 'contact_number' => '0000'],
            ])
            ->with('vouchers', [
                [
                    'source_key' => 'VO-1', 'source_number' => 'DV-2024-000006', 'budget_key' => 'BG-1', 'payment_type' => 'cash-advance', 'status' => 'released',
                    'voucher_date' => '2024-03-10', 'vendor_key' => 'V-1', 'payee_name' => 'Sample Supplier', 'particulars' => 'Office supplies', 'prepared_at' => '2024-03-10T09:00:00+08:00',
                    'items' => [['source_key' => 'VI-1', 'account_key' => 'A-S1', 'description' => 'Supplies', 'amount' => '5000.00']],
                ],
            ])
            ->with('checks', [
                ['source_key' => 'CK-1', 'bank_account_key' => 'BA-1', 'voucher_key' => 'VO-1', 'payee_name' => 'Sample Supplier', 'check_number' => '1001', 'amount' => '5000.00', 'status' => 'released', 'issued_at' => '2024-03-11T09:00:00+08:00'],
            ])
            ->with('liquidations', [
                ['source_key' => 'LQ-1', 'source_number' => 'LR-2024-000002', 'voucher_key' => 'VO-1', 'status' => 'for-clearing', 'recorded_at' => '2024-03-20T09:00:00+08:00', 'or_number' => 'OR-500', 'items' => [['voucher_item_key' => 'VI-1', 'actual_amount' => '4300.00']]],
            ])
            ->with('form_picklists', [
                ['source_key' => 'PL-1', 'name' => 'Case types', 'items' => ['Civil', 'Criminal']],
            ])
            ->with('form_templates', [
                ['source_key' => 'FT-1', 'source_number' => 'FT-000002', 'name' => 'Certificate of residency', 'type' => 'Certificates', 'kind' => 'rich-text', 'versions' => [
                    ['version' => 1, 'body' => '<p>{{resident.full_name}} for {{field.purpose}}</p><script>x</script>', 'fields' => [
                        ['key' => 'purpose', 'label' => 'Purpose', 'input_type' => 'text'],
                        ['key' => 'case', 'label' => 'Case', 'input_type' => 'table', 'picklist_key' => 'PL-1'],
                    ]],
                ]],
            ])
            ->with('form_transactions', [
                ['source_key' => 'FX-1', 'template_key' => 'FT-1', 'template_version' => 1, 'resident_key' => 'R-1', 'status' => 'issued', 'issued_at' => '2024-07-01T09:00:00+08:00', 'or_number' => 'OR-600', 'amount' => '50.00', 'fields' => [
                    ['key' => 'purpose', 'label' => 'Purpose', 'value' => 'Employment'],
                ]],
            ])
            ->with('sms_groups', [
                ['source_key' => 'SG-1', 'name' => 'Seniors', 'members' => [
                    ['resident_key' => 'R-1'],
                    ['mobile_number' => '09170000009', 'label' => 'Sample Contact'],
                    ['mobile_number' => '+639170000009'],
                ]],
            ])
            ->with('sms_templates', [
                ['source_key' => 'ST-1', 'title' => 'Assembly', 'body' => 'Assembly on Saturday'],
            ])
            ->file('residents/r-1.png', BundleWriter::png())
            ->file('blotters/bl-1.jpg', BundleWriter::jpeg());
    }
}
