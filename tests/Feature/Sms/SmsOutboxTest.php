<?php

namespace Tests\Feature\Sms;

use App\Enums\AuditAction;
use App\Enums\SmsSource;
use App\Enums\SmsStatus;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Resident;
use App\Models\SmsBatch;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSms;
use Tests\TestCase;

class SmsOutboxTest extends TestCase
{
    use CreatesSms, RefreshDatabase;

    public function test_compose_merges_picked_residents_and_typed_numbers_without_duplicates(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $gateway = $this->fakeGateway();
        $juan = Resident::factory()->create(['mobile_number' => '09170000001']);
        $maria = Resident::factory()->create(['mobile_number' => '09170000002']);
        $deceased = Resident::factory()->deceased()->create(['mobile_number' => '09170000003']);
        $noNumber = Resident::factory()->create(['mobile_number' => null]);

        $this->actingAs($secretary)->post(route('sms.store'), [
            'resident_ids' => [$juan->id, $maria->id, $deceased->id, $noNumber->id],
            'numbers' => "+63 917 000 0001,\n0917-000-0009; 0917 000 0009",
            'body' => "  Barangay assembly\r\non Saturday.\u{0007} ",
        ])->assertSessionHasNoErrors()->assertRedirect(route('sms.index'));

        $this->inBarangay($barangay);
        $messages = SmsMessage::query()->orderBy('id')->get();
        $this->assertSame(['+639170000001', '+639170000002', '+639170000009'], $messages->pluck('recipient')->all());
        $this->assertSame([$juan->id, $maria->id, null], $messages->pluck('resident_id')->all());
        $this->assertSame("Barangay assembly\non Saturday.", $messages->first()?->body);
        $this->assertCount(3, $gateway->sent);
        $batch = SmsBatch::query()->sole();
        $this->assertSame(SmsSource::Compose, $batch->source);
        $this->assertSame(3, $batch->recipient_count);
        $this->assertSame(2, $batch->skipped_count);
        $audit = AuditEvent::query()->where('action', AuditAction::SmsQueued)->sole();
        $this->assertEquals(['matched' => 7, 'recipients' => 3, 'skipped' => 2, 'duplicates' => 2], array_intersect_key($audit->changes['after'], array_flip(['matched', 'recipients', 'skipped', 'duplicates'])));
    }

    public function test_compose_validates_numbers_body_length_and_needs_a_reachable_recipient(): void
    {
        [$secretary] = $this->smsDesk();
        Queue::fake();

        $this->actingAs($secretary)->post(route('sms.store'), ['numbers' => '0917 000 0001, 02 8123 4567', 'body' => 'Hi'])
            ->assertSessionHasErrors('numbers.1');
        $this->actingAs($secretary)->post(route('sms.store'), ['numbers' => '0917 000 0001', 'body' => str_repeat('a', 766)])
            ->assertSessionHasErrors('body');
        $this->actingAs($secretary)->post(route('sms.store'), ['numbers' => '0917 000 0001', 'body' => " \u{0007} "])
            ->assertSessionHasErrors('body');
        $deceased = Resident::factory()->deceased()->create(['mobile_number' => '09170000003']);
        $this->actingAs($secretary)->postJson(route('sms.store'), ['resident_ids' => [$deceased->id], 'body' => 'Hi'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_no_recipients');

        $this->assertSame(0, SmsMessage::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_compose_respects_the_recipient_cap(): void
    {
        [$secretary] = $this->smsDesk();
        $this->configureSms(['max_recipients' => 2]);
        Queue::fake();

        $this->actingAs($secretary)->postJson(route('sms.store'), ['numbers' => '09170000001, 09170000002, 09170000003', 'body' => 'Hi'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_too_many_recipients');

        $this->assertSame(0, SmsMessage::query()->count());
    }

    public function test_the_outbox_lists_filters_and_shows_messages(): void
    {
        [$clerk, $barangay] = $this->smsDesk(SystemRole::Clerk);
        $juan = Resident::factory()->named('Dela Cruz', 'Juan')->create(['mobile_number' => '09170000001']);
        SmsMessage::factory()->sent()->create(['resident_id' => $juan->id, 'recipient' => '+639170000001', 'body' => 'Assembly reminder', 'queued_at' => now()->subDays(3)]);
        SmsMessage::factory()->failed()->create(['body' => 'Water interruption', 'queued_at' => now()]);
        $queued = SmsMessage::factory()->create(['recipient' => '+639170000005', 'body' => 'Vaccination schedule', 'queued_at' => now()]);

        $this->actingAs($clerk)->get(route('sms.index'))->assertInertia(fn (Assert $page) => $page
            ->component('sms/Index')
            ->has('messages.data', 3)
            ->where('messages.data.0.can_cancel', true));
        $this->actingAs($clerk)->get(route('sms.index', ['status' => 'failed']))->assertInertia(fn (Assert $page) => $page
            ->has('messages.data', 1)
            ->where('messages.data.0.body', 'Water interruption'));
        $this->actingAs($clerk)->get(route('sms.index', ['search' => 'juan']))->assertInertia(fn (Assert $page) => $page
            ->has('messages.data', 1)
            ->where('messages.data.0.resident.full_name', $juan->full_name));
        $this->actingAs($clerk)->get(route('sms.index', ['search' => '0917 000 0005']))->assertInertia(fn (Assert $page) => $page
            ->has('messages.data', 1)
            ->where('messages.data.0.id', $queued->id));
        $this->actingAs($clerk)->get(route('sms.index', ['from' => now()->subDays(4)->toDateString(), 'to' => now()->subDays(2)->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1)->where('messages.data.0.body', 'Assembly reminder'));
        $this->actingAs($clerk)->get(route('sms.show', $queued))->assertInertia(fn (Assert $page) => $page
            ->component('sms/Show')
            ->where('message.status', 'queued')
            ->where('message.recipient', '+639170000005'));
        $this->assertSame(3, SmsMessage::query()->where('barangay_id', $barangay->id)->count());
    }

    public function test_cancel_is_all_or_nothing_and_only_for_queued_messages(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $queued = SmsMessage::factory()->count(2)->create();
        $sent = SmsMessage::factory()->sent()->create();

        $this->actingAs($secretary)->postJson(route('sms.cancel'), ['ids' => [$queued[0]->id, $sent->id]])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_messages_not_queued')
            ->assertJsonPath('error.message', fn (string $text): bool => str_contains($text, '#'.$sent->id));
        $this->inBarangay($barangay);
        $this->assertSame(SmsStatus::Queued, $queued[0]->refresh()->status);

        $this->actingAs($secretary)->post(route('sms.cancel'), ['ids' => $queued->pluck('id')->all()])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame(2, SmsMessage::query()->where('status', SmsStatus::Cancelled->value)->count());
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::SmsCancelled)->count());
        $this->assertSame(SmsStatus::Sent, $sent->refresh()->status);
    }

    public function test_retry_is_only_for_failed_messages_and_sends_again(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $gateway = $this->fakeGateway();
        $failed = SmsMessage::factory()->failed()->create(['attempts' => 5]);
        $sent = SmsMessage::factory()->sent()->create();
        $pruned = SmsMessage::factory()->failed()->create(['body' => null, 'recipient' => null, 'pruned_at' => now()]);

        $this->actingAs($secretary)->postJson(route('sms.retry'), ['ids' => [$failed->id, $sent->id]])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_messages_not_failed');
        $this->actingAs($secretary)->postJson(route('sms.retry'), ['ids' => [$pruned->id]])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_messages_not_failed');
        $this->assertSame([], $gateway->sent);

        $this->actingAs($secretary)->post(route('sms.retry'), ['ids' => [$failed->id]])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(SmsStatus::Sent, $failed->refresh()->status);
        $this->assertSame(1, $failed->attempts);
        $this->assertNull($failed->last_error_code);
        $this->assertCount(1, $gateway->sent);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::SmsRetried)->exists());
    }

    public function test_compose_prefills_a_resident_or_a_reply_number(): void
    {
        [$secretary] = $this->smsDesk();
        $juan = Resident::factory()->create(['mobile_number' => '09170000001']);
        $typed = SmsMessage::factory()->sent()->create(['recipient' => '+639170000007']);

        $this->actingAs($secretary)->get(route('sms.create', ['resident_id' => $juan->id]))->assertInertia(fn (Assert $page) => $page
            ->component('sms/Compose')
            ->where('prefill.resident.id', $juan->id)
            ->where('limits.max_segments', 5));
        $this->actingAs($secretary)->get(route('sms.create', ['reply_to' => $typed->id]))->assertInertia(fn (Assert $page) => $page
            ->where('prefill.numbers', ['09170000007']));
    }

    public function test_the_picker_can_limit_results_to_residents_with_a_mobile_number(): void
    {
        [$secretary] = $this->smsDesk();
        Resident::factory()->named('Santos', 'Ana')->create(['mobile_number' => '09170000001']);
        Resident::factory()->named('Santos', 'Ben')->create(['mobile_number' => null]);

        $this->actingAs($secretary)->getJson(route('residents.search', ['q' => 'Santos', 'with_mobile' => 1]))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($secretary)->getJson(route('residents.search', ['q' => 'Santos']))
            ->assertOk()->assertJsonCount(2, 'data');
    }
}
