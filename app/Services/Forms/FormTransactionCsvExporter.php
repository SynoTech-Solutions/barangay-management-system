<?php

namespace App\Services\Forms;

use App\Enums\AuditAction;
use App\Models\FormTransaction;
use App\Models\User;
use App\Queries\Filters\FormTransactionFilters;
use App\Queries\FormTransactionListQuery;
use App\Services\Audit\AuditTrail;
use Generator;

final readonly class FormTransactionCsvExporter
{
    public const array COLUMNS = [
        'transaction_number', 'status', 'resident_number', 'resident_name', 'template_type', 'template', 'or_number', 'amount',
        'issued_at', 'issued_by', 'released_at', 'released_by', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    private const string SUBJECT = 'form-transactions';

    private const string DATE_TIME_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private FormTransactionListQuery $query,
        private AuditTrail $audit,
    ) {}

    public function recordExport(FormTransactionFilters $filters, int $barangayId, User $actor): void
    {
        $this->audit->record(AuditAction::Exported, after: ['subject' => self::SUBJECT, 'filters' => $filters->toArray()], barangayId: $barangayId, actorId: $actor->id);
    }

    /**
     * @return list<string>
     */
    public function header(FormTransactionFilters $filters): array
    {
        return [
            ...array_map(fn (string $column): string => (string) __("forms.export.{$column}"), self::COLUMNS),
            ...array_map(fn (array $column): string => $column['label'], $this->query->fieldColumns($filters)),
        ];
    }

    /**
     * @return Generator<int, list<string>>
     */
    public function rows(FormTransactionFilters $filters): Generator
    {
        $fieldColumns = $this->query->fieldColumns($filters);

        foreach ($this->query->lazy($filters) as $transaction) {
            yield [...$this->row($transaction), ...array_values($this->query->fieldValues($transaction, $fieldColumns))];
        }
    }

    /**
     * @return list<string>
     */
    private function row(FormTransaction $transaction): array
    {
        return [
            $transaction->transaction_number,
            (string) __($transaction->status->labelKey()),
            $transaction->resident->resident_number ?? '',
            $transaction->resident->full_name ?? '',
            $transaction->template->type->label ?? '',
            $transaction->template->name ?? '',
            $transaction->or_number ?? '',
            $transaction->amount,
            $transaction->issued_at->format(self::DATE_TIME_FORMAT),
            $transaction->issuer->name ?? '',
            $transaction->released_at?->format(self::DATE_TIME_FORMAT) ?? '',
            $transaction->releaser->name ?? '',
            $transaction->cancelled_at?->format(self::DATE_TIME_FORMAT) ?? '',
            $transaction->canceller->name ?? '',
            $transaction->cancel_reason ?? '',
        ];
    }
}
