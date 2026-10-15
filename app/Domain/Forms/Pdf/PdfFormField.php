<?php

namespace App\Domain\Forms\Pdf;

use App\Enums\PdfFieldType;

final readonly class PdfFormField
{
    public function __construct(
        public string $name,
        public PdfFieldType $type,
        public ?string $onState = null,
    ) {}

    /**
     * @return array{name: string, type: string, on_state: string|null}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'type' => $this->type->value, 'on_state' => $this->onState];
    }
}
