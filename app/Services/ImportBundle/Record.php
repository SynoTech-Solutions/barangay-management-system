<?php

namespace App\Services\ImportBundle;

use App\Domain\Residents\NameNormalizer;

final readonly class Record
{
    public const string KEY = 'source_key';

    public const string ZERO = '0.00';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public array $data) {}

    public function key(): string
    {
        $key = $this->data[self::KEY] ?? null;

        return is_string($key) ? $key : '';
    }

    public function has(string $field): bool
    {
        return isset($this->data[$field]);
    }

    public function text(string $field): ?string
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) ? NameNormalizer::normalize($value) : null;
    }

    public function requiredText(string $field): string
    {
        return $this->text($field) ?? '';
    }

    public function ref(string $field): ?string
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function raw(string $field): ?string
    {
        $value = $this->data[$field] ?? null;
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function bool(string $field, bool $default = false): bool
    {
        $value = $this->data[$field] ?? null;

        return is_bool($value) ? $value : $default;
    }

    public function int(string $field): ?int
    {
        $value = $this->data[$field] ?? null;

        return is_int($value) ? $value : null;
    }

    public function number(string $field): ?float
    {
        $value = $this->data[$field] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    public function money(string $field, string $default = self::ZERO): string
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function scalar(string $field): string|int|bool|null
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) || is_int($value) || is_bool($value) ? $value : (is_float($value) ? (string) $value : null);
    }

    /**
     * @return list<self>
     */
    public function records(string $field): array
    {
        $items = $this->data[$field] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $records = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $records[] = new self(array_filter($item, fn (mixed $value, int|string $name): bool => is_string($name), ARRAY_FILTER_USE_BOTH));
            }
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    public function strings(string $field): array
    {
        $items = $this->data[$field] ?? null;
        if (! is_array($items)) {
            return [];
        }

        $values = [];
        foreach ($items as $item) {
            $value = is_string($item) ? NameNormalizer::normalize($item) : null;
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
