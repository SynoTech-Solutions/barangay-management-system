<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use App\Enums\BundleEntity;
use App\Enums\ImportProblem;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AccountsImporter extends BaseImporter
{
    public const string TABLE = 'accounts';

    private const array PARENT_KINDS = [
        AccountKind::Title->value => [AccountKind::Group->value, AccountKind::Title->value],
        AccountKind::Subsidiary->value => [AccountKind::Title->value, AccountKind::Subsidiary->value],
    ];

    public function entity(): BundleEntity
    {
        return BundleEntity::Accounts;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $kind = AccountKind::from($record->requiredText('kind'));
            $existing = $this->accounts($context)->where('code', $record->requiredText('code'))->first(['id', 'kind']);
            if ($existing !== null) {
                $existing->kind === $kind->value
                    ? $this->remember($context, $record, self::TABLE, (int) $existing->id)
                    : $context->failed($record->key(), ImportProblem::AccountKindMismatch);

                continue;
            }

            $id = $kind === AccountKind::Group ? $this->group($context, $record) : $this->item($context, $record, $kind);
            if ($id !== null) {
                $this->remember($context, $record, self::TABLE, $id);
            }
        }
    }

    private function group(ImportContext $context, Record $record): int
    {
        $type = AccountGroupType::from($record->requiredText('group_type'));
        $existing = $this->accounts($context)->where('kind', AccountKind::Group->value)->where('group_type', $type->value)->orderBy('id')->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return $this->insert($context, $record, [
            'parent_id' => null,
            'group_id' => null,
            'kind' => AccountKind::Group->value,
            'group_type' => $type->value,
            'level' => 0,
        ]);
    }

    private function item(ImportContext $context, Record $record, AccountKind $kind): ?int
    {
        $parentId = $this->required($context, $record, 'parent_key', BundleEntity::Accounts);
        if ($parentId === null) {
            return null;
        }

        $parent = $this->accounts($context)->where('id', $parentId)->first(['id', 'kind', 'level', 'group_id']);
        if ($parent === null || ! in_array($parent->kind, self::PARENT_KINDS[$kind->value] ?? [], true)) {
            $context->failed($record->key(), ImportProblem::AccountParentInvalid);

            return null;
        }

        return $this->insert($context, $record, [
            'parent_id' => (int) $parent->id,
            'group_id' => $parent->kind === AccountKind::Group->value ? (int) $parent->id : (int) $parent->group_id,
            'kind' => $kind->value,
            'group_type' => null,
            'level' => (int) $parent->level + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $shape
     */
    private function insert(ImportContext $context, Record $record, array $shape): int
    {
        $id = TableWriter::reserveId(self::TABLE);
        TableWriter::insert(self::TABLE, [[
            ...$shape,
            'id' => $id,
            'barangay_id' => $context->barangayId(),
            'code' => $record->requiredText('code'),
            'name' => $record->requiredText('name'),
            'remarks' => $record->text('remarks'),
            'is_locked' => $record->bool('is_locked'),
            'is_active' => $record->bool('is_active', true),
            'created_at' => $context->timestamp,
            'updated_at' => $context->timestamp,
        ]]);

        return $id;
    }

    private function accounts(ImportContext $context): Builder
    {
        return DB::table(self::TABLE)->where('barangay_id', $context->barangayId());
    }
}
