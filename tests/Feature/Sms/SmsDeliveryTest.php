<?php

namespace Tests\Feature\Sms;

use App\Enums\FeatureFlag;
use App\Enums\SmsSource;
use App\Enums\SmsStatus;
use App\Jobs\SendSmsMessage;
use App\Models\Barangay;
use App\Models\SmsBatch;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\OutgoingSms;
use App\Services\Sms\RecipientSet;
use App\Services\Sms\SendResult;
use App\Services\Sms\SmsErrorCodes;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsOutbox;
use App\Services\Sms\SmsRecipient;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\CreatesSms;
use Tests\TestCase;

class SmsDeliveryTest extends TestCase
{
    use CreatesSms, RefreshDatabase;

    private function recipients(string ...$numbers): RecipientSet
    {
        $set = new RecipientSet;
        foreach ($numbers as $number) {
            $set->add(new SmsRecipient($number));
        }

        return $set;
    }

    private function queue(User $user, string $body = 'Barangay assembly on Saturday.', string ...$numbers): SmsBatch
    {
        return app(SmsOutbox::class)->queue($this->recipients(...($numbers ?: ['+639170000001'])), $body, SmsSource::Compose, null, $user);
    }

    public function test_queueing_runs_in_the_callers_transaction_and_a_rollback_leaves_no_rows_and_sends_nothing(): void
    {
        [$user, $barangay] = $this->smsDesk();
        $gateway = $this->fakeGateway();

        try {
            DB::transaction(function () use ($user): void {
                $this->queue($user);
                $this->assertSame(1, SmsMessage::query()->count());

                throw new RuntimeException('caller failed after queueing');
            });
        } catch (RuntimeException) {
        }

        $this->inBarangay($barangay);
        $this->assertSame(0, SmsMessage::query()->count());
        $this->assertSame(0, SmsBatch::query()->count());
        $this->assertSame([], $gateway->sent);
        $this->assertInstanceOf(ShouldQueueAfterCommit::class, new SendSmsMessage(1, 1));

        $this->queue($user);

        $this->assertCount(1, $gateway->sent);
        $message = SmsMessage::query()->sole();
        $this->assertSame(SmsStatus::Sent, $message->status);
        $this->assertSame('fake-'.$message->id, $message->provider_message_id);
        $this->assertSame(1, $message->attempts);
        $this->assertNotNull($message->sent_at);
    }

    public function test_a_message_cancelled_before_its_job_runs_is_never_sent(): void
    {
        [$user, $barangay] = $this->smsDesk();
        $gateway = $this->fakeGateway();
        Queue::fake();

        $this->queue($user, 'Water interruption tomorrow.', '+639170000001', '+639170000002');
        [$first, $second] = SmsMessage::query()->orderBy('id')->get()->all();

        $this->actingAs($user)->post(route('sms.cancel'), ['ids' => [$first->id]])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);

        Queue::assertPushed(SendSmsMessage::class, 2);
        $this->runSendJob($first->id, $barangay)->assertNotReleased();
        $this->runSendJob($second->id, $barangay);

        $this->assertCount(1, $gateway->sent);
        $this->assertSame($second->id, $gateway->sent[0]->messageId);
        $this->assertSame(SmsStatus::Cancelled, $first->refresh()->status);
        $this->assertSame(0, $first->attempts);
        $this->assertSame($user->id, $first->cancelled_by);
    }

    public function test_retryable_errors_release_with_backoff_until_max_attempts_and_permanent_errors_fail_at_once(): void
    {
        [$user, $barangay] = $this->smsDesk();
        $this->configureSms(['max_attempts' => 2, 'retry_base_seconds' => 30]);
        $gateway = $this->fakeGateway()->willReturn(
            SendResult::retryable(SmsErrorCodes::http(503)),
            SendResult::retryable(SmsErrorCodes::TIMEOUT),
            SendResult::permanent(SmsErrorCodes::http(400)),
        );
        Queue::fake();
        $this->queue($user, 'Retry me.', '+639170000001', '+639170000002');
        [$retried, $rejected] = SmsMessage::query()->orderBy('id')->get()->all();

        $this->runSendJob($retried->id, $barangay)->assertReleased(30);
        $retried->refresh();
        $this->assertSame(SmsStatus::Queued, $retried->status);
        $this->assertSame(1, $retried->attempts);
        $this->assertSame('http_503', $retried->last_error_code);

        $this->runSendJob($retried->id, $barangay)->assertNotReleased();
        $retried->refresh();
        $this->assertSame(SmsStatus::Failed, $retried->status);
        $this->assertSame(2, $retried->attempts);
        $this->assertSame('timeout', $retried->last_error_code);

        $this->runSendJob($rejected->id, $barangay)->assertNotReleased();
        $rejected->refresh();
        $this->assertSame(SmsStatus::Failed, $rejected->status);
        $this->assertSame(1, $rejected->attempts);
        $this->assertSame('http_400', $rejected->last_error_code);
        $this->assertCount(3, $gateway->sent);
    }

    public function test_gateway_exceptions_are_treated_as_retryable(): void
    {
        [$user, $barangay] = $this->smsDesk();
        $this->app->instance(SmsGateway::class, new class implements SmsGateway
        {
            public function send(OutgoingSms $message): SendResult
            {
                throw new RuntimeException('socket closed');
            }
        });
        Queue::fake();
        $this->queue($user);
        $message = SmsMessage::query()->sole();

        $this->runSendJob($message->id, $barangay)->assertReleased(60);
        $this->assertSame(SmsErrorCodes::GATEWAY_EXCEPTION, $message->refresh()->last_error_code);
        $this->assertSame(SmsStatus::Queued, $message->status);
    }

    public function test_a_sent_or_sending_message_is_never_sent_again(): void
    {
        [, $barangay] = $this->smsDesk();
        $gateway = $this->fakeGateway();
        $sent = SmsMessage::factory()->sent()->create();
        $sending = SmsMessage::factory()->sending()->create();

        $this->runSendJob($sent->id, $barangay)->assertNotReleased();
        $this->runSendJob($sending->id, $barangay)->assertNotReleased();
        $this->runSendJob(999999, $barangay)->assertNotReleased();

        $this->assertSame([], $gateway->sent);
        $this->assertSame(SmsStatus::Sent, $sent->refresh()->status);
        $this->assertSame(1, $sent->attempts);
    }

    public function test_the_rate_limiter_releases_the_job_without_using_an_attempt(): void
    {
        [$user, $barangay] = $this->smsDesk();
        $this->configureSms(['rate_per_minute' => 1]);
        $gateway = $this->fakeGateway();
        Queue::fake();
        $this->queue($user, 'Rate limited.', '+639170000001', '+639170000002');
        [$first, $second] = SmsMessage::query()->orderBy('id')->get()->all();

        $this->runSendJob($first->id, $barangay)->assertNotReleased();
        $job = $this->runSendJob($second->id, $barangay);

        $job->assertReleased();
        $this->assertCount(1, $gateway->sent);
        $this->assertSame(SmsStatus::Queued, $second->refresh()->status);
        $this->assertSame(0, $second->attempts);

        [, $other] = $this->smsDesk();
        $this->inBarangay($other);
        $elsewhere = SmsMessage::factory()->create();
        $this->runSendJob($elsewhere->id, $other)->assertNotReleased();
        $this->assertCount(2, $gateway->sent);
    }

    public function test_a_message_whose_barangay_turned_sms_off_fails_and_can_be_retried_later(): void
    {
        [$user, $barangay] = $this->smsDesk();
        $gateway = $this->fakeGateway();
        Queue::fake();
        $this->queue($user);
        $message = SmsMessage::query()->sole();

        $this->turnOff(FeatureFlag::Sms, $barangay);
        $this->runSendJob($message->id, $barangay);

        $this->assertSame([], $gateway->sent);
        $this->assertSame(SmsStatus::Failed, $message->refresh()->status);
        $this->assertSame(SmsErrorCodes::FEATURE_DISABLED, $message->last_error_code);
    }

    public function test_stuck_messages_are_requeued_up_to_the_attempt_cap(): void
    {
        [, $barangay] = $this->smsDesk();
        $this->configureSms(['stuck_after_minutes' => 15, 'max_attempts' => 3]);
        Queue::fake();
        $stuck = SmsMessage::factory()->sending()->create(['sending_started_at' => now()->subMinutes(20), 'attempts' => 1]);
        $exhausted = SmsMessage::factory()->sending()->create(['sending_started_at' => now()->subMinutes(20), 'attempts' => 3]);
        $recent = SmsMessage::factory()->sending()->create(['sending_started_at' => now()->subMinutes(5)]);

        $this->artisan('bms:sms:dispatch-stuck')->expectsOutputToContain('Requeued 1 stuck message(s); marked 1 as failed.')->assertSuccessful();

        $this->assertSame(SmsStatus::Queued, $stuck->refresh()->status);
        $this->assertSame(SmsErrorCodes::STUCK, $stuck->last_error_code);
        $this->assertSame(SmsStatus::Failed, $exhausted->refresh()->status);
        $this->assertSame(SmsStatus::Sending, $recent->refresh()->status);
        Queue::assertPushed(SendSmsMessage::class, fn (SendSmsMessage $job): bool => $job->messageId === $stuck->id && $job->barangayId === $barangay->id);
        Queue::assertPushed(SendSmsMessage::class, 1);
    }

    public function test_a_job_that_gives_up_marks_the_message_failed(): void
    {
        [, $barangay] = $this->smsDesk();
        $message = SmsMessage::factory()->create();

        (new SendSmsMessage($message->id, $barangay->id))->failed(new RuntimeException('worker died'));

        $this->assertSame(SmsStatus::Failed, $message->refresh()->status);
        $this->assertSame(SmsErrorCodes::JOB_FAILED, $message->last_error_code);
    }

    public function test_the_null_driver_rejects_every_message(): void
    {
        [$user] = $this->smsDesk();
        $this->configureSms(['driver' => 'null']);

        $this->queue($user);

        $message = SmsMessage::query()->sole();
        $this->assertSame(SmsStatus::Failed, $message->status);
        $this->assertSame(SmsErrorCodes::NOT_CONFIGURED, $message->last_error_code);
    }

    public function test_barangays_are_never_mixed_up_by_the_job(): void
    {
        [$user, $barangay] = $this->smsDesk();
        $this->fakeGateway();
        Queue::fake();
        $this->queue($user);
        $message = SmsMessage::query()->sole();
        $other = Barangay::factory()->create();

        $this->runSendJob($message->id, $other);

        $this->inBarangay($barangay);
        $this->assertSame(SmsStatus::Queued, $message->refresh()->status);
    }
}
