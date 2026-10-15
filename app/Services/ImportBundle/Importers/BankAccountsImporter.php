<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Services\ImportBundle\ImportContext;

final class BankAccountsImporter extends BaseImporter
{
    public const string TABLE = 'bank_accounts';

    public function entity(): BundleEntity
    {
        return BundleEntity::BankAccounts;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $id = $this->findOrInsert($context, self::TABLE, 'account_number', $record->requiredText('account_number'), [
                'account_name' => $record->requiredText('account_name'),
                'depository_bank' => $record->requiredText('depository_bank'),
                'branch_address' => $record->text('branch_address'),
                'is_active' => $record->bool('is_active', true),
            ], caseInsensitive: false);

            $this->remember($context, $record, self::TABLE, $id);
        }
    }
}
