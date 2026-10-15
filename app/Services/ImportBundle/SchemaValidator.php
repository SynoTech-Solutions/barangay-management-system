<?php

namespace App\Services\ImportBundle;

use App\Domain\ImportBundle\BundleFormat;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;

final class SchemaValidator
{
    public const string ID_PREFIX = 'https://schemas.bms.local/import-bundle/';

    public const string RESOURCE_DIRECTORY = 'import-bundle';

    private const int MAX_ERRORS = 10;

    private const string POINTER_SEPARATOR = '/';

    private readonly Validator $validator;

    private readonly string $prefix;

    public function __construct(int $version = BundleFormat::VERSION)
    {
        $this->prefix = self::ID_PREFIX.BundleFormat::schemaDirectory($version).'/';
        $this->validator = new Validator;
        $this->validator->setMaxErrors(self::MAX_ERRORS);
        $this->validator->resolver()?->registerPrefix($this->prefix, self::directory($version));
    }

    public static function directory(int $version): string
    {
        return resource_path(self::RESOURCE_DIRECTORY.DIRECTORY_SEPARATOR.BundleFormat::schemaDirectory($version));
    }

    /**
     * @return list<array{pointer: string, keyword: string}>
     */
    public function errors(string $schemaFile, mixed $data): array
    {
        $error = $this->validator->validate($data, $this->prefix.$schemaFile)->error();
        if ($error === null) {
            return [];
        }

        $found = [];
        $this->collect($error, $found);

        return array_values(array_unique($found, SORT_REGULAR));
    }

    /**
     * @param  list<array{pointer: string, keyword: string}>  $found
     */
    private function collect(ValidationError $error, array &$found): void
    {
        if ($error->subErrors() !== []) {
            foreach ($error->subErrors() as $sub) {
                $this->collect($sub, $found);
            }

            return;
        }

        $pointer = self::POINTER_SEPARATOR.implode(self::POINTER_SEPARATOR, array_map('strval', $error->data()->fullPath()));
        $args = $error->args();
        $names = match ($error->keyword()) {
            'additionalProperties', 'properties' => $args['properties'] ?? (isset($args['property']) ? [$args['property']] : []),
            'required' => $args['missing'] ?? [],
            default => [],
        };

        if (! is_array($names) || $names === []) {
            $found[] = ['pointer' => $pointer, 'keyword' => $error->keyword()];

            return;
        }

        foreach ($names as $name) {
            $found[] = ['pointer' => rtrim($pointer, self::POINTER_SEPARATOR).self::POINTER_SEPARATOR.(is_scalar($name) ? (string) $name : ''), 'keyword' => $error->keyword()];
        }
    }
}
