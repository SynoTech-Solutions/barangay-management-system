<?php

namespace App\Http\Requests\Certificates;

use App\Http\Requests\Documents\DocumentBatchRequest;
use App\Models\BusinessClearance;

class BusinessClearanceBatchRequest extends DocumentBatchRequest
{
    protected function model(): string
    {
        return BusinessClearance::class;
    }
}
