<?php

namespace App\Http\Requests\Reports;

use App\Domain\Reports\FilterValueRule;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilter;
use App\Domain\Reports\ReportSort;
use App\Enums\ReportDataset;
use App\Enums\ReportOperator;
use App\Enums\SortDirection;
use App\Models\User;
use App\Services\Reports\SavedReportAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavedReportRequest extends FormRequest
{
    public const int NAME_MAX = 120;

    public const int COLUMNS_MAX = 30;

    public const int FILTERS_MAX = 10;

    public const int SORTS_MAX = 3;

    /**
     * @return array<string, mixed>
     */
    public function rules(SavedReportAccess $access): array
    {
        $user = $this->user();
        $datasets = $user instanceof User ? $access->datasets($user) : [];
        $dataset = ReportDataset::tryFrom($this->string('dataset')->toString());
        $columns = $user instanceof User && $dataset !== null && in_array($dataset, $datasets, true) ? $access->columnMap($user, $dataset) : [];
        $keys = array_keys($columns);
        $groupable = array_keys(array_filter($columns, fn ($column): bool => $column->groupable));

        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'dataset' => ['required', Rule::in(array_map(fn (ReportDataset $item): string => $item->value, $datasets))],
            'columns' => ['required', 'array', 'min:1', 'max:'.self::COLUMNS_MAX],
            'columns.*' => ['required', 'string', 'distinct', Rule::in($keys)],
            'filters' => ['array', 'max:'.self::FILTERS_MAX],
            'filters.*.column' => ['required', 'string', Rule::in($keys)],
            'filters.*.operator' => ['required', Rule::enum(ReportOperator::class)],
            'filters.*.value' => ['nullable', 'string', 'max:'.FilterValueRule::MAX_LENGTH],
            'sort' => ['array', 'max:'.self::SORTS_MAX],
            'sort.*.column' => ['required', 'string', Rule::in($keys)],
            'sort.*.direction' => ['required', Rule::enum(SortDirection::class)],
            'group_by' => ['nullable', 'string', Rule::in($groupable)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(SavedReportAccess $access): array
    {
        return [
            function (Validator $validator) use ($access): void {
                $user = $this->user();
                $dataset = ReportDataset::tryFrom($this->string('dataset')->toString());
                if (! $user instanceof User || $dataset === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $columns = $access->columnMap($user, $dataset);
                foreach ((array) $this->input('filters', []) as $index => $filter) {
                    $column = $columns[(string) ($filter['column'] ?? '')] ?? null;
                    $operator = ReportOperator::tryFrom((string) ($filter['operator'] ?? ''));
                    $value = isset($filter['value']) && is_scalar($filter['value']) ? (string) $filter['value'] : null;
                    if ($column === null || $operator === null || ! FilterValueRule::accepts($column, $operator, $value)) {
                        $validator->errors()->add("filters.{$index}.value", (string) __('reports.saved.invalid_filter'));
                    }
                }
            },
        ];
    }

    public function name(): string
    {
        return $this->string('name')->squish()->toString();
    }

    public function definition(): ReportDefinition
    {
        $filters = [];
        foreach ((array) $this->input('filters', []) as $filter) {
            $operator = ReportOperator::from((string) $filter['operator']);
            $filters[] = new ReportFilter((string) $filter['column'], $operator, $operator->needsValue() ? (string) $filter['value'] : null);
        }

        $sorts = [];
        foreach ((array) $this->input('sort', []) as $sort) {
            $sorts[] = new ReportSort((string) $sort['column'], SortDirection::from((string) $sort['direction']));
        }

        return new ReportDefinition(
            ReportDataset::from($this->string('dataset')->toString()),
            array_values(array_map('strval', (array) $this->input('columns', []))),
            $filters,
            $sorts,
            $this->filled('group_by') ? $this->string('group_by')->toString() : null,
        );
    }
}
