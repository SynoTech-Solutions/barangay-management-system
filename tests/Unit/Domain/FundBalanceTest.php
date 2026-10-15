<?php

namespace Tests\Unit\Domain;

use App\Domain\Finance\ConsumptionEntry;
use App\Domain\Finance\FundBalance;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FundBalanceTest extends TestCase
{
    public function test_available_is_the_final_appropriation_minus_items_that_are_not_cancelled(): void
    {
        $balance = FundBalance::of(100_000, [new ConsumptionEntry(25_050), new ConsumptionEntry(10_000)]);

        $this->assertSame(100_000, $balance->appropriation);
        $this->assertSame(35_050, $balance->consumed);
        $this->assertSame(64_950, $balance->available());
        $this->assertFalse($balance->isOverdrawn());
    }

    public function test_a_missing_appropriation_counts_as_zero(): void
    {
        $balance = FundBalance::of(null, [new ConsumptionEntry(500)]);

        $this->assertSame(0, $balance->appropriation);
        $this->assertSame(-500, $balance->available());
    }

    public function test_cancelled_items_return_their_money_to_the_fund(): void
    {
        $balance = FundBalance::of(10_000, [new ConsumptionEntry(4_000, cancelled: true), new ConsumptionEntry(1_000)]);

        $this->assertSame(9_000, $balance->available());
    }

    public function test_the_actual_expense_replaces_the_original_amount_after_liquidation(): void
    {
        $refund = FundBalance::of(10_000, [new ConsumptionEntry(5_000, actualAmount: 4_200)]);
        $reimbursement = FundBalance::of(10_000, [new ConsumptionEntry(5_000, actualAmount: 6_000)]);

        $this->assertSame(5_800, $refund->available());
        $this->assertSame(4_000, $reimbursement->available());
    }

    public function test_zero_balance_covers_nothing_and_zero_amounts_are_never_covered(): void
    {
        $empty = FundBalance::of(0, []);
        $exact = FundBalance::of(5_000, [new ConsumptionEntry(5_000)]);
        $open = FundBalance::of(5_000, []);

        $this->assertSame(0, $empty->available());
        $this->assertFalse($empty->canCover(1));
        $this->assertFalse($exact->canCover(1));
        $this->assertFalse($open->canCover(0));
        $this->assertTrue($open->canCover(5_000));
        $this->assertFalse($open->canCover(5_001));
    }

    public function test_an_overdrawn_fund_is_flagged_and_covers_nothing(): void
    {
        $balance = FundBalance::of(1_000, [new ConsumptionEntry(1_500)]);

        $this->assertTrue($balance->isOverdrawn());
        $this->assertSame(-500, $balance->available());
        $this->assertFalse($balance->canCover(1));
    }

    public function test_negative_appropriations_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FundBalance::of(-1, []);
    }

    public function test_negative_consumption_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConsumptionEntry(100, actualAmount: -1);
    }
}
