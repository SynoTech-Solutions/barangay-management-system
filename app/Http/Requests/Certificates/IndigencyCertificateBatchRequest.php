<?php

namespace App\Http\Requests\Certificates;

use App\Http\Requests\Documents\DocumentBatchRequest;
use App\Models\IndigencyCertificate;

class IndigencyCertificateBatchRequest extends DocumentBatchRequest
{
    protected function model(): string
    {
        return IndigencyCertificate::class;
    }
}
