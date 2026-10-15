<?php

namespace App\Http\Requests\Cedula;

use App\Http\Requests\Documents\DocumentBatchRequest;
use App\Models\Cedula;

class CedulaBatchRequest extends DocumentBatchRequest
{
    protected function model(): string
    {
        return Cedula::class;
    }
}
