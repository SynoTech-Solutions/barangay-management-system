<?php

namespace App\Domain\Forms;

final readonly class CheckLabels
{
    public function __construct(
        public string $checked,
        public string $unchecked,
    ) {}
}
