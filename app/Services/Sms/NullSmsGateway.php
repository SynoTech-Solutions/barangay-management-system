<?php

namespace App\Services\Sms;

final readonly class NullSmsGateway implements SmsGateway
{
    public function send(OutgoingSms $message): SendResult
    {
        return SendResult::permanent(SmsErrorCodes::NOT_CONFIGURED);
    }
}
