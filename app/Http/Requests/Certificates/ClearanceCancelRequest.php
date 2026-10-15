<?php

namespace App\Http\Requests\Certificates;

use App\Http\Requests\Documents\RequiresCancelReason;

class ClearanceCancelRequest extends ClearanceBatchRequest
{
    use RequiresCancelReason;
}
