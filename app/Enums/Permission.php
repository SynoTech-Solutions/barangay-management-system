<?php

namespace App\Enums;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case FeaturesView = 'features.view';
    case FeaturesManage = 'features.manage';
    case FeaturesManageGlobal = 'features.manage-global';
    case BarangayProfileManage = 'barangay-profile.manage';
    case LookupsManage = 'lookups.manage';
    case AuditView = 'audit.view';
    case DatabaseReset = 'database.reset';
    case BarangaysManage = 'barangays.manage';
    case BarangaysSwitch = 'barangays.switch';
    case ReportsCrossBarangay = 'reports.cross-barangay';
    case ReportsView = 'reports.view';
    case ReportsDesign = 'reports.design';
    case ReportsGovernmentIds = 'reports.government-ids';

    case ResidentsView = 'residents.view';
    case ResidentsCreate = 'residents.create';
    case ResidentsUpdate = 'residents.update';
    case ResidentsDelete = 'residents.delete';
    case ResidentsBulkEdit = 'residents.bulk-edit';
    case ResidentsExport = 'residents.export';
    case PuroksManage = 'puroks.manage';
    case BiometricsCapture = 'biometrics.capture';
    case PoliticalTagsView = 'political-tags.view';
    case PoliticalTagsManage = 'political-tags.manage';
    case IdCardsIssue = 'id-cards.issue';
    case IdCardsRelease = 'id-cards.release';
    case IdCardsCancel = 'id-cards.cancel';

    case ClearancesView = 'clearances.view';
    case ClearancesIssue = 'clearances.issue';
    case ClearancesRelease = 'clearances.release';
    case ClearancesCancel = 'clearances.cancel';
    case BusinessesView = 'businesses.view';
    case BusinessesManage = 'businesses.manage';
    case CedulaView = 'cedula.view';
    case CedulaIssue = 'cedula.issue';
    case CedulaRelease = 'cedula.release';
    case CedulaCancel = 'cedula.cancel';
    case BlotterView = 'blotter.view';
    case BlotterManage = 'blotter.manage';
    case BlotterResolve = 'blotter.resolve';
    case PrintTemplatesManage = 'print-templates.manage';

    case FormTemplatesManage = 'form-templates.manage';
    case FormTransactionsView = 'form-transactions.view';
    case FormTransactionsCreate = 'form-transactions.create';
    case FormTransactionsRelease = 'form-transactions.release';
    case FormTransactionsCancel = 'form-transactions.cancel';

    case BudgetView = 'budget.view';
    case BudgetPrepare = 'budget.prepare';
    case BudgetApprove = 'budget.approve';
    case BudgetRevise = 'budget.revise';
    case VouchersView = 'vouchers.view';
    case VouchersCreate = 'vouchers.create';
    case VouchersVerify = 'vouchers.verify';
    case VouchersApprove = 'vouchers.approve';
    case VouchersCancel = 'vouchers.cancel';
    case ChecksView = 'checks.view';
    case ChecksIssue = 'checks.issue';
    case ChecksRelease = 'checks.release';
    case ChecksPrint = 'checks.print';
    case ChecksCancel = 'checks.cancel';
    case ChecksIssueDirect = 'checks.issue-direct';
    case LiquidationsManage = 'liquidations.manage';
    case LiquidationsClear = 'liquidations.clear';
    case ChartOfAccountsManage = 'chart-of-accounts.manage';
    case ChartOfAccountsEditLocked = 'chart-of-accounts.edit-locked';
    case SuppliersManage = 'suppliers.manage';
    case BankAccountsManage = 'bank-accounts.manage';
    case CollectionsView = 'collections.view';
    case CollectionsCreate = 'collections.create';
    case CollectionsCancel = 'collections.cancel';
    case CollectionsExport = 'collections.export';
    case CollectionTypesManage = 'collection-types.manage';
    case AccountingReportsView = 'accounting-reports.view';

    case SmsView = 'sms.view';
    case SmsSend = 'sms.send';
    case SmsCancel = 'sms.cancel';
    case SmsGroupsManage = 'sms-groups.manage';
    case SmsTemplatesManage = 'sms-templates.manage';

    public function isGlobalOnly(): bool
    {
        return in_array($this, [
            self::BarangaysManage,
            self::BarangaysSwitch,
            self::ReportsCrossBarangay,
            self::FeaturesManageGlobal,
        ], true);
    }

    public function labelKey(): string
    {
        return "permissions.{$this->value}";
    }
}
