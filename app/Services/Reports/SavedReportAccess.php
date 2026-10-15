<?php

namespace App\Services\Reports;

use App\Domain\Reports\ColumnDefinition;
use App\Domain\Reports\DatasetCatalog;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilter;
use App\Domain\Reports\ReportSort;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\Permission;
use App\Enums\ReportColumnSensitivity;
use App\Enums\ReportDataset;
use App\Exceptions\BmsException;
use App\Models\User;
use App\Services\Features\FeatureFlags;

final readonly class SavedReportAccess
{
    public function __construct(private FeatureFlags $features) {}

    /**
     * @return list<ReportDataset>
     */
    public function datasets(User $user): array
    {
        return array_values(array_filter(ReportDataset::cases(), fn (ReportDataset $dataset): bool => $this->canUse($user, $dataset)));
    }

    public function canUse(User $user, ReportDataset $dataset): bool
    {
        [$permission, $flag] = match ($dataset) {
            ReportDataset::Residents => [Permission::ResidentsView, null],
            ReportDataset::BarangayClearances => [Permission::ClearancesView, FeatureFlag::BarangayClearance],
            ReportDataset::BusinessClearances => [Permission::ClearancesView, FeatureFlag::BusinessClearance],
            ReportDataset::Cedulas => [Permission::CedulaView, FeatureFlag::Cedula],
            ReportDataset::Collections => [Permission::CollectionsView, FeatureFlag::OtherCollections],
            ReportDataset::Vouchers => [Permission::VouchersView, FeatureFlag::Finance],
            ReportDataset::FormTransactions => [Permission::FormTransactionsView, FeatureFlag::FormsEngine],
        };

        return $user->can($permission->value) && ($flag === null || $this->features->enabled($flag));
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function columns(User $user, ReportDataset $dataset): array
    {
        $political = $this->features->enabled(FeatureFlag::PoliticalColorTagging) && $user->can(Permission::PoliticalTagsView->value);
        $governmentIds = $this->features->enabled(FeatureFlag::ReportGovernmentIdColumns) && $user->can(Permission::ReportsGovernmentIds->value);

        return array_values(array_filter(DatasetCatalog::columns($dataset), fn (ColumnDefinition $column): bool => match ($column->sensitivity) {
            ReportColumnSensitivity::None => true,
            ReportColumnSensitivity::PoliticalTag => $political,
            ReportColumnSensitivity::GovernmentId => $governmentIds,
        }));
    }

    /**
     * @return array<string, ColumnDefinition>
     */
    public function columnMap(User $user, ReportDataset $dataset): array
    {
        $map = [];
        foreach ($this->columns($user, $dataset) as $column) {
            $map[$column->key] = $column;
        }

        return $map;
    }

    public function visible(User $user, ReportDefinition $definition): ReportDefinition
    {
        if (! $this->canUse($user, $definition->dataset)) {
            throw BmsException::of(ErrorCode::SavedReportUnavailable);
        }

        $allowed = $this->columnMap($user, $definition->dataset);
        foreach ($definition->referencedFilterColumns() as $column) {
            if (! isset($allowed[$column])) {
                throw BmsException::of(ErrorCode::SavedReportUnavailable);
            }
        }

        $columns = array_values(array_filter($definition->columns, fn (string $column): bool => isset($allowed[$column])));
        if ($columns === [] && $definition->groupBy === null) {
            throw BmsException::of(ErrorCode::SavedReportUnavailable);
        }

        return new ReportDefinition(
            $definition->dataset,
            $columns,
            array_values(array_filter($definition->filters, fn (ReportFilter $filter): bool => isset($allowed[$filter->column]))),
            array_values(array_filter($definition->sorts, fn (ReportSort $sort): bool => isset($allowed[$sort->column]))),
            $definition->groupBy,
        );
    }
}
