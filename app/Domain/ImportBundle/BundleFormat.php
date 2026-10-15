<?php

namespace App\Domain\ImportBundle;

final class BundleFormat
{
    public const string NAME = 'bms-import-bundle';

    public const int VERSION = 1;

    public const array SUPPORTED_VERSIONS = [self::VERSION];

    public const string MANIFEST_FILE = 'manifest.json';

    public const string FILES_DIRECTORY = 'files';

    public const string MANIFEST_SCHEMA = 'manifest.schema.json';

    public static function schemaDirectory(int $version): string
    {
        return 'v'.$version;
    }

    public static function supports(mixed $version): bool
    {
        return is_int($version) && in_array($version, self::SUPPORTED_VERSIONS, true);
    }
}
