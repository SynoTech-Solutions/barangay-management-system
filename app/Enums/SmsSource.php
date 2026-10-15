<?php

namespace App\Enums;

enum SmsSource: string
{
    case Compose = 'compose';
    case Group = 'group';
    case ResidentList = 'resident-list';
    case Retry = 'retry';

    public function labelKey(): string
    {
        return "sms.sources.{$this->value}";
    }
}
