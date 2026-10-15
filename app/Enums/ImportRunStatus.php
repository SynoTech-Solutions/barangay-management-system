<?php

namespace App\Enums;

enum ImportRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
}
