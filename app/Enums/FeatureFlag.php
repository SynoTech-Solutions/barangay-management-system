<?php

namespace App\Enums;

enum FeatureFlag: string
{
    case SelfServiceProfile = 'self-service-profile';
    case UserTheme = 'user-theme';
    case DefaultAddress = 'default-address';
    case DatabaseReset = 'database-reset';
    case LookupAdminInline = 'lookup-admin-inline';
    case GridExcelExport = 'grid-excel-export';
    case GridPrint = 'grid-print';
    case GridLayoutPersistence = 'grid-layout-persistence';
    case ReportPrintSettings = 'report-print-settings';
    case DashboardPendingCounters = 'dashboard-pending-counters';
    case CrossBarangayReporting = 'cross-barangay-reporting';
    case AuditLogArchiveImport = 'audit-log-archive-import';

    case ResidentPhoto = 'resident-photo';
    case BiometricCapture = 'biometric-capture';
    case BiometricVerification = 'biometric-verification';
    case PoliticalColorTagging = 'political-color-tagging';
    case ResidentIdCards = 'resident-id-cards';
    case IdCardValidity = 'id-card-validity';
    case PvcIdPrinting = 'pvc-id-printing';
    case IssuanceRequiresPhoto = 'issuance-requires-photo';
    case ResidentWorkEducation = 'resident-work-education';
    case SectorTagging = 'sector-tagging';
    case FamilyMemberLinking = 'family-member-linking';
    case ResidentQuickAdd = 'resident-quick-add';
    case ResidentBulkEdit = 'resident-bulk-edit';
    case ResidentProfilePrint = 'resident-profile-print';
    case BlankProfileForm = 'blank-profile-form';
    case UnresolvedComplaintHold = 'unresolved-complaint-hold';

    case Sms = 'sms';
    case SmsGroups = 'sms-groups';
    case SmsTemplates = 'sms-templates';
    case SmsFromResidentLists = 'sms-from-resident-lists';
    case SmsGsmModem = 'sms-gsm-modem';
    case SmsInbox = 'sms-inbox';

    case BarangayClearance = 'barangay-clearance';
    case BusinessClearance = 'business-clearance';
    case BusinessRegistry = 'business-registry';
    case Cedula = 'cedula';
    case CedulaTemplates = 'cedula-templates';
    case CedulaInterest = 'cedula-interest';
    case IndigencyCertificate = 'indigency-certificate';
    case Blotter = 'blotter';
    case BlotterPhotoAttachment = 'blotter-photo-attachment';
    case ClearanceCtcReference = 'clearance-ctc-reference';
    case ClearanceDocStamp = 'clearance-doc-stamp';
    case ClearanceFingerprints = 'clearance-fingerprints';
    case ClearanceQrCode = 'clearance-qr-code';
    case DocumentReleaseStep = 'document-release-step';
    case CaptainSignatureOnClearance = 'captain-signature-on-clearance';
    case SecretaryApprovalBlock = 'secretary-approval-block';
    case CustomPrintTemplates = 'custom-print-templates';
    case TemplateShortcodes = 'template-shortcodes';
    case ReportDesigner = 'report-designer';

    case FormsEngine = 'forms-engine';
    case FormsRichTextTemplates = 'forms-rich-text-templates';
    case PdfFormTemplates = 'pdf-form-templates';
    case FormsTablePicklists = 'forms-table-picklists';
    case FormsAutoPrintOnSave = 'forms-auto-print-on-save';
    case SqlReportBuilder = 'sql-report-builder';
    case ReportGovernmentIdColumns = 'report-government-id-columns';

    case Finance = 'finance';
    case DirectCheckIssuance = 'direct-check-issuance';
    case BudgetCopyPrevious = 'budget-copy-previous';
    case BudgetRevision = 'budget-revision';
    case BudgetUnappropriatedRow = 'budget-unappropriated-row';
    case VoucherOthersAutoRelease = 'voucher-others-auto-release';
    case CashAdvanceLiquidation = 'cash-advance-liquidation';
    case LiquidationClearing = 'liquidation-clearing';
    case CheckPrinting = 'check-printing';
    case AmountInWordsUppercase = 'amount-in-words-uppercase';
    case DvPrintRequiresCheck = 'dv-print-requires-check';
    case OtherCollections = 'other-collections';
    case ConsolidatedCollectionReport = 'consolidated-collection-report';
    case AccountingTicketsViewer = 'accounting-tickets-viewer';
    case AccountingReports = 'accounting-reports';
    case GlLockedAccountEdit = 'gl-locked-account-edit';

    public function labelKey(): string
    {
        return "features.{$this->value}.label";
    }

    public function descriptionKey(): string
    {
        return "features.{$this->value}.description";
    }
}
