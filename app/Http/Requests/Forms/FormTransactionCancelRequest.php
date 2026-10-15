<?php

namespace App\Http\Requests\Forms;

use App\Http\Requests\Documents\RequiresCancelReason;

class FormTransactionCancelRequest extends FormTransactionBatchRequest
{
    use RequiresCancelReason;
}
