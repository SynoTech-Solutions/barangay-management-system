<?php

namespace App\Support;

final class Like
{
    public static function contains(string $term): string
    {
        return '%'.self::escape($term).'%';
    }

    public static function startsWith(string $term): string
    {
        return self::escape($term).'%';
    }

    private static function escape(string $term): string
    {
        return addcslashes($term, '%_\\');
    }
}
