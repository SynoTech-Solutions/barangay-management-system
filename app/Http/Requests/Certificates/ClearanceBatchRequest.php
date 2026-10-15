<?php

namespace App\Http\Requests\Certificates;

use App\Http\Requests\Documents\DocumentBatchRequest;
use App\Models\BarangayClearance;

class ClearanceBatchRequest extends DocumentBatchRequest
{
    protected function model(): string
    {
        return BarangayClearance::class;
    }
}
