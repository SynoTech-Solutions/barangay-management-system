<?php

namespace App\Http\Requests\IdCards;

use App\Http\Requests\Documents\RequiresCancelReason;

class IdCardCancelRequest extends IdCardBatchRequest
{
    use RequiresCancelReason;
}
