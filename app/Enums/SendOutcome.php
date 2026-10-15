<?php

namespace App\Enums;

enum SendOutcome: string
{
    case Accepted = 'accepted';
    case Retryable = 'retryable';
    case Permanent = 'permanent';
}
