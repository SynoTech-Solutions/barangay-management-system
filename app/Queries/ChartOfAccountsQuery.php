<?php

namespace App\Queries;

use App\Domain\Finance\AccountTree;
use App\Enums\AccountGroupType;
use App\Models\Account;

final readonly class ChartOfAccountsQuery
{
    /**
     * @return list<Account>
     */
    public function tree(): array
    {
        $accounts = Account::query()->withCount('children')->get()->keyBy('id');
        $order = AccountTree::depthFirst(
            $accounts->map(fn (Account $account): ?int => $account->parent_id)->all(),
            $accounts->map(fn (Account $account): string => $account->code)->all(),
        );

        $tree = [];
        foreach ($order as $id) {
            $account = $accounts->get($id);
            if ($account instanceof Account) {
                $tree[] = $account;
            }
        }

        return $tree;
    }

    /**
     * @param  list<Account>  $accounts
     * @return array<int, AccountGroupType>
     */
    public function groupTypes(array $accounts): array
    {
        $types = [];
        foreach ($accounts as $account) {
            if ($account->isGroup() && $account->group_type !== null) {
                $types[$account->id] = $account->group_type;
            }
        }

        $result = [];
        foreach ($accounts as $account) {
            $result[$account->id] = $types[$account->groupId()] ?? AccountGroupType::Other;
        }

        return $result;
    }

    public static function isPosting(Account $account): bool
    {
        return ! $account->isGroup() && ($account->children_count ?? 0) === 0;
    }
}
