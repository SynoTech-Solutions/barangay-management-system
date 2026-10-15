<?php

namespace App\Domain\Finance;

final class BudgetRevision
{
    /**
     * @param  array<int, int>  $previous
     * @param  array<int, int>  $next
     * @return array<int, int>
     */
    public static function deltas(array $previous, array $next): array
    {
        $deltas = [];
        foreach (array_keys($previous + $next) as $accountId) {
            $delta = ($next[$accountId] ?? 0) - ($previous[$accountId] ?? 0);
            if ($delta !== 0) {
                $deltas[$accountId] = $delta;
            }
        }

        return $deltas;
    }

    /**
     * @param  array<int, int>  $previous
     * @param  array<int, int>  $next
     */
    public static function imbalance(array $previous, array $next): int
    {
        return array_sum($next) - array_sum($previous);
    }
}
