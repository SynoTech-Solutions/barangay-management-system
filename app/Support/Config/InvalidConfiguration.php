<?php

namespace App\Support\Config;

use RuntimeException;

final class InvalidConfiguration extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public static function withProblems(array $problems): self
    {
        return new self('Invalid application configuration: '.implode('; ', $problems));
    }
}
