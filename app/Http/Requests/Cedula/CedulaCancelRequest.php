<?php

namespace App\Http\Requests\Cedula;

use App\Http\Requests\Documents\RequiresCancelReason;

class CedulaCancelRequest extends CedulaBatchRequest
{
    use RequiresCancelReason;
}
