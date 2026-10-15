<?php

namespace App\Services\Reports;

use App\Domain\Reports\ReportDefinition;
use App\Enums\AuditAction;
use App\Models\SavedReport;
use App\Models\User;
use App\Queries\SavedReportQuery;
use App\Services\Audit\AuditTrail;
use Generator;

final readonly class SavedReportCsvExporter
{
    private const string SUBJECT = 'saved-report';

    public function __construct(
        private SavedReportQuery $query,
        private AuditTrail $audit,
    ) {}

    public function recordExport(SavedReport $report, ReportDefinition $definition, User $actor): void
    {
        $this->audit->record(AuditAction::Exported, $report, after: [
            'subject' => self::SUBJECT,
            'dataset' => $definition->dataset->value,
            'columns' => $this->query->headers($definition),
        ], barangayId: $report->barangay_id, actorId: $actor->id);
    }

    /**
     * @return list<string>
     */
    public function header(ReportDefinition $definition): array
    {
        return array_map(fn (string $column): string => (string) __("reports.saved.columns.{$column}"), $this->query->headers($definition));
    }

    /**
     * @return Generator<int, list<string>>
     */
    public function rows(ReportDefinition $definition): Generator
    {
        foreach ($this->query->lazy($definition) as $row) {
            yield array_values($row);
        }
    }
}
