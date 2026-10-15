<?php

namespace App\Services\ImportBundle;

use Illuminate\Support\Facades\DB;

final class TableWriter
{
    private const int BATCH = 500;

    /**
     * @return list<int>
     */
    public static function reserveIds(string $table, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $rows = DB::select("select nextval(pg_get_serial_sequence(?, 'id')) as id from generate_series(1, ?)", [$table, $count]);

        return array_values(array_map(fn (mixed $row): int => (int) data_get($row, 'id'), $rows));
    }

    public static function reserveId(string $table): int
    {
        return self::reserveIds($table, 1)[0];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::BATCH) as $batch) {
            DB::table($table)->insert($batch);
        }
    }
}
