<?php

namespace App\Services\Forms\Pdf;

use App\Domain\Forms\Pdf\PdfFormField;

final readonly class StoredPdf
{
    /**
     * @param  list<PdfFormField>  $fields
     */
    public function __construct(
        public string $path,
        public string $fileName,
        public int $sizeBytes,
        public string $sha256,
        public array $fields,
        public bool $isNew,
    ) {}

    public function field(string $name): ?PdfFormField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }
}
