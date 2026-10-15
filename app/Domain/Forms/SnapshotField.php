<?php

namespace App\Domain\Forms;

use App\Enums\FormInputType;

final readonly class SnapshotField
{
    public function __construct(
        public string $key,
        public string $label,
        public FormInputType $inputType,
        public string|int|bool|null $value,
        public string $display,
    ) {}

    /**
     * @return array{key: string, label: string, input_type: string, value: string|int|bool|null, display: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'input_type' => $this->inputType->value,
            'value' => $this->value,
            'display' => $this->display,
        ];
    }
}
