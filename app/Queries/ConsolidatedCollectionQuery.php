<?php

namespace App\Queries;

use App\Enums\CollectionSource;
use App\Enums\IssuedDocumentStatus;
use App\Queries\Filters\CollectionReportFilters;
use App\Support\Config\BmsSettings;
use App\Tenancy\CurrentBarangay;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final readonly class ConsolidatedCollectionQuery
{
    public const int PRINT_LIMIT = 2000;

    private const int CHUNK_SIZE = 500;

    private const string ZERO = '0.00';

    private const string ROW_COLUMNS = 'source, source_id, reference, or_number, payer, type_name, amount::text as amount, doc_stamp::text as doc_stamp, total::text as total, paid_on::text as paid_on';

    private const string SUM_COLUMNS = 'count(*) as count, coalesce(sum(amount), 0)::numeric(14, 2)::text as amount, coalesce(sum(doc_stamp), 0)::numeric(14, 2)::text as doc_stamp, coalesce(sum(total), 0)::numeric(14, 2)::text as total';

    public function __construct(
        private BmsSettings $settings,
        private CurrentBarangay $currentBarangay,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(CollectionReportFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        $page = $this->ordered($filters)->paginate($this->settings->pagination->clamp($perPage))->withQueryString();

        return $page->through(fn (object $row): array => $this->present($row));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forPrint(CollectionReportFilters $filters): array
    {
        return array_values($this->ordered($filters)->limit(self::PRINT_LIMIT)->get()->map(fn (object $row): array => $this->present($row))->values()->all());
    }

    /**
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function lazy(CollectionReportFilters $filters): LazyCollection
    {
        return $this->ordered($filters)->lazy(self::CHUNK_SIZE)->map(fn (object $row): array => $this->present($row));
    }

    /**
     * @return array{count: int, amount: string, doc_stamp: string, total: string}
     */
    public function totals(CollectionReportFilters $filters): array
    {
        $row = DB::query()->fromSub($this->union($filters), 'collections')->selectRaw(self::SUM_COLUMNS)->first();

        return $this->sums($row);
    }

    /**
     * @return list<array{source: string, label: string, count: int, amount: string, doc_stamp: string, total: string}>
     */
    public function bySource(CollectionReportFilters $filters): array
    {
        return array_values(DB::query()->fromSub($this->union($filters), 'collections')
            ->selectRaw('source, '.self::SUM_COLUMNS)
            ->groupBy('source')
            ->orderBy('source')
            ->get()
            ->map(fn (object $row): array => ['source' => $this->text($row, 'source'), 'label' => $this->sourceLabel($this->text($row, 'source')), ...$this->sums($row)])
            ->values()
            ->all());
    }

    /**
     * @return list<array{source: string, label: string, count: int, amount: string, doc_stamp: string, total: string}>
     */
    public function byType(CollectionReportFilters $filters): array
    {
        return array_values(DB::query()->fromSub($this->union($filters), 'collections')
            ->selectRaw('source, type_name, '.self::SUM_COLUMNS)
            ->groupBy('source', 'type_name')
            ->orderBy('source')
            ->orderBy('type_name')
            ->get()
            ->map(fn (object $row): array => [
                'source' => $this->text($row, 'source'),
                'label' => $this->text($row, 'type_name') !== '' ? $this->text($row, 'type_name') : $this->sourceLabel($this->text($row, 'source')),
                ...$this->sums($row),
            ])
            ->values()
            ->all());
    }

    private function ordered(CollectionReportFilters $filters): Builder
    {
        return DB::query()->fromSub($this->union($filters), 'collections')
            ->selectRaw(self::ROW_COLUMNS)
            ->orderBy('paid_on')
            ->orderByRaw('or_number asc nulls last')
            ->orderBy('reference');
    }

    private function union(CollectionReportFilters $filters): Builder
    {
        $sources = $filters->source === null ? CollectionSource::cases() : [$filters->source];
        $branches = array_map(fn (CollectionSource $source): Builder => $this->branch($source, $filters), $sources);
        $union = array_shift($branches);
        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        return $union;
    }

    private function branch(CollectionSource $source, CollectionReportFilters $filters): Builder
    {
        $barangayId = $this->currentBarangay->require()->id;

        if ($source === CollectionSource::OtherCollection) {
            return DB::table('other_collections as d')
                ->join('collection_types as t', 't.id', '=', 'd.collection_type_id')
                ->where('d.barangay_id', $barangayId)
                ->whereNull('d.cancelled_at')
                ->where('d.amount', '>', 0)
                ->whereBetween('d.paid_on', [$filters->from->toDateString(), $filters->to->toDateString()])
                ->selectRaw('?::text as source, d.id as source_id, d.collection_number::text as reference, d.or_number::text as or_number, d.payer_name::text as payer, t.name::text as type_name, d.amount, 0::numeric(12, 2) as doc_stamp, d.amount as total, d.paid_on as paid_on', [$source->value]);
        }

        [$table, $number, $payer, $orNumber, $docStamp] = $this->documentColumns($source);

        return DB::table("{$table} as d")
            ->when(str_starts_with($payer, 'r.'), fn (Builder $query) => $query->join('residents as r', 'r.id', '=', 'd.resident_id'))
            ->where('d.barangay_id', $barangayId)
            ->where('d.status', '<>', IssuedDocumentStatus::Cancelled->value)
            ->whereRaw("d.amount + {$docStamp} > 0")
            ->where('d.issued_at', '>=', $filters->from->startOfDay())
            ->where('d.issued_at', '<', $filters->to->addDay()->startOfDay())
            ->selectRaw("?::text as source, d.id as source_id, d.{$number}::text as reference, {$orNumber}::text as or_number, {$payer}::text as payer, null::text as type_name, d.amount, {$docStamp}::numeric(12, 2) as doc_stamp, d.amount + {$docStamp} as total, d.issued_at::date as paid_on", [$source->value]);
    }

    /**
     * @return array{0: literal-string, 1: literal-string, 2: literal-string, 3: literal-string, 4: literal-string}
     */
    private function documentColumns(CollectionSource $source): array
    {
        return match ($source) {
            CollectionSource::BarangayClearance => ['barangay_clearances', 'clearance_number', 'r.full_name', 'd.or_number', 'd.doc_stamp'],
            CollectionSource::BusinessClearance => ['business_clearances', 'clearance_number', 'd.owner_name', 'd.or_number', 'd.doc_stamp'],
            CollectionSource::Cedula => ['cedulas', 'cedula_number', 'r.full_name', 'null', '0'],
            CollectionSource::IdCard => ['id_cards', 'card_number', 'r.full_name', 'd.or_number', '0'],
            CollectionSource::IndigencyCertificate => ['indigency_certificates', 'certificate_number', 'r.full_name', 'null', '0'],
            CollectionSource::OtherCollection => ['other_collections', 'collection_number', 'd.payer_name', 'd.or_number', '0'],
            CollectionSource::FormTransaction => ['form_transactions', 'transaction_number', 'r.full_name', 'd.or_number', '0'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $source = $this->text($row, 'source');

        return [
            'source' => $source,
            'source_label' => $this->sourceLabel($source),
            'source_id' => (int) ($row->source_id ?? 0),
            'reference' => $this->text($row, 'reference'),
            'or_number' => $this->text($row, 'or_number') !== '' ? $this->text($row, 'or_number') : null,
            'payer' => $this->text($row, 'payer'),
            'type' => $this->text($row, 'type_name') !== '' ? $this->text($row, 'type_name') : $this->sourceLabel($source),
            'amount' => $this->text($row, 'amount'),
            'doc_stamp' => $this->text($row, 'doc_stamp'),
            'total' => $this->text($row, 'total'),
            'paid_on' => $this->text($row, 'paid_on'),
        ];
    }

    /**
     * @return array{count: int, amount: string, doc_stamp: string, total: string}
     */
    private function sums(?object $row): array
    {
        return [
            'count' => (int) ($row->count ?? 0),
            'amount' => $row === null ? self::ZERO : ($this->text($row, 'amount') ?: self::ZERO),
            'doc_stamp' => $row === null ? self::ZERO : ($this->text($row, 'doc_stamp') ?: self::ZERO),
            'total' => $row === null ? self::ZERO : ($this->text($row, 'total') ?: self::ZERO),
        ];
    }

    private function sourceLabel(string $source): string
    {
        $case = CollectionSource::tryFrom($source);

        return $case === null ? $source : (string) __($case->labelKey());
    }

    private function text(object $row, string $column): string
    {
        $value = $row->{$column} ?? null;

        return is_scalar($value) ? (string) $value : '';
    }
}
