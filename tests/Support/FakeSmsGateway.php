<?php

namespace Tests\Support;

use App\Services\Sms\OutgoingSms;
use App\Services\Sms\SendResult;
use App\Services\Sms\SmsGateway;

final class FakeSmsGateway implements SmsGateway
{
    /**
     * @var list<OutgoingSms>
     */
    public array $sent = [];

    /**
     * @var list<SendResult>
     */
    private array $results = [];

    public function willReturn(SendResult ...$results): self
    {
        $this->results = array_values($results);

        return $this;
    }

    public function send(OutgoingSms $message): SendResult
    {
        $this->sent[] = $message;

        return array_shift($this->results) ?? SendResult::accepted('fake-'.$message->messageId);
    }
}
