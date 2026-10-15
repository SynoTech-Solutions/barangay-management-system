<?php

namespace App\Domain\Forms;

final readonly class TemplateContent
{
    /**
     * @param  list<FieldDraft>  $fields
     * @param  list<PdfMappingDraft>  $mappings
     */
    public function __construct(
        public ?string $body,
        public array $fields,
        public array $mappings = [],
    ) {}

    /**
     * @return list<string>
     */
    public function fieldKeys(): array
    {
        return array_map(fn (FieldDraft $field): string => $field->key, $this->fields);
    }
}
