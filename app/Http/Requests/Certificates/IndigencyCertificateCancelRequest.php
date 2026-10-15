<?php

namespace App\Http\Requests\Certificates;

use App\Http\Requests\Documents\RequiresCancelReason;

class IndigencyCertificateCancelRequest extends IndigencyCertificateBatchRequest
{
    use RequiresCancelReason;
}
