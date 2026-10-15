<?php

namespace App\Domain\Features;

use App\Enums\FeatureArea as Area;
use App\Enums\FeatureFlag as F;
use App\Enums\FlagKind as Kind;

final class FlagCatalog
{
    public static function definition(F $flag): FlagDefinition
    {
        return match ($flag) {
            F::SelfServiceProfile => new FlagDefinition($flag, Area::Platform, Kind::Option, true),
            F::UserTheme => new FlagDefinition($flag, Area::Platform, Kind::Option, true),
            F::DefaultAddress => new FlagDefinition($flag, Area::Platform, Kind::Option, true),
            F::DatabaseReset => new FlagDefinition($flag, Area::Platform, Kind::Ops, false),
            F::LookupAdminInline => new FlagDefinition($flag, Area::Platform, Kind::Option, true),
            F::GridExcelExport => new FlagDefinition($flag, Area::Platform, Kind::Option, true),
            F::GridPrint => new FlagDefinition($flag, Area::Platform, Kind::Option, true),
            F::GridLayoutPersistence => new FlagDefinition($flag, Area::Platform, Kind::Option, true),
            F::ReportPrintSettings => new FlagDefinition($flag, Area::Platform, Kind::Option, false),
            F::DashboardPendingCounters => new FlagDefinition($flag, Area::Platform, Kind::Option, true),
            F::CrossBarangayReporting => new FlagDefinition($flag, Area::Platform, Kind::Module, false),
            F::AuditLogArchiveImport => new FlagDefinition($flag, Area::Migration, Kind::Ops, false),

            F::ResidentPhoto => new FlagDefinition($flag, Area::Residents, Kind::Option, true),
            F::BiometricCapture => new FlagDefinition($flag, Area::Residents, Kind::Module, false),
            F::BiometricVerification => new FlagDefinition($flag, Area::Residents, Kind::Module, false, [F::BiometricCapture]),
            F::PoliticalColorTagging => new FlagDefinition($flag, Area::Residents, Kind::Module, false),
            F::ResidentIdCards => new FlagDefinition($flag, Area::Residents, Kind::Module, true),
            F::IdCardValidity => new FlagDefinition($flag, Area::Residents, Kind::Option, false, [F::ResidentIdCards]),
            F::PvcIdPrinting => new FlagDefinition($flag, Area::Residents, Kind::Option, false, [F::ResidentIdCards]),
            F::IssuanceRequiresPhoto => new FlagDefinition($flag, Area::Residents, Kind::Option, true, [F::ResidentPhoto]),
            F::ResidentWorkEducation => new FlagDefinition($flag, Area::Residents, Kind::Option, true),
            F::SectorTagging => new FlagDefinition($flag, Area::Residents, Kind::Option, true),
            F::FamilyMemberLinking => new FlagDefinition($flag, Area::Residents, Kind::Option, true),
            F::ResidentQuickAdd => new FlagDefinition($flag, Area::Residents, Kind::Option, true),
            F::ResidentBulkEdit => new FlagDefinition($flag, Area::Residents, Kind::Option, true),
            F::ResidentProfilePrint => new FlagDefinition($flag, Area::Residents, Kind::Option, true),
            F::BlankProfileForm => new FlagDefinition($flag, Area::Residents, Kind::Option, true),
            F::UnresolvedComplaintHold => new FlagDefinition($flag, Area::Residents, Kind::Option, true, [F::Blotter]),

            F::Sms => new FlagDefinition($flag, Area::Sms, Kind::Module, false),
            F::SmsGroups => new FlagDefinition($flag, Area::Sms, Kind::Option, true, [F::Sms]),
            F::SmsTemplates => new FlagDefinition($flag, Area::Sms, Kind::Option, true, [F::Sms]),
            F::SmsFromResidentLists => new FlagDefinition($flag, Area::Sms, Kind::Option, true, [F::Sms]),
            F::SmsGsmModem => new FlagDefinition($flag, Area::Sms, Kind::Option, false, [F::Sms]),
            F::SmsInbox => new FlagDefinition($flag, Area::Sms, Kind::Option, false, [F::Sms]),

            F::BarangayClearance => new FlagDefinition($flag, Area::Certificates, Kind::Module, true),
            F::BusinessClearance => new FlagDefinition($flag, Area::Certificates, Kind::Module, true, [F::BusinessRegistry]),
            F::BusinessRegistry => new FlagDefinition($flag, Area::Certificates, Kind::Module, true),
            F::Cedula => new FlagDefinition($flag, Area::Certificates, Kind::Module, true),
            F::CedulaTemplates => new FlagDefinition($flag, Area::Certificates, Kind::Option, true, [F::Cedula]),
            F::CedulaInterest => new FlagDefinition($flag, Area::Certificates, Kind::Option, false, [F::Cedula]),
            F::IndigencyCertificate => new FlagDefinition($flag, Area::Certificates, Kind::Module, false),
            F::Blotter => new FlagDefinition($flag, Area::Certificates, Kind::Module, true),
            F::BlotterPhotoAttachment => new FlagDefinition($flag, Area::Certificates, Kind::Option, true, [F::Blotter]),
            F::ClearanceCtcReference => new FlagDefinition($flag, Area::Certificates, Kind::Option, true),
            F::ClearanceDocStamp => new FlagDefinition($flag, Area::Certificates, Kind::Option, true),
            F::ClearanceFingerprints => new FlagDefinition($flag, Area::Certificates, Kind::Option, false, [F::BarangayClearance, F::BiometricCapture]),
            F::ClearanceQrCode => new FlagDefinition($flag, Area::Certificates, Kind::Option, true),
            F::DocumentReleaseStep => new FlagDefinition($flag, Area::Certificates, Kind::Option, true),
            F::CaptainSignatureOnClearance => new FlagDefinition($flag, Area::Certificates, Kind::Option, false),
            F::SecretaryApprovalBlock => new FlagDefinition($flag, Area::Certificates, Kind::Option, true, [F::BarangayClearance]),
            F::CustomPrintTemplates => new FlagDefinition($flag, Area::Certificates, Kind::Option, false),
            F::TemplateShortcodes => new FlagDefinition($flag, Area::Certificates, Kind::Option, true),
            F::ReportDesigner => new FlagDefinition($flag, Area::Certificates, Kind::Module, false),

            F::FormsEngine => new FlagDefinition($flag, Area::Forms, Kind::Module, true),
            F::FormsRichTextTemplates => new FlagDefinition($flag, Area::Forms, Kind::Option, true, [F::FormsEngine]),
            F::PdfFormTemplates => new FlagDefinition($flag, Area::Forms, Kind::Option, true, [F::FormsEngine]),
            F::FormsTablePicklists => new FlagDefinition($flag, Area::Forms, Kind::Option, true, [F::FormsEngine]),
            F::FormsAutoPrintOnSave => new FlagDefinition($flag, Area::Forms, Kind::Option, true, [F::FormsEngine]),
            F::SqlReportBuilder => new FlagDefinition($flag, Area::Forms, Kind::Module, false),
            F::ReportGovernmentIdColumns => new FlagDefinition($flag, Area::Forms, Kind::Option, false, [F::SqlReportBuilder]),

            F::Finance => new FlagDefinition($flag, Area::Finance, Kind::Module, true),
            F::DirectCheckIssuance => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::Finance]),
            F::BudgetCopyPrevious => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::Finance]),
            F::BudgetRevision => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::Finance]),
            F::BudgetUnappropriatedRow => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::Finance]),
            F::VoucherOthersAutoRelease => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::Finance]),
            F::CashAdvanceLiquidation => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::Finance]),
            F::LiquidationClearing => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::CashAdvanceLiquidation]),
            F::CheckPrinting => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::Finance]),
            F::AmountInWordsUppercase => new FlagDefinition($flag, Area::Finance, Kind::Option, false, [F::Finance]),
            F::DvPrintRequiresCheck => new FlagDefinition($flag, Area::Finance, Kind::Option, true, [F::Finance]),
            F::OtherCollections => new FlagDefinition($flag, Area::Finance, Kind::Module, true),
            F::ConsolidatedCollectionReport => new FlagDefinition($flag, Area::Finance, Kind::Option, true),
            F::AccountingTicketsViewer => new FlagDefinition($flag, Area::Finance, Kind::Option, false, [F::Finance]),
            F::AccountingReports => new FlagDefinition($flag, Area::Finance, Kind::Option, false, [F::Finance]),
            F::GlLockedAccountEdit => new FlagDefinition($flag, Area::Finance, Kind::Option, false, [F::Finance]),
        };
    }

    /**
     * @return list<FlagDefinition>
     */
    public static function all(): array
    {
        return array_map(self::definition(...), F::cases());
    }
}
