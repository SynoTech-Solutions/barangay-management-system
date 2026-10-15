<?php

namespace App\Queries;

use App\Domain\Reports\ColumnDefinition;
use App\Enums\ReportDataset;
use App\Enums\ReportOperator;
use App\Enums\SortDirection;
use App\Http\Resources\Reports\SavedReportResource;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\SavedReportAccess;
use BackedEnum;

final readonly class SavedReportCatalogQuery
{
    public function __construct(private SavedReportAccess $access) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function active(): array
    {
        return array_values(SavedReport::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (SavedReport $report): array => (new SavedReportResource($report))->resolve())
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        return [
            'datasets' => array_map(fn (ReportDataset $dataset): array => [
                'value' => $dataset->value,
                'label' => (string) __($dataset->labelKey()),
                'columns' => array_map(fn (ColumnDefinition $column): array => [
                    'key' => $column->key,
                    'label' => (string) __("reports.saved.columns.{$column->key}"),
                    'type' => $column->type->value,
                    'groupable' => $column->groupable,
                    'operators' => array_map(fn (ReportOperator $operator): array => [
                        'value' => $operator->value,
                        'label' => (string) __($operator->labelKey()),
                        'needs_value' => $operator->needsValue(),
                    ], ReportOperator::for($column->type)),
                    'choices' => $column->choices === null ? [] : array_map(fn (BackedEnum $case): array => [
                        'value' => (string) $case->value,
                        'label' => method_exists($case, 'labelKey') ? (string) __($case->labelKey()) : (string) $case->value,
                    ], ($column->choices)::cases()),
                ], $this->access->columns($user, $dataset)),
            ], $this->access->datasets($user)),
            'directions' => array_map(fn (SortDirection $direction): array => [
                'value' => $direction->value,
                'label' => (string) __("reports.saved.directions.{$direction->value}"),
            ], SortDirection::cases()),
        ];
    }
}
