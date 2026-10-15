<?php

namespace App\Services\ImportBundle;

use Generator;
use JsonException;

final class JsonLines
{
    private const int DEPTH = 64;

    public const string LINE_PREFIX = '#';

    /**
     * @return Generator<int, string>
     */
    public static function lines(string $file): Generator
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            $number = 0;
            while (($line = fgets($handle)) !== false) {
                $number++;
                if (trim($line) !== '') {
                    yield $number => $line;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public static function decodeObject(string $line): mixed
    {
        try {
            return json_decode($line, false, self::DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decodeArray(string $line): ?array
    {
        try {
            $value = json_decode($line, true, self::DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($value) && ! array_is_list($value) ? $value : null;
    }

    /**
     * @return Generator<int, list<Record>>
     */
    public static function chunks(string $file, int $size): Generator
    {
        $chunk = [];
        foreach (self::lines($file) as $line) {
            $chunk[] = new Record(self::decodeArray($line) ?? []);
            if (count($chunk) >= $size) {
                yield $chunk;
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }

    public static function lineKey(int $line): string
    {
        return self::LINE_PREFIX.$line;
    }
}
