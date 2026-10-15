<?php

namespace App\Domain\Finance;

use InvalidArgumentException;

final class AccountTree
{
    private const int ROOT = 0;

    /**
     * @param  array<int, int|null>  $parents
     * @param  array<int, int>  $values
     * @return array<int, int>
     */
    public static function rollUp(array $parents, array $values): array
    {
        $totals = array_fill_keys(array_keys($parents), 0);

        foreach ($values as $id => $value) {
            $node = $id;
            $steps = 0;
            while ($node !== null && array_key_exists($node, $parents)) {
                $totals[$node] += $value;
                $node = $parents[$node];
                if (++$steps > count($parents)) {
                    throw new InvalidArgumentException('The account tree contains a cycle.');
                }
            }
        }

        return $totals;
    }

    /**
     * @param  array<int, int|null>  $parents
     * @param  array<int, string>  $sortKeys
     * @return list<int>
     */
    public static function depthFirst(array $parents, array $sortKeys): array
    {
        $children = [];
        foreach ($parents as $id => $parent) {
            $children[$parent !== null && array_key_exists($parent, $parents) ? $parent : self::ROOT][] = $id;
        }

        $ordered = [];
        $visit = function (int $node) use (&$visit, &$ordered, $children, $sortKeys): void {
            $branch = $children[$node] ?? [];
            usort($branch, fn (int $a, int $b): int => strnatcmp($sortKeys[$a] ?? '', $sortKeys[$b] ?? ''));
            foreach ($branch as $child) {
                $ordered[] = $child;
                $visit($child);
            }
        };
        $visit(self::ROOT);

        return $ordered;
    }

    /**
     * @param  array<int, int|null>  $parents
     * @return list<int>
     */
    public static function leaves(array $parents): array
    {
        $withChildren = array_flip(array_filter($parents, fn (?int $parent): bool => $parent !== null));

        return array_values(array_filter(array_keys($parents), fn (int $id): bool => ! array_key_exists($id, $withChildren)));
    }
}
