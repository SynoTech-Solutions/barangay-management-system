<?php

namespace App\Domain\Reports;

use App\Enums\ReportDataset;
use App\Enums\ReportOperator;
use App\Enums\SortDirection;

final readonly class ReportDefinition
{
    public const int VERSION = 1;

    public const array SUPPORTED_VERSIONS = [1];

    /**
     * @param  list<string>  $columns
     * @param  list<ReportFilter>  $filters
     * @param  list<ReportSort>  $sorts
     */
    public function __construct(
        public ReportDataset $dataset,
        public array $columns,
        public array $filters = [],
        public array $sorts = [],
        public ?string $groupBy = null,
    ) {}

    /**
     * @return array{v: int, dataset: string, columns: list<string>, filters: list<array{column: string, operator: string, value: string|null}>, sort: list<array{column: string, direction: string}>, group_by: string|null}
     */
    public function toArray(): array
    {
        return [
            'v' => self::VERSION,
            'dataset' => $this->dataset->value,
            'columns' => $this->columns,
            'filters' => array_map(fn (ReportFilter $filter): array => ['column' => $filter->column, 'operator' => $filter->operator->value, 'value' => $filter->value], $this->filters),
            'sort' => array_map(fn (ReportSort $sort): array => ['column' => $sort->column, 'direction' => $sort->direction->value], $this->sorts),
            'group_by' => $this->groupBy,
        ];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $version = $data['v'] ?? null;
        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            throw new InvalidReportDefinition('Unsupported report definition version.');
        }

        $dataset = ReportDataset::tryFrom(is_string($data['dataset'] ?? null) ? $data['dataset'] : '');
        if ($dataset === null) {
            throw new InvalidReportDefinition('Unknown dataset.');
        }

        $filters = [];
        foreach (self::list($data['filters'] ?? []) as $filter) {
            $operator = is_array($filter) && is_string($filter['operator'] ?? null) ? ReportOperator::tryFrom($filter['operator']) : null;
            if (! is_array($filter) || ! is_string($filter['column'] ?? null) || $operator === null) {
                throw new InvalidReportDefinition('Malformed filter.');
            }
            $value = $filter['value'] ?? null;
            $filters[] = new ReportFilter($filter['column'], $operator, is_scalar($value) ? (string) $value : null);
        }

        $sorts = [];
        foreach (self::list($data['sort'] ?? []) as $sort) {
            $direction = is_array($sort) && is_string($sort['direction'] ?? null) ? SortDirection::tryFrom($sort['direction']) : null;
            if (! is_array($sort) || ! is_string($sort['column'] ?? null) || $direction === null) {
                throw new InvalidReportDefinition('Malformed sort.');
            }
            $sorts[] = new ReportSort($sort['column'], $direction);
        }

        $columns = array_values(array_filter(self::list($data['columns'] ?? []), 'is_string'));
        $groupBy = $data['group_by'] ?? null;

        return new self($dataset, $columns, $filters, $sorts, is_string($groupBy) ? $groupBy : null);
    }

    /**
     * @return list<string>
     */
    public function referencedFilterColumns(): array
    {
        return array_values(array_unique([
            ...array_map(fn (ReportFilter $filter): string => $filter->column, $this->filters),
            ...($this->groupBy === null ? [] : [$this->groupBy]),
        ]));
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
