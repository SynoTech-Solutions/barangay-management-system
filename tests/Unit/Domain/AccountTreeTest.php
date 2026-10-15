<?php

namespace Tests\Unit\Domain;

use App\Domain\Finance\AccountTree;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AccountTreeTest extends TestCase
{
    private const array PARENTS = [1 => null, 2 => 1, 3 => 2, 4 => 2, 5 => 1, 6 => null];

    public function test_values_roll_up_to_every_ancestor_once(): void
    {
        $totals = AccountTree::rollUp(self::PARENTS, [3 => 100, 4 => 250, 5 => 50, 6 => 7]);

        $this->assertSame([1 => 400, 2 => 350, 3 => 100, 4 => 250, 5 => 50, 6 => 7], $totals);
    }

    public function test_leaves_are_accounts_without_children(): void
    {
        $this->assertSame([3, 4, 5, 6], AccountTree::leaves(self::PARENTS));
    }

    public function test_depth_first_order_sorts_siblings_naturally_by_code(): void
    {
        $codes = [1 => '2', 2 => '2-10', 3 => '2-10-1', 4 => '2-10-2', 5 => '2-9', 6 => '1'];

        $this->assertSame([6, 1, 5, 2, 3, 4], AccountTree::depthFirst(self::PARENTS, $codes));
    }

    public function test_cycles_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccountTree::rollUp([1 => 2, 2 => 1], [1 => 5]);
    }
}
