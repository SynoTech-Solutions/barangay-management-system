<?php

namespace App\Services\ImportBundle;

use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

final class UniqueValues implements ChunkAware
{
    /**
     * @var array<string, true>
     */
    private array $claimed = [];

    public function __construct(private readonly int $barangayId) {}

    /**
     * @param  literal-string  $column
     * @param  list<array{0: string, 1: string, 2: mixed}>  $scope
     */
    public function claim(string $table, string $column, string $value, bool $caseInsensitive = false, array $scope = []): bool
    {
        $slot = $table.'|'.$column.'|'.json_encode($scope).'|'.($caseInsensitive ? mb_strtolower($value) : $value);
        if (isset($this->claimed[$slot])) {
            return false;
        }

        $query = DB::table($table)->where('barangay_id', $this->barangayId)->where($scope);
        $caseInsensitive ? $query->where(new Expression('lower('.$column.')'), mb_strtolower($value)) : $query->where($column, $value);
        if ($query->exists()) {
            return false;
        }

        $this->claimed[$slot] = true;

        return true;
    }

    public function begin(): void
    {
        $this->claimed = [];
    }

    public function commit(): void
    {
        $this->claimed = [];
    }

    public function rollback(): void
    {
        $this->claimed = [];
    }
}
