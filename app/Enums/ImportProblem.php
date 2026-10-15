<?php

namespace App\Enums;

enum ImportProblem: string
{
    case AlreadyImported = 'already_imported';
    case InvalidJson = 'invalid_json';
    case SchemaInvalid = 'schema_invalid';
    case DuplicateSourceKey = 'duplicate_source_key';
    case CountMismatch = 'count_mismatch';
    case EntityFileMissing = 'entity_file_missing';
    case DanglingReference = 'dangling_reference';
    case MissingName = 'missing_name';
    case FutureBirthdate = 'future_birthdate';
    case UnknownBirthplace = 'unknown_birthplace';
    case AttachmentMissing = 'attachment_missing';
    case AttachmentUnsupported = 'attachment_unsupported';
    case ColorTagSkipped = 'color_tag_skipped';
    case NumberReplaced = 'number_replaced';
    case NameTaken = 'name_taken';
    case UsernameRenamed = 'username_renamed';
    case OrNumberConflict = 'or_number_conflict';
    case FormNumberConflict = 'form_number_conflict';
    case AccountParentInvalid = 'account_parent_invalid';
    case AccountKindMismatch = 'account_kind_mismatch';
    case BudgetYearExists = 'budget_year_exists';
    case BudgetLineNotPosting = 'budget_line_not_posting';
    case DuplicateLine = 'duplicate_line';
    case DuplicateVersion = 'duplicate_version';
    case UnknownVersion = 'unknown_version';
    case DuplicateFieldKey = 'duplicate_field_key';
    case AmountBelowMinimum = 'amount_below_minimum';
    case CheckConflict = 'check_conflict';
    case LiquidationConflict = 'liquidation_conflict';
    case DuplicateMember = 'duplicate_member';
    case RecordFailed = 'record_failed';
}
