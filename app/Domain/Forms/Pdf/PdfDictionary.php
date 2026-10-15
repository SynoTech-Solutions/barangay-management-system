<?php

namespace App\Domain\Forms\Pdf;

final readonly class PdfDictionary
{
    /**
     * @param  array<string, mixed>  $entries
     */
    public function __construct(public array $entries) {}

    public function get(string $key): mixed
    {
        return $this->entries[$key] ?? null;
    }

    public function name(string $key): ?string
    {
        $value = $this->get($key);

        return $value instanceof PdfName ? $value->value : null;
    }

    public function integer(string $key): ?int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : null;
    }
}
