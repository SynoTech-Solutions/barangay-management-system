<?php

namespace App\Support\Config;

final readonly class AuthSettings
{
    public function __construct(
        public int $loginMaxAttemptsPerMinute,
        public int $twoFactorMaxAttemptsPerMinute,
        public int $passwordMinLength,
        public bool $passwordCheckBreached,
    ) {}
}
