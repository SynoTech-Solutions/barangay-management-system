<?php

namespace App\Services\Sms;

use App\Domain\Sms\MaskedNumber;
use App\Enums\LogEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class LogSmsGateway implements SmsGateway
{
    private const string ID_PREFIX = 'log-';

    public function send(OutgoingSms $message): SendResult
    {
        $providerId = self::ID_PREFIX.Str::uuid()->toString();

        Log::info(LogEvent::SmsLogDriverDelivered->value, [
            'message_id' => $message->messageId,
            'recipient' => MaskedNumber::of($message->recipient),
            'segments' => $message->segments,
            'characters' => mb_strlen($message->body),
            'provider_message_id' => $providerId,
        ]);

        return SendResult::accepted($providerId);
    }
}
