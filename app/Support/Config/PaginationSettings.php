<?php

namespace App\Support\Config;

final readonly class PaginationSettings
{
    public function __construct(
        public int $defaultPageSize,
        public int $maxPageSize,
    ) {}

    public function clamp(?int $requested): int
    {
        if ($requested === null || $requested < 1) {
            return $this->defaultPageSize;
        }

        return min($requested, $this->maxPageSize);
    }
}
