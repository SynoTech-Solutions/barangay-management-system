<?php

namespace App\Domain\Forms;

use App\Enums\FormInputType;
use InvalidArgumentException;

final readonly class FormSnapshot
{
    public const int VERSION = 1;

    public const array SUPPORTED_VERSIONS = [1];

    /**
     * @param  list<SnapshotField>  $fields
     * @param  array<string, string>  $tokens
     */
    public function __construct(
        public array $fields,
        public array $tokens,
    ) {}

    /**
     * @return array{v: int, fields: list<array{key: string, label: string, input_type: string, value: string|int|bool|null, display: string}>, tokens: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'v' => self::VERSION,
            'fields' => array_map(fn (SnapshotField $field): array => $field->toArray(), $this->fields),
            'tokens' => $this->tokens,
        ];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! in_array($data['v'] ?? null, self::SUPPORTED_VERSIONS, true)) {
            throw new InvalidArgumentException('Unsupported form snapshot version.');
        }

        $fields = [];
        foreach (is_array($data['fields'] ?? null) ? $data['fields'] : [] as $field) {
            $type = is_array($field) && is_string($field['input_type'] ?? null) ? FormInputType::tryFrom($field['input_type']) : null;
            if (! is_array($field) || $type === null || ! is_string($field['key'] ?? null)) {
                continue;
            }
            $value = $field['value'] ?? null;
            $fields[] = new SnapshotField(
                $field['key'],
                is_string($field['label'] ?? null) ? $field['label'] : $field['key'],
                $type,
                is_string($value) || is_int($value) || is_bool($value) ? $value : null,
                is_scalar($field['display'] ?? null) ? (string) $field['display'] : '',
            );
        }

        $tokens = [];
        foreach (is_array($data['tokens'] ?? null) ? $data['tokens'] : [] as $token => $value) {
            if (is_string($token) && is_scalar($value)) {
                $tokens[$token] = (string) $value;
            }
        }

        return new self($fields, $tokens);
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    public function rawValues(): array
    {
        $values = [];
        foreach ($this->fields as $field) {
            $values[$field->key] = $field->value;
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $documentTokens
     * @return array<string, string>
     */
    public function placeholderValues(array $documentTokens): array
    {
        $values = [...$this->tokens, ...$documentTokens];
        foreach ($this->fields as $field) {
            $values[Placeholder::field($field->key)] = $field->display;
        }

        return $values;
    }

    public function field(string $key): ?SnapshotField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }
}
