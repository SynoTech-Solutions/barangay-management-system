<?php

namespace Tests\Concerns;

use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Jobs\SendSmsMessage;
use App\Models\Barangay;
use App\Models\User;
use App\Services\Sms\SmsDelivery;
use App\Services\Sms\SmsGateway;
use App\Support\Config\BmsSettings;
use App\Tenancy\CurrentBarangay;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeSmsGateway;

trait CreatesSms
{
    use CreatesResidents;

    /**
     * @var list<array{message: string, context: array<mixed>}>
     */
    protected array $logged = [];

    /**
     * @return array{0: User, 1: Barangay}
     */
    protected function smsDesk(SystemRole $role = SystemRole::Secretary): array
    {
        [$user, $barangay] = $this->residentDesk($role);
        $this->turnOn(FeatureFlag::Sms, $barangay);

        return [$user, $barangay];
    }

    protected function fakeGateway(): FakeSmsGateway
    {
        $gateway = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $gateway);

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function configureSms(array $values): void
    {
        foreach ($values as $key => $value) {
            config(["bms.sms.{$key}" => $value]);
        }
        $this->app->forgetInstance(BmsSettings::class);
    }

    protected function runSendJob(int $messageId, Barangay $barangay): SendSmsMessage
    {
        $job = (new SendSmsMessage($messageId, $barangay->id))->withFakeQueueInteractions();
        $job->handle(app(SmsDelivery::class), app(CurrentBarangay::class));

        return $job;
    }

    protected function captureLogs(): void
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->logged[] = ['message' => $event->message, 'context' => $event->context];
        });
    }

    protected function loggedText(): string
    {
        return (string) json_encode($this->logged);
    }
}
