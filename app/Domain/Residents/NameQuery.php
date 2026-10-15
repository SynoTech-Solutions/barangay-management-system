<?php

namespace App\Domain\Residents;

final readonly class NameQuery
{
    private const string NAME_SEPARATOR = ',';

    /**
     * @param  list<string>  $tokens
     */
    private function __construct(
        public string $raw,
        public ?string $lastName,
        public ?string $firstName,
        public array $tokens,
    ) {}

    public static function parse(string $input): self
    {
        $raw = NameNormalizer::normalize($input) ?? '';

        if (str_contains($raw, self::NAME_SEPARATOR)) {
            [$last, $first] = array_map(
                fn (string $part): ?string => NameNormalizer::normalize($part),
                explode(self::NAME_SEPARATOR, $raw, 2),
            );

            return new self($raw, $last, $first, []);
        }

        return new self($raw, null, null, $raw === '' ? [] : explode(' ', $raw));
    }

    public function isEmpty(): bool
    {
        return $this->lastName === null && $this->firstName === null && $this->tokens === [];
    }

    public function isCommaForm(): bool
    {
        return $this->lastName !== null || $this->firstName !== null;
    }
}
