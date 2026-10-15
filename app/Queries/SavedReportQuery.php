<?php

namespace App\Queries;

use App\Domain\Reports\ColumnDefinition;
use App\Domain\Reports\DatasetCatalog;
use App\Domain\Reports\InvalidReportDefinition;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilter;
use App\Enums\ReportColumnType;
use App\Enums\ReportDataset;
use App\Enums\ReportOperator;
use App\Models\BarangayClearance;
use App\Models\BusinessClearance;
use App\Models\Cedula;
use App\Models\FormTransaction;
use App\Models\OtherCollection;
use App\Models\Resident;
use App\Models\Voucher;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;

final readonly class SavedReportQuery
{
    public const string COUNT_COLUMN = 'count';

    private const int CHUNK_SIZE = 500;

    private const string ALIAS_PREFIX = 'c_';

    private const string COUNT_ALIAS = 'c__count';

    private const array POLITICAL_COLUMNS = ['political_color'];

    /** @var array<string, array<literal-string, literal-string>> */
    private const array EXPRESSIONS = [
        'residents' => [
            'resident_number' => 'residents.resident_number',
            'full_name' => 'residents.full_name',
            'last_name' => 'residents.last_name',
            'first_name' => 'residents.first_name',
            'birthdate' => 'residents.birthdate',
            'age' => "date_part('year', age(current_date, residents.birthdate))::int",
            'gender' => 'residents.gender',
            'civil_status' => 'residents.civil_status',
            'purok' => 'report_puroks.name',
            'street_address' => 'residents.street_address',
            'mobile_number' => 'residents.mobile_number',
            'email' => 'residents.email',
            'is_registered_voter' => 'residents.is_registered_voter',
            'precinct_number' => 'residents.precinct_number',
            'is_senior_citizen_member' => 'residents.is_senior_citizen_member',
            'is_pwd' => 'residents.is_pwd',
            'is_single_parent' => 'residents.is_single_parent',
            'is_indigent' => 'residents.is_indigent',
            'is_deceased' => 'residents.is_deceased',
            'registered_on' => 'residents.created_at::date',
            'political_color' => 'report_colors.name',
            'national_id' => 'residents.national_id',
            'drivers_license' => 'residents.drivers_license',
            'tin' => 'residents.tin',
            'sss_gsis' => 'residents.sss_gsis',
            'pagibig' => 'residents.pagibig',
            'philhealth' => 'residents.philhealth',
            'voter_id' => 'residents.voter_id',
        ],
        'barangay-clearances' => [
            'clearance_number' => 'barangay_clearances.clearance_number',
            'control_number' => 'barangay_clearances.control_number',
            'resident_name' => 'report_residents.full_name',
            'status' => 'barangay_clearances.status',
            'clearance_type' => 'report_types.label',
            'purpose' => 'report_purposes.label',
            'or_number' => 'barangay_clearances.or_number',
            'amount' => 'barangay_clearances.amount',
            'doc_stamp' => 'barangay_clearances.doc_stamp',
            'issued_on' => 'barangay_clearances.issued_at::date',
            'expires_on' => 'barangay_clearances.expires_on',
        ],
        'business-clearances' => [
            'clearance_number' => 'business_clearances.clearance_number',
            'control_number' => 'business_clearances.control_number',
            'business_name' => 'business_clearances.business_name',
            'owner_name' => 'business_clearances.owner_name',
            'status' => 'business_clearances.status',
            'or_number' => 'business_clearances.or_number',
            'amount' => 'business_clearances.amount',
            'doc_stamp' => 'business_clearances.doc_stamp',
            'issued_on' => 'business_clearances.issued_at::date',
            'expires_on' => 'business_clearances.expires_on',
        ],
        'cedulas' => [
            'cedula_number' => 'cedulas.cedula_number',
            'form_number' => 'cedulas.form_number',
            'resident_name' => 'report_residents.full_name',
            'tax_year' => 'cedulas.tax_year',
            'status' => 'cedulas.status',
            'is_exempt' => 'cedulas.is_exempt',
            'basic_tax' => 'cedulas.basic_tax',
            'additional_tax' => 'cedulas.additional_tax',
            'interest' => 'cedulas.interest',
            'amount' => 'cedulas.amount',
            'issued_on' => 'cedulas.issued_at::date',
        ],
        'collections' => [
            'collection_number' => 'other_collections.collection_number',
            'collection_type' => 'report_types.name',
            'payer_name' => 'other_collections.payer_name',
            'or_number' => 'other_collections.or_number',
            'amount' => 'other_collections.amount',
            'paid_on' => 'other_collections.paid_on',
            'is_cancelled' => '(other_collections.cancelled_at is not null)',
        ],
        'vouchers' => [
            'voucher_number' => 'vouchers.voucher_number',
            'voucher_date' => 'vouchers.voucher_date',
            'payee_name' => 'vouchers.payee_name',
            'payment_type' => 'vouchers.payment_type',
            'status' => 'vouchers.status',
            'particulars' => 'vouchers.particulars',
            'amount' => '(select coalesce(sum(report_items.amount), 0) from voucher_items as report_items where report_items.voucher_id = vouchers.id)',
        ],
        'form-transactions' => [
            'transaction_number' => 'form_transactions.transaction_number',
            'template_name' => 'report_templates.name',
            'template_type' => 'report_types.label',
            'resident_name' => 'report_residents.full_name',
            'status' => 'form_transactions.status',
            'or_number' => 'form_transactions.or_number',
            'amount' => 'form_transactions.amount',
            'issued_on' => 'form_transactions.issued_at::date',
            'released_on' => 'form_transactions.released_at::date',
        ],
    ];

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return list<string>
     */
    public function headers(ReportDefinition $definition): array
    {
        if ($definition->groupBy === null) {
            return $definition->columns;
        }

        return [$definition->groupBy, self::COUNT_COLUMN, ...array_values(array_filter(
            $definition->columns,
            fn (string $column): bool => $column !== $definition->groupBy && DatasetCatalog::column($definition->dataset, $column)?->type === ReportColumnType::Money,
        ))];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, string>>
     */
    public function paginate(ReportDefinition $definition, ?int $perPage): LengthAwarePaginator
    {
        $page = $this->builder($definition)->paginate($this->settings->pagination->clamp($perPage))->withQueryString();

        return $page->through(fn (object $row): array => $this->present($definition, $row));
    }

    /**
     * @return LazyCollection<int, array<string, string>>
     */
    public function lazy(ReportDefinition $definition): LazyCollection
    {
        return $this->builder($definition)->lazy(self::CHUNK_SIZE)->map(fn (object $row): array => $this->present($definition, $row));
    }

    private function builder(ReportDefinition $definition): Builder
    {
        $query = $this->base($definition);
        $dataset = $definition->dataset;

        foreach ($definition->filters as $filter) {
            [, $expression] = $this->column($dataset, $filter->column);
            $this->applyFilter($query, $expression, $filter, DatasetCatalog::column($dataset, $filter->column));
        }

        if ($definition->groupBy !== null) {
            [$alias, $group] = $this->column($dataset, $definition->groupBy);
            $query->selectRaw("{$group} as {$alias}")
                ->selectRaw('count(*) as '.self::COUNT_ALIAS)
                ->groupByRaw($group);

            foreach (array_slice($this->headers($definition), 2) as $column) {
                [$sumAlias, $expression] = $this->column($dataset, $column);
                $query->selectRaw("coalesce(sum({$expression}), 0)::numeric(14, 2)::text as {$sumAlias}");
            }

            $this->applySort($query, $definition, [$definition->groupBy]);

            return $query->orderByRaw("{$group} asc nulls last");
        }

        foreach ($definition->columns as $column) {
            [$alias, $expression] = $this->column($dataset, $column);
            $selected = DatasetCatalog::column($dataset, $column)?->type === ReportColumnType::Money ? "({$expression})::numeric(14, 2)::text" : $expression;
            $query->selectRaw("{$selected} as {$alias}");
        }

        $this->applySort($query, $definition, $definition->columns);
        [, $first] = $this->column($dataset, $definition->columns[0]);

        return $query->orderByRaw("{$first} asc nulls last");
    }

    /**
     * @return array{0: literal-string, 1: literal-string}
     */
    private function column(ReportDataset $dataset, string $key): array
    {
        foreach (self::EXPRESSIONS[$dataset->value] as $column => $expression) {
            if ($column === $key) {
                return [self::ALIAS_PREFIX.$column, $expression];
            }
        }

        throw new InvalidReportDefinition('Unknown column.');
    }

    private function base(ReportDefinition $definition): Builder
    {
        $political = array_intersect(self::POLITICAL_COLUMNS, [...$definition->columns, ...$definition->referencedFilterColumns()]) !== [];

        return match ($definition->dataset) {
            ReportDataset::Residents => Resident::query()
                ->leftJoin('puroks as report_puroks', 'report_puroks.id', '=', 'residents.purok_id')
                ->when($political, fn ($query) => $query
                    ->leftJoin('resident_political_tags as report_tags', 'report_tags.resident_id', '=', 'residents.id')
                    ->leftJoin('color_categories as report_colors', 'report_colors.id', '=', 'report_tags.color_category_id'))
                ->toBase(),
            ReportDataset::BarangayClearances => BarangayClearance::query()
                ->join('residents as report_residents', 'report_residents.id', '=', 'barangay_clearances.resident_id')
                ->leftJoin('lookup_values as report_types', 'report_types.id', '=', 'barangay_clearances.clearance_type_id')
                ->leftJoin('lookup_values as report_purposes', 'report_purposes.id', '=', 'barangay_clearances.purpose_id')
                ->toBase(),
            ReportDataset::BusinessClearances => BusinessClearance::query()->toBase(),
            ReportDataset::Cedulas => Cedula::query()
                ->join('residents as report_residents', 'report_residents.id', '=', 'cedulas.resident_id')
                ->toBase(),
            ReportDataset::Collections => OtherCollection::query()
                ->join('collection_types as report_types', 'report_types.id', '=', 'other_collections.collection_type_id')
                ->toBase(),
            ReportDataset::Vouchers => Voucher::query()->toBase(),
            ReportDataset::FormTransactions => FormTransaction::query()
                ->join('form_templates as report_templates', 'report_templates.id', '=', 'form_transactions.form_template_id')
                ->join('residents as report_residents', 'report_residents.id', '=', 'form_transactions.resident_id')
                ->leftJoin('lookup_values as report_types', 'report_types.id', '=', 'report_templates.type_id')
                ->toBase(),
        };
    }

    /**
     * @param  literal-string  $expression
     */
    private function applyFilter(Builder $query, string $expression, ReportFilter $filter, ?ColumnDefinition $column): void
    {
        $type = $column->type ?? ReportColumnType::Text;
        $value = (string) $filter->value;
        $cast = match ($type) {
            ReportColumnType::Date => '?::date',
            ReportColumnType::Number, ReportColumnType::Money => '?::numeric',
            default => '?',
        };
        $text = $type === ReportColumnType::Text;

        match ($filter->operator) {
            ReportOperator::Equals => $text ? $query->whereRaw("lower({$expression}) = lower(?)", [$value]) : $query->whereRaw("{$expression} = {$cast}", [$value]),
            ReportOperator::NotEquals => $text ? $query->whereRaw("lower({$expression}) is distinct from lower(?)", [$value]) : $query->whereRaw("{$expression} is distinct from {$cast}", [$value]),
            ReportOperator::Contains => $query->whereRaw("{$expression} ilike ?", [Like::contains($value)]),
            ReportOperator::StartsWith => $query->whereRaw("{$expression} ilike ?", [Like::startsWith($value)]),
            ReportOperator::GreaterThan => $query->whereRaw("{$expression} > {$cast}", [$value]),
            ReportOperator::AtLeast => $query->whereRaw("{$expression} >= {$cast}", [$value]),
            ReportOperator::LessThan => $query->whereRaw("{$expression} < {$cast}", [$value]),
            ReportOperator::AtMost => $query->whereRaw("{$expression} <= {$cast}", [$value]),
            ReportOperator::IsTrue => $query->whereRaw("{$expression} is true"),
            ReportOperator::IsFalse => $query->whereRaw("{$expression} is not true"),
            ReportOperator::IsEmpty => $query->whereRaw("({$expression} is null or ({$expression})::text = '')"),
            ReportOperator::IsNotEmpty => $query->whereRaw("({$expression} is not null and ({$expression})::text <> '')"),
        };
    }

    /**
     * @param  list<string>  $allowed
     */
    private function applySort(Builder $query, ReportDefinition $definition, array $allowed): void
    {
        foreach ($definition->sorts as $sort) {
            if (in_array($sort->column, $allowed, true)) {
                [, $expression] = $this->column($definition->dataset, $sort->column);
                $query->orderByRaw("{$expression} {$sort->direction->value} nulls last");
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function present(ReportDefinition $definition, object $row): array
    {
        $values = [];
        foreach ($this->headers($definition) as $column) {
            $raw = $row->{$column === self::COUNT_COLUMN ? self::COUNT_ALIAS : self::ALIAS_PREFIX.$column} ?? null;
            $values[$column] = $this->format(DatasetCatalog::column($definition->dataset, $column), $raw);
        }

        return $values;
    }

    private function format(?ColumnDefinition $column, mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if ($column?->type === ReportColumnType::Boolean) {
            return (string) __(in_array($raw, [true, 1, '1', 't', 'true'], true) ? 'reports.saved.yes' : 'reports.saved.no');
        }

        if ($column?->type === ReportColumnType::Choice && $column->choices !== null && is_scalar($raw)) {
            $case = ($column->choices)::tryFrom((string) $raw);

            return $case instanceof BackedEnum && method_exists($case, 'labelKey') ? (string) __($case->labelKey()) : (string) $raw;
        }

        return is_scalar($raw) ? (string) $raw : '';
    }
}
