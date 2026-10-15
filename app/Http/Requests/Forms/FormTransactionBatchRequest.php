<?php

namespace App\Http\Requests\Forms;

use App\Http\Requests\Documents\DocumentBatchRequest;
use App\Models\FormTransaction;

class FormTransactionBatchRequest extends DocumentBatchRequest
{
    protected function model(): string
    {
        return FormTransaction::class;
    }
}
