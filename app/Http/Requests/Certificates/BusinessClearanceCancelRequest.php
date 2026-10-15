<?php

namespace App\Http\Requests\Certificates;

use App\Http\Requests\Documents\RequiresCancelReason;

class BusinessClearanceCancelRequest extends BusinessClearanceBatchRequest
{
    use RequiresCancelReason;
}
