<?php

namespace App\Services\ImportBundle;

use App\Enums\BundleEntity;
use App\Enums\ImportProblem;
use App\Services\ImportBundle\Importers\VouchersImporter;
use stdClass;

final class BundleValidator
{
    public const int DETAIL_LIMIT = 20;

    private const array NESTED_KEYS = [
        VouchersImporter::ITEM_ENTITY => [BundleEntity::Vouchers, 'items'],
    ];

    /**
     * @var list<array{entity: string, key: string, pointer: string, keyword: string}>
     */
    private array $details = [];

    public function __construct(private readonly SchemaValidator $schemas) {}

    /**
     * @return list<array{entity: string, key: string, pointer: string, keyword: string}>
     */
    public function validate(Bundle $bundle, ImportTally $tally): array
    {
        $this->details = [];
        $nested = [];

        foreach ($bundle->present() as $entity) {
            $tally->start($entity->value);
            $file = $bundle->file($entity);
            if (! is_file($file)) {
                $tally->record($entity->value, ImportTally::SEVERITY_ERROR, '*', ImportProblem::EntityFileMissing);

                continue;
            }

            $keys = [];
            $lines = 0;
            foreach (JsonLines::lines($file) as $number => $line) {
                $lines++;
                $tally->read($entity->value, 1);
                $data = JsonLines::decodeObject($line);
                if (! $data instanceof stdClass) {
                    $tally->record($entity->value, ImportTally::SEVERITY_ERROR, JsonLines::lineKey($number), ImportProblem::InvalidJson);

                    continue;
                }

                $key = is_string($data->source_key ?? null) && $data->source_key !== '' ? $data->source_key : JsonLines::lineKey($number);
                $errors = $this->schemas->errors($entity->schemaFile(), $data);
                if ($errors !== []) {
                    $tally->record($entity->value, ImportTally::SEVERITY_ERROR, $key, ImportProblem::SchemaInvalid);
                    foreach ($errors as $error) {
                        $this->detail($entity->value, $key, $error);
                    }

                    continue;
                }

                if (isset($keys[$key])) {
                    $tally->record($entity->value, ImportTally::SEVERITY_ERROR, $key, ImportProblem::DuplicateSourceKey);

                    continue;
                }
                $keys[$key] = true;

                foreach (self::NESTED_KEYS as $nestedEntity => [$owner, $field]) {
                    if ($owner !== $entity) {
                        continue;
                    }

                    foreach ((array) ($data->{$field} ?? []) as $item) {
                        $itemKey = $item instanceof stdClass && is_string($item->source_key ?? null) ? $item->source_key : '';
                        if (isset($nested[$nestedEntity][$itemKey])) {
                            $tally->record($entity->value, ImportTally::SEVERITY_ERROR, $key, ImportProblem::DuplicateSourceKey);
                        }
                        $nested[$nestedEntity][$itemKey] = true;
                    }
                }
            }

            if ($lines !== $bundle->count($entity)) {
                $tally->record($entity->value, ImportTally::SEVERITY_ERROR, '*', ImportProblem::CountMismatch);
            }
        }

        return $this->details;
    }

    /**
     * @param  array{pointer: string, keyword: string}  $error
     */
    private function detail(string $entity, string $key, array $error): void
    {
        $this->details[] = ['entity' => $entity, 'key' => $key, 'pointer' => $error['pointer'], 'keyword' => $error['keyword']];
    }
}
