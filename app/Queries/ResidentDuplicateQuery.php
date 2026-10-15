<?php

namespace App\Queries;

use App\Domain\Residents\NameNormalizer;
use App\Models\Resident;

final class ResidentDuplicateQuery
{
    public function find(string $lastName, string $firstName, ?string $birthdate, ?int $exceptId = null): ?Resident
    {
        return Resident::query()
            ->whereRaw('lower(last_name) = ?', [NameNormalizer::key($lastName)])
            ->whereRaw('lower(first_name) = ?', [NameNormalizer::key($firstName)])
            ->whereRaw('birthdate is not distinct from cast(? as date)', [$birthdate])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('id')
            ->first();
    }
}
