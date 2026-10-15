<?php

namespace App\Services\Audit;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

final class AuditSnapshot
{
    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function of(Model $model, array $keys): array
    {
        $snapshot = [];

        foreach ($keys as $key) {
            $value = $model->getAttribute($key);
            $snapshot[$key] = match (true) {
                $value instanceof DateTimeInterface => $value->format(self::DATE_FORMAT),
                $value instanceof BackedEnum => $value->value,
                default => $value,
            };
        }

        return $snapshot;
    }
}
