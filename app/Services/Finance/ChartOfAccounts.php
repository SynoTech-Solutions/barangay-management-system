<?php

namespace App\Services\Finance;

use App\Domain\Finance\StandardChart;
use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\Permission;
use App\Exceptions\BmsException;
use App\Models\Account;
use App\Models\Barangay;
use App\Models\BudgetLine;
use App\Models\User;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use App\Services\Features\FeatureFlags;
use App\Tenancy\CurrentBarangay;
use Illuminate\Support\Facades\DB;

final readonly class ChartOfAccounts
{
    private const string SUBSIDIARY_CODE = '%s-%s';

    private const array PARENT_KINDS = [
        AccountKind::Title->value => [AccountKind::Group, AccountKind::Title],
        AccountKind::Subsidiary->value => [AccountKind::Title, AccountKind::Subsidiary],
    ];

    public function __construct(
        private AuditTrail $audit,
        private FeatureFlags $features,
        private PermissionResolver $permissions,
        private CurrentBarangay $currentBarangay,
    ) {}

    public static function subsidiaryCode(string $parentCode, string $series): string
    {
        return sprintf(self::SUBSIDIARY_CODE, $parentCode, $series);
    }

    /**
     * @param  array{kind: AccountKind, code: string|null, series: string|null, name: string, remarks: string|null, parent_id: int|null, group_type: AccountGroupType|null}  $details
     */
    public function create(array $details, User $actor): Account
    {
        return DB::transaction(function () use ($details, $actor): Account {
            $account = new Account;
            $account->kind = $details['kind'];
            $account->name = $details['name'];
            $account->remarks = $details['remarks'];
            $account->updated_by = $actor->id;

            if ($details['kind'] === AccountKind::Group) {
                $account->code = (string) $details['code'];
                $account->group_type = $details['group_type'];
                $account->level = 0;
            } else {
                $parent = Account::query()->lockForUpdate()->find($details['parent_id']);
                $this->ensureValidParent($parent, $details['kind']);
                assert($parent instanceof Account);
                $this->ensureNoBudgetLines($parent);

                $account->parent_id = $parent->id;
                $account->group_id = $parent->groupId();
                $account->level = $parent->level + 1;
                $account->code = $details['kind'] === AccountKind::Subsidiary
                    ? self::subsidiaryCode($parent->code, (string) $details['series'])
                    : (string) $details['code'];
            }

            $account->save();
            $this->audit->record(AuditAction::AccountSaved, $account, null, AuditSnapshot::of($account, Account::AUDITED_ATTRIBUTES), $account->barangay_id, $actor->id);

            return $account;
        });
    }

    /**
     * @param  array{name: string, remarks: string|null, group_type: AccountGroupType|null}  $details
     */
    public function update(Account $account, array $details, User $actor): Account
    {
        $this->ensureEditable($account, $actor);

        return DB::transaction(function () use ($account, $details, $actor): Account {
            $before = AuditSnapshot::of($account, Account::AUDITED_ATTRIBUTES);
            $account->name = $details['name'];
            $account->remarks = $details['remarks'];
            if ($account->isGroup() && $details['group_type'] !== null) {
                $account->group_type = $details['group_type'];
            }
            $account->updated_by = $actor->id;
            $account->save();

            $this->audit->record(AuditAction::AccountSaved, $account, $before, AuditSnapshot::of($account, Account::AUDITED_ATTRIBUTES), $account->barangay_id, $actor->id);

            return $account;
        });
    }

    public function setActive(Account $account, bool $active, User $actor): void
    {
        $this->ensureEditable($account, $actor);

        DB::transaction(function () use ($account, $active, $actor): void {
            $account->is_active = $active;
            $account->updated_by = $actor->id;
            $account->save();

            $this->audit->record($active ? AuditAction::Reactivated : AuditAction::Deactivated, $account, ['is_active' => ! $active], ['is_active' => $active], $account->barangay_id, $actor->id);
        });
    }

    public function remove(Account $account, User $actor): bool
    {
        if ($account->is_locked) {
            throw BmsException::of(ErrorCode::AccountLocked, ['code' => $account->code]);
        }

        if ($this->isReferenced($account)) {
            $this->setActive($account, false, $actor);

            return false;
        }

        DB::transaction(function () use ($account, $actor): void {
            $before = AuditSnapshot::of($account, Account::AUDITED_ATTRIBUTES);
            $account->delete();

            $this->audit->record(AuditAction::Deleted, $account, $before, null, $account->barangay_id, $actor->id);
        });

        return true;
    }

    public function installStandard(Barangay $barangay): int
    {
        return $this->currentBarangay->runAs($barangay, fn (): int => DB::transaction(function (): int {
            if (Account::query()->exists()) {
                return 0;
            }

            $byCode = [];
            foreach (StandardChart::entries() as $entry) {
                $parent = $entry['parent'] === null ? null : $byCode[$entry['parent']];
                $account = new Account;
                $account->fill([
                    'kind' => $entry['kind'],
                    'group_type' => $entry['group_type'],
                    'code' => $entry['code'],
                    'name' => $entry['name'],
                    'parent_id' => $parent?->id,
                    'group_id' => $parent?->groupId(),
                    'level' => $parent === null ? 0 : $parent->level + 1,
                    'is_locked' => true,
                ]);
                $account->save();
                $byCode[$entry['code']] = $account;
            }

            return count($byCode);
        }));
    }

    public function canEditLocked(User $actor): bool
    {
        return $this->features->enabled(FeatureFlag::GlLockedAccountEdit)
            && $this->permissions->has($actor, Permission::ChartOfAccountsEditLocked);
    }

    private function ensureEditable(Account $account, User $actor): void
    {
        if ($account->is_locked && ! $this->canEditLocked($actor)) {
            throw BmsException::of(ErrorCode::AccountLocked, ['code' => $account->code]);
        }
    }

    private function ensureValidParent(?Account $parent, AccountKind $kind): void
    {
        if ($parent === null || ! $parent->is_active || ! in_array($parent->kind, self::PARENT_KINDS[$kind->value] ?? [], true)) {
            throw BmsException::of(ErrorCode::AccountParentInvalid);
        }
    }

    private function ensureNoBudgetLines(Account $parent): void
    {
        if (BudgetLine::query()->where('account_id', $parent->id)->exists()) {
            throw BmsException::of(ErrorCode::AccountHasBudgetLines, ['code' => $parent->code]);
        }
    }

    private function isReferenced(Account $account): bool
    {
        return Account::query()->where('parent_id', $account->id)->exists()
            || BudgetLine::query()->where('account_id', $account->id)->exists();
    }
}
