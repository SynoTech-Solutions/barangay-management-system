<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\ImportBundle\UniqueName;
use App\Enums\BundleEntity;
use App\Enums\ImportProblem;
use App\Enums\RoleScope;
use App\Enums\SystemRole;
use App\Services\Access\RoleSynchronizer;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;
use App\Services\Numbering\DocumentNumberAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class UsersImporter extends BaseImporter
{
    public const string TABLE = 'users';

    public const int USERNAME_MAX = 64;

    private const int UNUSABLE_SECRET_LENGTH = 64;

    public function __construct(DocumentNumberAllocator $numbers, private readonly RoleSynchronizer $roles)
    {
        parent::__construct($numbers);
    }

    public function entity(): BundleEntity
    {
        return BundleEntity::Users;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $role = SystemRole::tryFrom($record->requiredText('role'));
            if ($role === null || $role->scope() !== RoleScope::Barangay) {
                $context->failed($record->key(), ImportProblem::SchemaInvalid);

                continue;
            }

            $username = $this->availableUsername($context, $record, mb_strtolower($record->requiredText('username')));
            $id = TableWriter::reserveId(self::TABLE);
            TableWriter::insert(self::TABLE, [[
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'name' => $record->text('name') ?? $username,
                'username' => $username,
                'designation' => $record->text('designation'),
                'is_active' => false,
                'email' => null,
                'password' => Hash::make(Str::random(self::UNUSABLE_SECRET_LENGTH)),
                'created_at' => $context->utcOrNow($record->raw('created_at')),
                'updated_at' => $context->timestamp,
            ]]);

            TableWriter::insert('user_roles', [[
                'user_id' => $id,
                'role_id' => $this->roles->systemRole($role)->id,
                'barangay_id' => $context->barangayId(),
                'created_at' => $context->timestamp,
                'updated_at' => $context->timestamp,
            ]]);

            $this->remember($context, $record, self::TABLE, $id);
        }
    }

    private function availableUsername(ImportContext $context, Record $record, string $username): string
    {
        $candidate = $username;
        for ($attempt = 1; DB::table(self::TABLE)->whereRaw('lower(username) = lower(?)', [$candidate])->exists(); $attempt++) {
            $candidate = UniqueName::username($username, $context->barangay->psgc_code, $attempt, self::USERNAME_MAX);
        }

        if ($candidate !== $username) {
            $context->warn($record->key(), ImportProblem::UsernameRenamed);
        }

        return $candidate;
    }
}
