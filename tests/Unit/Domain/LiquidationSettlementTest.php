<?php

namespace Tests\Unit\Domain;

use App\Domain\Finance\LiquidationSettlement;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LiquidationSettlementTest extends TestCase
{
    public function test_spending_less_than_the_advance_is_a_refund_that_needs_a_receipt(): void
    {
        $settlement = new LiquidationSettlement(500_000, 420_050);

        $this->assertSame([79_950, 0, true], [$settlement->refund(), $settlement->reimbursement(), $settlement->needsReceipt()]);
    }

    public function test_spending_more_than_the_advance_is_a_reimbursement(): void
    {
        $settlement = new LiquidationSettlement(500_000, 512_000);

        $this->assertSame([0, 12_000, false], [$settlement->refund(), $settlement->reimbursement(), $settlement->needsReceipt()]);
    }

    public function test_spending_exactly_the_advance_settles_to_zero(): void
    {
        $settlement = new LiquidationSettlement(500_000, 500_000);

        $this->assertSame([0, 0, false], [$settlement->refund(), $settlement->reimbursement(), $settlement->needsReceipt()]);
    }

    public function test_spending_nothing_refunds_everything(): void
    {
        $this->assertSame(500_000, (new LiquidationSettlement(500_000, 0))->refund());
    }

    public function test_negative_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LiquidationSettlement(100, -1);
    }
}
