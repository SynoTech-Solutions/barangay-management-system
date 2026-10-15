<?php

return [
    'cancel_reason' => 'Cancelled before the import',
    'or_conflict' => 'OR number :number was already used by another document; it was cleared on import.',
    'form_number_conflict' => 'CTC form number :number was already used by another cedula; it was cleared on import.',
    'errors' => [
        'not_a_directory' => 'The bundle path :path is not a readable directory.',
        'manifest_missing' => 'The bundle has no manifest.json.',
        'manifest_unreadable' => 'manifest.json is not a valid JSON object.',
        'wrong_format' => 'manifest.json must declare format ":expected".',
        'unsupported_version' => 'Bundle format version :version is not supported; this importer reads version :supported.',
        'manifest_invalid' => 'manifest.json does not match the schema: :errors',
    ],
    'command' => [
        'barangay_required' => 'Pass --barangay with the PSGC code of an onboarded barangay, or set barangay_psgc in the manifest.',
        'barangay_unknown' => 'No onboarded barangay has PSGC code :code.',
        'barangay_mismatch' => 'The manifest targets barangay :manifest; importing into :flag because --barangay wins.',
        'unknown_entity' => 'Unknown entity ":name". Entities: :entities.',
        'not_in_bundle' => ':entity is not in this bundle; nothing to import for it.',
        'missing_dependency' => ':entity depends on :dependency, which is in the bundle but neither selected nor imported by an earlier run. Add :dependency to --only.',
        'operator_unknown' => 'No account with username ":username".',
        'opened' => 'Bundle format v:version from :generator :generator_version, generated :generated_at.',
        'progress' => ':entity: :count record(s)',
        'schema_error' => ':entity :key :pointer (:keyword)',
        'more_errors' => 'and :count more; see the problem report.',
        'rejected' => 'Bundle rejected: :count record(s) failed validation. Nothing was imported.',
        'sequences' => 'Sequences continued: :count',
        'problems' => 'Problem report (:rows row(s)): storage/app/private/:path',
        'dry_run' => 'Dry run for :barangay: nothing was written except the problem report.',
        'finished' => 'Run #:run for :barangay: :status.',
    ],
];
