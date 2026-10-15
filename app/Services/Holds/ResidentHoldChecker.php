<?php

namespace App\Services\Holds;

use App\Models\Resident;

interface ResidentHoldChecker
{
    public function isOnHold(Resident $resident): bool;

    /**
     * @param  list<int>  $residentIds
     * @return list<int>
     */
    public function heldAmong(array $residentIds): array;
}
