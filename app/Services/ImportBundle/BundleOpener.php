<?php

namespace App\Services\ImportBundle;

use App\Domain\ImportBundle\BundleFormat;
use stdClass;

final readonly class BundleOpener
{
    private const string ERROR_SEPARATOR = ', ';

    public function open(string $path): Bundle
    {
        $directory = realpath($path);
        if ($directory === false || ! is_dir($directory) || ! is_readable($directory)) {
            throw new BundleRejected(__('import.errors.not_a_directory', ['path' => $path]));
        }

        $manifestFile = $directory.DIRECTORY_SEPARATOR.BundleFormat::MANIFEST_FILE;
        if (! is_file($manifestFile)) {
            throw new BundleRejected(__('import.errors.manifest_missing'));
        }

        $text = (string) file_get_contents($manifestFile);
        $object = JsonLines::decodeObject($text);
        $data = JsonLines::decodeArray($text);
        if (! $object instanceof stdClass || $data === null) {
            throw new BundleRejected(__('import.errors.manifest_unreadable'));
        }

        if (($data['format'] ?? null) !== BundleFormat::NAME) {
            throw new BundleRejected(__('import.errors.wrong_format', ['expected' => BundleFormat::NAME]));
        }

        $version = $data['version'] ?? null;
        if (! BundleFormat::supports($version)) {
            throw new BundleRejected(__('import.errors.unsupported_version', [
                'version' => is_scalar($version) ? (string) $version : '?',
                'supported' => implode(self::ERROR_SEPARATOR, BundleFormat::SUPPORTED_VERSIONS),
            ]));
        }

        $errors = (new SchemaValidator(BundleFormat::VERSION))->errors(BundleFormat::MANIFEST_SCHEMA, $object);
        if ($errors !== []) {
            throw new BundleRejected(__('import.errors.manifest_invalid', [
                'errors' => implode(self::ERROR_SEPARATOR, array_map(fn (array $error): string => "{$error['pointer']} ({$error['keyword']})", $errors)),
            ]));
        }

        $entities = [];
        foreach ((array) ($data['entities'] ?? []) as $name => $entry) {
            if (is_string($name) && is_array($entry)) {
                $entities[$name] = ['file' => (string) ($entry['file'] ?? ''), 'count' => (int) ($entry['count'] ?? 0)];
            }
        }

        $generator = is_array($data['generator'] ?? null) ? $data['generator'] : [];

        return new Bundle(
            path: $directory,
            version: BundleFormat::VERSION,
            barangayPsgc: is_string($data['barangay_psgc'] ?? null) ? $data['barangay_psgc'] : null,
            generatorName: (string) ($generator['name'] ?? ''),
            generatorVersion: (string) ($generator['version'] ?? ''),
            generatedAt: (string) ($data['generated_at'] ?? ''),
            entities: $entities,
        );
    }
}
