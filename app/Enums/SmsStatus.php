<?php

namespace App\Enums;

enum SmsStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public static function finished(): array
    {
        return [self::Sent, self::Failed, self::Cancelled];
    }

    public function labelKey(): string
    {
        return "sms.statuses.{$this->value}";
    }
}
