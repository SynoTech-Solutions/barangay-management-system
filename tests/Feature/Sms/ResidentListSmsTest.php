<?php

namespace Tests\Feature\Sms;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\SmsSource;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\SmsBatch;
use App\Models\SmsGroup;
use App\Models\SmsGroupMember;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSms;
use Tests\TestCase;

class ResidentListSmsTest extends TestCase
{
    use CreatesSms, RefreshDatabase;

    /**
     * @return array{0: Purok, 1: Purok}
     */
    private function puroks(): array
    {
        $east = Purok::factory()->create(['name' => 'Purok East']);
        $west = Purok::factory()->create(['name' => 'Purok West']);

        foreach (['09170000001', '09170000002', '09170000003'] as $number) {
            Resident::factory()->create(['purok_id' => $east->id, 'mobile_number' => $number]);
        }
        Resident::factory()->deceased()->create(['purok_id' => $east->id, 'mobile_number' => '09170000004']);
        Resident::factory()->create(['purok_id' => $east->id, 'mobile_number' => null]);
        Resident::factory()->create(['purok_id' => $west->id, 'mobile_number' => '09170000006']);

        return [$east, $west];
    }

    public function test_send_to_everyone_matching_the_filters_needs_the_confirmed_count_and_writes_one_audit_row(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        Queue::fake();
        [$east] = $this->puroks();
        $selection = ['scope' => 'filtered', 'purok_id' => $east->id];

        $this->actingAs($secretary)->get(route('sms.residents.send.create', $selection))->assertInertia(fn (Assert $page) => $page
            ->component('sms/FromResidents')
            ->where('mode', 'send')
            ->where('summary.recipients', 3)
            ->where('summary.skipped', 2)
            ->where('summary.over_cap', false)
            ->where('selection.scope', 'filtered')
            ->where('selection.purok_id', (string) $east->id));

        $this->actingAs($secretary)->postJson(route('sms.residents.send.store'), [...$selection, 'body' => 'Purok meeting tonight.', 'confirmed_count' => 4])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_recipients_changed');
        $this->inBarangay($barangay);
        $this->assertSame(0, SmsMessage::query()->count());

        $this->actingAs($secretary)->post(route('sms.residents.send.store'), [...$selection, 'body' => 'Purok meeting tonight.', 'confirmed_count' => 3])
            ->assertSessionHasNoErrors()->assertRedirect(route('sms.index'));

        $this->inBarangay($barangay);
        $this->assertSame(['+639170000001', '+639170000002', '+639170000003'], SmsMessage::query()->orderBy('recipient')->pluck('recipient')->all());
        $this->assertSame(SmsSource::ResidentList, SmsBatch::query()->sole()->source);
        $audit = AuditEvent::query()->where('action', AuditAction::SmsQueued)->sole();
        $this->assertSame(3, $audit->changes['after']['recipients']);
        $this->assertSame(2, $audit->changes['after']['skipped']);
        $this->assertSame('resident-list', $audit->changes['after']['source']);
        Queue::assertCount(3);
    }

    public function test_the_hard_cap_blocks_large_sends(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $this->configureSms(['max_recipients' => 2]);
        Queue::fake();
        [$east] = $this->puroks();
        $selection = ['scope' => 'filtered', 'purok_id' => $east->id];

        $this->actingAs($secretary)->get(route('sms.residents.send.create', $selection))->assertInertia(fn (Assert $page) => $page
            ->where('summary.recipients', 3)
            ->where('summary.over_cap', true)
            ->where('summary.max', 2));
        $this->actingAs($secretary)->postJson(route('sms.residents.send.store'), [...$selection, 'body' => 'Hi', 'confirmed_count' => 3])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_too_many_recipients');

        $this->inBarangay($barangay);
        $this->assertSame(0, SmsMessage::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_selected_residents_ignore_filters_and_other_barangays(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        Queue::fake();
        $this->puroks();
        $picked = Resident::query()->whereNotNull('mobile_number')->where('is_deceased', false)->orderBy('id')->limit(2)->pluck('id')->all();
        $deceased = Resident::query()->where('is_deceased', true)->value('id');
        [, $other] = $this->smsDesk();
        $this->inBarangay($other);
        $outsider = Resident::factory()->create(['mobile_number' => '09170000099']);
        $this->inBarangay($barangay);

        $selection = ['scope' => 'selected', 'resident_ids' => [...$picked, $deceased, $outsider->id], 'purok_id' => 999999];
        $this->actingAs($secretary)->get(route('sms.residents.send.create', $selection))->assertInertia(fn (Assert $page) => $page
            ->where('summary.recipients', 2)
            ->where('summary.skipped', 2)
            ->where('selection', ['scope' => 'selected', 'resident_ids' => [...$picked, $deceased, $outsider->id]]));
        $this->actingAs($secretary)->post(route('sms.residents.send.store'), [...$selection, 'body' => 'Hi', 'confirmed_count' => 2])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertEqualsCanonicalizing($picked, SmsMessage::query()->pluck('resident_id')->all());
    }

    public function test_add_to_group_from_the_resident_list(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        [$east] = $this->puroks();
        $group = SmsGroup::factory()->create();
        $existing = Resident::query()->where('mobile_number', '09170000001')->sole();
        SmsGroupMember::factory()->forResident($existing)->create(['sms_group_id' => $group->id]);
        $selection = ['scope' => 'filtered', 'purok_id' => $east->id];

        $this->actingAs($secretary)->get(route('sms.residents.group.create', $selection))->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'group')
            ->where('groups.0.value', $group->id));
        $this->actingAs($secretary)->post(route('sms.residents.group.store'), [...$selection, 'sms_group_id' => $group->id, 'confirmed_count' => 3])
            ->assertSessionHasNoErrors()->assertRedirect(route('sms.groups.show', $group));

        $this->inBarangay($barangay);
        $this->assertSame(3, SmsGroupMember::query()->where('sms_group_id', $group->id)->count());
        $audit = AuditEvent::query()->where('action', AuditAction::SmsGroupMembersAdded)->sole();
        $this->assertEquals(['added' => 2, 'already' => 1, 'skipped' => 2], $audit->changes['after']);
    }

    public function test_the_resident_list_actions_need_their_flag_and_permission(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);

        $this->actingAs($viewer)->get(route('sms.residents.send.create', ['scope' => 'filtered']))->assertForbidden();
        $this->actingAs($secretary)->get(route('sms.residents.send.create', ['scope' => 'filtered']))->assertOk();

        $this->turnOff(FeatureFlag::SmsFromResidentLists, $barangay);
        $this->actingAs($secretary)->get(route('sms.residents.send.create', ['scope' => 'filtered']))->assertNotFound();
        $this->actingAs($secretary)->post(route('sms.residents.send.store'), ['scope' => 'filtered', 'body' => 'Hi', 'confirmed_count' => 1])->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertSame(0, SmsMessage::query()->count());
    }
}
