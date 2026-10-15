<?php

namespace App\Domain\Forms;

final readonly class RenderedTemplate
{
    /**
     * @param  list<string>  $missing
     */
    public function __construct(
        public string $html,
        public array $missing,
    ) {}
}
