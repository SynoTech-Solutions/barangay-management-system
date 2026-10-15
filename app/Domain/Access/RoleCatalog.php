<?php

namespace App\Domain\Access;

use App\Enums\Permission as P;
use App\Enums\SystemRole;

final class RoleCatalog
{
    /**
     * @return list<P>
     */
    public static function permissionsFor(SystemRole $role): array
    {
        return match ($role) {
            SystemRole::CentralAdmin => P::cases(),
            SystemRole::MunicipalViewer => self::viewPermissions([P::ReportsCrossBarangay, P::BarangaysSwitch]),
            SystemRole::BarangayAdmin => array_values(array_filter(
                P::cases(),
                fn (P $permission): bool => ! $permission->isGlobalOnly(),
            )),
            SystemRole::Secretary => self::viewPermissions([
                P::ResidentsCreate, P::ResidentsUpdate, P::ResidentsBulkEdit, P::ResidentsExport,
                P::BiometricsCapture,
                P::IdCardsIssue, P::IdCardsRelease, P::IdCardsCancel,
                P::ClearancesIssue, P::ClearancesRelease, P::ClearancesCancel,
                P::BusinessesManage,
                P::BlotterView, P::BlotterManage, P::BlotterResolve,
                P::FormTemplatesManage, P::FormTransactionsCreate, P::FormTransactionsRelease, P::FormTransactionsCancel,
                P::ReportsDesign,
                P::SmsSend, P::SmsCancel, P::SmsGroupsManage, P::SmsTemplatesManage,
                P::LookupsManage,
                P::PuroksManage,
            ]),
            SystemRole::Treasurer => self::viewPermissions([
                P::CedulaIssue, P::CedulaRelease, P::CedulaCancel,
                P::CollectionsCreate, P::CollectionsCancel, P::CollectionsExport, P::CollectionTypesManage,
                P::BudgetPrepare, P::BudgetRevise,
                P::VouchersCreate, P::VouchersCancel,
                P::ChecksIssue, P::ChecksRelease, P::ChecksPrint, P::ChecksCancel,
                P::LiquidationsManage,
                P::SuppliersManage, P::BankAccountsManage,
            ]),
            SystemRole::Bookkeeper => self::viewPermissions([
                P::VouchersVerify,
                P::LiquidationsClear,
                P::ChartOfAccountsManage,
                P::CollectionsExport,
            ]),
            SystemRole::Clerk => self::viewPermissions([
                P::ResidentsCreate, P::ResidentsUpdate,
                P::IdCardsIssue,
                P::ClearancesIssue,
                P::CedulaIssue,
                P::BlotterView, P::BlotterManage,
                P::FormTransactionsCreate,
                P::CollectionsCreate,
            ]),
            SystemRole::Viewer => self::viewPermissions(),
        };
    }

    /**
     * @param  list<P>  $extra
     * @return list<P>
     */
    private static function viewPermissions(array $extra = []): array
    {
        return array_values(array_unique([
            P::ResidentsView,
            P::ReportsView,
            P::ClearancesView,
            P::BusinessesView,
            P::CedulaView,
            P::FormTransactionsView,
            P::BudgetView,
            P::VouchersView,
            P::ChecksView,
            P::CollectionsView,
            P::AccountingReportsView,
            P::SmsView,
            ...$extra,
        ], SORT_REGULAR));
    }
}
