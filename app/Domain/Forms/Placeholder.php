<?php

namespace App\Domain\Forms;

final class Placeholder
{
    public const string PATTERN = '/\{\{\s*([a-z]+\.[a-z][a-z0-9_]*)\s*\}\}/';

    public const string FIELD_NAMESPACE = 'field';

    private const string SEPARATOR = '.';

    public static function token(string $namespace, string $key): string
    {
        return $namespace.self::SEPARATOR.$key;
    }

    public static function field(string $key): string
    {
        return self::token(self::FIELD_NAMESPACE, $key);
    }

    public static function wrap(string $token): string
    {
        return '{{'.$token.'}}';
    }

    /**
     * @return list<string>
     */
    public static function tokensIn(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  list<string>  $fieldKeys
     * @return list<string>
     */
    public static function unknownTokens(string $template, array $fieldKeys): array
    {
        $known = array_flip([...PlaceholderCatalog::tokens(), ...array_map(self::field(...), $fieldKeys)]);

        return array_values(array_filter(self::tokensIn($template), fn (string $token): bool => ! isset($known[$token])));
    }
}
