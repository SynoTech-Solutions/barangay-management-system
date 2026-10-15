<?php

namespace App\Services\Sms;

interface SmsGateway
{
    public function send(OutgoingSms $message): SendResult;
}
