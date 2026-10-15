<?php

namespace App\Services\Finance;

use App\Enums\AuditAction;
use App\Models\User;
use App\Queries\ConsolidatedCollectionQuery;
use App\Queries\Filters\CollectionReportFilters;
use App\Services\Audit\AuditTrail;
use Generator;

final readonly class CollectionCsvExporter
{
    public const array COLUMNS = ['paid_on', 'source_label', 'type', 'reference', 'or_number', 'payer', 'amount', 'doc_stamp', 'total'];

    public const string AUDIT_SUBJECT = 'consolidated-collections';

    private const string FORMULA_PREFIXES = '=+-@';

    private const string FORMULA_GUARD = "'";

    public function __construct(
        private ConsolidatedCollectionQuery $query,
        private AuditTrail $audit,
    ) {}

    public function recordExport(CollectionReportFilters $filters, int $barangayId, User $actor): void
    {
        $this->audit->record(AuditAction::Exported, after: ['subject' => self::AUDIT_SUBJECT, 'filters' => $filters->toArray()], barangayId: $barangayId, actorId: $actor->id);
    }

    /**
     * @return list<string>
     */
    public function header(): array
    {
        return array_map(fn (string $column): string => (string) __("finance.export.{$column}"), self::COLUMNS);
    }

    /**
     * @return Generator<int, list<string>>
     */
    public function rows(CollectionReportFilters $filters): Generator
    {
        foreach ($this->query->lazy($filters) as $row) {
            yield array_map(fn (string $column): string => $this->cell($row[$column] ?? null), self::COLUMNS);
        }
    }

    private function cell(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return $text !== '' && ! is_numeric($text) && str_contains(self::FORMULA_PREFIXES, $text[0]) ? self::FORMULA_GUARD.$text : $text;
    }
}
