<?php

namespace Tests\Feature\Sms;

use App\Enums\AuditAction;
use App\Enums\SmsStatus;
use App\Models\AuditEvent;
use App\Models\Resident;
use App\Models\SmsGroup;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSms;
use Tests\TestCase;

class SmsPrivacyTest extends TestCase
{
    use CreatesSms, RefreshDatabase;

    private const string BODY = 'Your laboratory result is ready for pickup.';

    public function test_audit_rows_and_logs_carry_counts_and_masked_numbers_only(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $this->captureLogs();
        $resident = Resident::factory()->create(['mobile_number' => '09170000001']);
        $group = SmsGroup::factory()->create();

        $this->actingAs($secretary)->post(route('sms.store'), ['resident_ids' => [$resident->id], 'numbers' => '0917 000 0002', 'body' => self::BODY])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('sms.groups.members.store', $group), ['mobile_number' => '0917 000 0003'])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $queued = SmsMessage::factory()->create(['recipient' => '+639170000004', 'body' => self::BODY]);
        $failed = SmsMessage::factory()->failed()->create(['recipient' => '+639170000005', 'body' => self::BODY]);
        $this->actingAs($secretary)->post(route('sms.cancel'), ['ids' => [$queued->id]])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('sms.retry'), ['ids' => [$failed->id]])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(3, SmsMessage::query()->where('status', SmsStatus::Sent->value)->whereNotNull('provider_message_id')->where('provider_message_id', 'like', 'log-%')->count());
        $audit = (string) json_encode(AuditEvent::query()->get(['action', 'changes'])->toArray());
        $logs = $this->loggedText();

        foreach ([$audit, $logs] as $text) {
            $this->assertStringNotContainsString('laboratory', $text);
            foreach (['0001', '0002', '0003', '0004', '0005'] as $suffix) {
                $this->assertStringNotContainsString('917000'.$suffix, $text);
            }
        }
        $this->assertStringContainsString('***0001', $logs);
        $this->assertStringContainsString('***0003', $audit);
        $this->assertStringContainsString('***0004', $audit);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::SmsQueued)->count());
    }

    public function test_retention_prune_erases_bodies_and_numbers_but_keeps_counts_and_statuses(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $resident = Resident::factory()->create();
        $old = now()->subDays(40);
        $sent = SmsMessage::factory()->sent()->create(['resident_id' => $resident->id, 'queued_at' => $old, 'sent_at' => $old]);
        $failed = SmsMessage::factory()->failed()->create(['queued_at' => $old, 'failed_at' => $old]);
        $cancelled = SmsMessage::factory()->cancelled()->create(['queued_at' => $old, 'cancelled_at' => $old]);
        $stillQueued = SmsMessage::factory()->create(['queued_at' => $old]);
        $recent = SmsMessage::factory()->sent()->create(['queued_at' => now()->subDays(10), 'sent_at' => now()->subDays(10)]);
        [, $other] = $this->smsDesk();
        $this->inBarangay($other);
        $elsewhere = SmsMessage::factory()->sent()->create(['queued_at' => $old, 'sent_at' => $old]);

        $this->artisan('bms:sms:prune')->expectsOutputToContain('history is kept')->assertSuccessful();
        $this->assertNotNull($elsewhere->refresh()->body);

        $this->configureSms(['retention_days' => 30]);
        $this->artisan('bms:sms:prune')->expectsOutputToContain('Pruned 4 message(s) in 2 barangay(s).')->assertSuccessful();

        $this->inBarangay($barangay);
        foreach ([$sent, $failed, $cancelled] as $message) {
            $status = $message->status;
            $message->refresh();
            $this->assertNull($message->body);
            $this->assertNull($message->recipient);
            $this->assertNull($message->resident_id);
            $this->assertNotNull($message->pruned_at);
            $this->assertSame($status, $message->status);
            $this->assertSame(1, $message->segments);
        }
        $this->assertNotNull($stillQueued->refresh()->body);
        $this->assertNotNull($recent->refresh()->recipient);
        $this->assertSame(5, SmsMessage::query()->count());
        $audit = AuditEvent::query()->where('action', AuditAction::SmsPruned)->where('barangay_id', $barangay->id)->sole();
        $this->assertSame(3, $audit->changes['after']['messages']);

        $this->artisan('bms:sms:prune')->expectsOutputToContain('Pruned 0 message(s)')->assertSuccessful();

        $this->actingAs($secretary)->get(route('sms.show', $sent))->assertOk();
    }
}
