<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use RuntimeException;

class BmsException extends RuntimeException
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly array $context = [],
    ) {
        parent::__construct($errorCode->value);
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function of(ErrorCode $code, array $context = []): self
    {
        return new self($code, $context);
    }

    public function userMessage(): string
    {
        return __($this->errorCode->messageKey(), array_map(
            fn ($value): string => (string) $value,
            $this->context,
        ));
    }
}
