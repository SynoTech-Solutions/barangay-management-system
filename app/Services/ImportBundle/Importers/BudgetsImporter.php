<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\AccountKind;
use App\Enums\BudgetStatus;
use App\Enums\BundleEntity;
use App\Enums\ImportProblem;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;
use Illuminate\Support\Facades\DB;

final class BudgetsImporter extends BaseImporter
{
    public const string TABLE = 'budgets';

    public const string VERSION_TABLE = 'budget_versions';

    public const string LINE_TABLE = 'budget_lines';

    public function entity(): BundleEntity
    {
        return BundleEntity::Budgets;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $year = (int) $record->int('fiscal_year');
            if (DB::table(self::TABLE)->where('barangay_id', $context->barangayId())->where('fiscal_year', $year)->exists()) {
                $context->skipped($record->key(), ImportProblem::BudgetYearExists);

                continue;
            }

            $versions = $record->records('versions');
            $numbers = array_map(fn (Record $version): int => (int) $version->int('number'), $versions);
            if (count(array_unique($numbers)) !== count($numbers)) {
                $context->failed($record->key(), ImportProblem::DuplicateVersion);

                continue;
            }

            usort($versions, fn (Record $a, Record $b): int => $a->int('number') <=> $b->int('number'));
            $status = BudgetStatus::from($record->requiredText('status'));
            $approvedAt = $status === BudgetStatus::Approved ? $context->utcOrNow($record->raw('approved_at')) : null;
            $budgetId = TableWriter::reserveId(self::TABLE);
            TableWriter::insert(self::TABLE, [[
                'id' => $budgetId,
                'barangay_id' => $context->barangayId(),
                'fiscal_year' => $year,
                'title' => $record->requiredText('title'),
                'status' => $status->value,
                'approved_at' => $approvedAt,
                'created_at' => $context->timestamp,
                'updated_at' => $context->timestamp,
            ]]);

            foreach ($versions as $position => $version) {
                $isLatest = $position === count($versions) - 1;
                $versionId = TableWriter::reserveId(self::VERSION_TABLE);
                TableWriter::insert(self::VERSION_TABLE, [[
                    'id' => $versionId,
                    'barangay_id' => $context->barangayId(),
                    'budget_id' => $budgetId,
                    'number' => (int) $version->int('number'),
                    'remarks' => $version->text('remarks'),
                    'approved_at' => null,
                    'created_at' => $context->timestamp,
                    'updated_at' => $context->timestamp,
                ]]);
                $this->lines($context, $record, $versionId, $version->records('lines'));

                $versionApprovedAt = $context->utc($version->raw('approved_at')) ?? ($isLatest ? $approvedAt : null);
                if ($versionApprovedAt !== null) {
                    DB::table(self::VERSION_TABLE)->where('id', $versionId)->update(['approved_at' => $versionApprovedAt]);
                }
            }

            $this->remember($context, $record, self::TABLE, $budgetId);
        }
    }

    /**
     * @param  list<Record>  $lines
     */
    private function lines(ImportContext $context, Record $budget, int $versionId, array $lines): void
    {
        $rows = [];
        foreach ($lines as $line) {
            $accountId = $this->optional($context, $line, 'account_key', BundleEntity::Accounts, $budget->key());
            if ($accountId === null) {
                continue;
            }

            if (! $this->isPosting($context, $accountId)) {
                $context->warn($budget->key(), ImportProblem::BudgetLineNotPosting);

                continue;
            }

            if (isset($rows[$accountId])) {
                $context->warn($budget->key(), ImportProblem::DuplicateLine);

                continue;
            }

            $rows[$accountId] = [
                'barangay_id' => $context->barangayId(),
                'budget_version_id' => $versionId,
                'account_id' => $accountId,
                'amount' => $line->money('amount'),
                'remarks' => $line->text('remarks'),
                'created_at' => $context->timestamp,
                'updated_at' => $context->timestamp,
            ];
        }

        TableWriter::insert(self::LINE_TABLE, array_values($rows));
    }

    private function isPosting(ImportContext $context, int $accountId): bool
    {
        return DB::table(AccountsImporter::TABLE)
            ->where('barangay_id', $context->barangayId())
            ->where('id', $accountId)
            ->where('kind', '<>', AccountKind::Group->value)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('accounts as children')->whereColumn('children.parent_id', 'accounts.id'))
            ->exists();
    }
}
