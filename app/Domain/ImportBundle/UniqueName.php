<?php

namespace App\Domain\ImportBundle;

final class UniqueName
{
    public static function candidate(string $base, int $attempt, int $max): string
    {
        if ($attempt <= 1) {
            return mb_substr($base, 0, $max);
        }

        $suffix = " ({$attempt})";

        return rtrim(mb_substr($base, 0, $max - mb_strlen($suffix))).$suffix;
    }

    public static function username(string $username, string $scope, int $attempt, int $max): string
    {
        $suffix = '-'.$scope.($attempt > 1 ? '-'.$attempt : '');

        return mb_substr($username, 0, $max - mb_strlen($suffix)).$suffix;
    }
}
