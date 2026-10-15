<?php

namespace App\Enums;

use Symfony\Component\HttpFoundation\Response;

enum ErrorCode: string
{
    case FeatureDisabled = 'feature_disabled';
    case MissingBarangayContext = 'missing_barangay_context';
    case BarangaySuspended = 'barangay_suspended';
    case BarangayNotAccessible = 'barangay_not_accessible';
    case AccountInactive = 'account_inactive';
    case CannotDeactivateSelf = 'cannot_deactivate_self';
    case CannotRemoveOwnAdminRole = 'cannot_remove_own_admin_role';
    case RoleNotAssignable = 'role_not_assignable';
    case GlobalLookupReadOnly = 'global_lookup_read_only';
    case LookupLabelTaken = 'lookup_label_taken';
    case PermissionDenied = 'permission_denied';
    case PurokNameTaken = 'purok_name_taken';
    case ResidentPhotoRequired = 'resident_photo_required';
    case ResidentDeceased = 'resident_deceased';
    case ResidentOnHold = 'resident_on_hold';
    case DocumentNotEditable = 'document_not_editable';
    case DocumentsNotPending = 'documents_not_pending';
    case DocumentCancelled = 'document_cancelled';
    case BusinessInactive = 'business_inactive';
    case BlotterNotOpen = 'blotter_not_open';
    case ColorCategoryNameTaken = 'color_category_name_taken';
    case AccountLocked = 'account_locked';
    case AccountHasBudgetLines = 'account_has_budget_lines';
    case AccountNotPosting = 'account_not_posting';
    case AccountParentInvalid = 'account_parent_invalid';
    case BudgetYearTaken = 'budget_year_taken';
    case BudgetNotDraft = 'budget_not_draft';
    case BudgetNotApproved = 'budget_not_approved';
    case BudgetNotEmpty = 'budget_not_empty';
    case BudgetSourceInvalid = 'budget_source_invalid';
    case BudgetRevisionUnbalanced = 'budget_revision_unbalanced';
    case BudgetRevisionNeedsRemarks = 'budget_revision_needs_remarks';
    case BudgetRevisionUnchanged = 'budget_revision_unchanged';
    case CollectionCancelled = 'collection_cancelled';
    case OrNumberTaken = 'or_number_taken';
    case BudgetRevisionBelowCommitted = 'budget_revision_below_committed';
    case AccountNotFundSource = 'account_not_fund_source';
    case InsufficientFunds = 'insufficient_funds';
    case VoucherNotEditable = 'voucher_not_editable';
    case VoucherWrongStatus = 'voucher_wrong_status';
    case VoucherHasReleasedCheck = 'voucher_has_released_check';
    case VoucherLiquidated = 'voucher_liquidated';
    case VoucherNeedsCheck = 'voucher_needs_check';
    case VoucherHasCheck = 'voucher_has_check';
    case VoucherPaidWithoutCheck = 'voucher_paid_without_check';
    case CheckNotForRelease = 'check_not_for_release';
    case CheckCancelled = 'check_cancelled';
    case LiquidationCleared = 'liquidation_cleared';
    case LiquidationNeedsReceipt = 'liquidation_needs_receipt';
    case VoucherNotUnliquidated = 'voucher_not_unliquidated';
    case CheckNumberTaken = 'check_number_taken';
    case LiquidationItemsMismatch = 'liquidation_items_mismatch';
    case FormTemplateUnavailable = 'form_template_unavailable';
    case FormTemplateNameTaken = 'form_template_name_taken';
    case FormTemplateUnknownTokens = 'form_template_unknown_tokens';
    case FormPicklistNameTaken = 'form_picklist_name_taken';
    case FormPicklistItemTaken = 'form_picklist_item_taken';
    case PdfFormUnreadable = 'pdf_form_unreadable';
    case PdfFillerNotConfigured = 'pdf_filler_not_configured';
    case PdfFillFailed = 'pdf_fill_failed';
    case SavedReportNameTaken = 'saved_report_name_taken';
    case SavedReportUnavailable = 'saved_report_unavailable';
    case SmsNoRecipients = 'sms_no_recipients';
    case SmsTooLong = 'sms_too_long';
    case SmsEmptyBody = 'sms_empty_body';
    case SmsTooManyRecipients = 'sms_too_many_recipients';
    case SmsRecipientsChanged = 'sms_recipients_changed';
    case SmsMessagesNotQueued = 'sms_messages_not_queued';
    case SmsMessagesNotFailed = 'sms_messages_not_failed';
    case SmsGroupNameTaken = 'sms_group_name_taken';
    case SmsGroupInactive = 'sms_group_inactive';
    case SmsGroupMemberTaken = 'sms_group_member_taken';

    public function httpStatus(): int
    {
        return match ($this) {
            self::FeatureDisabled => Response::HTTP_NOT_FOUND,
            self::MissingBarangayContext => Response::HTTP_CONFLICT,
            self::BarangaySuspended, self::BarangayNotAccessible, self::AccountInactive, self::PermissionDenied => Response::HTTP_FORBIDDEN,
            self::CannotDeactivateSelf, self::CannotRemoveOwnAdminRole, self::RoleNotAssignable,
            self::GlobalLookupReadOnly, self::LookupLabelTaken, self::PurokNameTaken,
            self::ResidentPhotoRequired, self::ResidentDeceased, self::ResidentOnHold,
            self::DocumentNotEditable, self::DocumentsNotPending, self::DocumentCancelled, self::BusinessInactive, self::BlotterNotOpen,
            self::ColorCategoryNameTaken,
            self::AccountLocked, self::AccountHasBudgetLines, self::AccountNotPosting, self::AccountParentInvalid,
            self::BudgetYearTaken, self::BudgetNotDraft, self::BudgetNotApproved, self::BudgetNotEmpty, self::BudgetSourceInvalid,
            self::BudgetRevisionUnbalanced, self::BudgetRevisionNeedsRemarks, self::BudgetRevisionUnchanged,
            self::CollectionCancelled, self::OrNumberTaken,
            self::BudgetRevisionBelowCommitted, self::AccountNotFundSource, self::InsufficientFunds,
            self::VoucherNotEditable, self::VoucherWrongStatus, self::VoucherHasReleasedCheck, self::VoucherLiquidated,
            self::VoucherNeedsCheck, self::VoucherHasCheck, self::VoucherPaidWithoutCheck,
            self::CheckNotForRelease, self::CheckCancelled, self::LiquidationCleared, self::LiquidationNeedsReceipt,
            self::VoucherNotUnliquidated, self::CheckNumberTaken, self::LiquidationItemsMismatch,
            self::FormTemplateUnavailable, self::FormTemplateNameTaken, self::FormTemplateUnknownTokens,
            self::FormPicklistNameTaken, self::FormPicklistItemTaken, self::PdfFormUnreadable,
            self::SavedReportNameTaken, self::SavedReportUnavailable,
            self::SmsNoRecipients, self::SmsTooLong, self::SmsEmptyBody, self::SmsTooManyRecipients, self::SmsRecipientsChanged,
            self::SmsMessagesNotQueued, self::SmsMessagesNotFailed, self::SmsGroupNameTaken, self::SmsGroupInactive,
            self::SmsGroupMemberTaken => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::PdfFillerNotConfigured, self::PdfFillFailed => Response::HTTP_SERVICE_UNAVAILABLE,
        };
    }

    public function messageKey(): string
    {
        return "errors.{$this->value}";
    }
}
