<?php

namespace App\Queries;

use App\Models\Cedula;
use Illuminate\Database\Eloquent\Collection;

final readonly class CedulaReferenceQuery
{
    public const int LIMIT = 10;

    /**
     * @return Collection<int, Cedula>
     */
    public function forResident(int $residentId): Collection
    {
        return Cedula::query()
            ->notCancelled()
            ->where('resident_id', $residentId)
            ->orderByDesc('tax_year')
            ->orderByDesc('issued_at')
            ->limit(self::LIMIT)
            ->get();
    }
}
