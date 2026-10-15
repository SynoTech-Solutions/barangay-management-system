<?php

namespace App\Domain\Forms;

use App\Enums\FormInputType;

final readonly class FieldDraft
{
    public function __construct(
        public string $key,
        public string $label,
        public FormInputType $inputType,
        public bool $isRequired,
        public ?int $picklistId,
    ) {}

    /**
     * @return array{key: string, label: string, input_type: string, is_required: bool, form_picklist_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'input_type' => $this->inputType->value,
            'is_required' => $this->isRequired,
            'form_picklist_id' => $this->inputType === FormInputType::Table ? $this->picklistId : null,
        ];
    }
}
