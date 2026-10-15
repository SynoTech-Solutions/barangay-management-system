<?php

namespace App\Domain\Finance;

use InvalidArgumentException;

final readonly class FundBalance
{
    private function __construct(
        public int $appropriation,
        public int $consumed,
    ) {}

    /**
     * @param  iterable<ConsumptionEntry>  $entries
     */
    public static function of(?int $appropriation, iterable $entries): self
    {
        $appropriation ??= 0;
        if ($appropriation < 0) {
            throw new InvalidArgumentException('An appropriation cannot be negative.');
        }

        $consumed = 0;
        foreach ($entries as $entry) {
            if (! $entry->cancelled) {
                $consumed += $entry->effectiveAmount();
            }
        }

        return new self($appropriation, $consumed);
    }

    public function available(): int
    {
        return $this->appropriation - $this->consumed;
    }

    public function isOverdrawn(): bool
    {
        return $this->available() < 0;
    }

    public function canCover(int $amount): bool
    {
        return $amount > 0 && $amount <= $this->available();
    }
}
