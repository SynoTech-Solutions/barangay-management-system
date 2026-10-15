<?php

namespace Tests\Feature\Sms;

use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\SmsSource;
use App\Exceptions\BmsException;
use App\Models\AuditEvent;
use App\Models\Resident;
use App\Models\SmsBatch;
use App\Models\SmsGroup;
use App\Models\SmsGroupMember;
use App\Models\SmsMessage;
use App\Services\Sms\SmsGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSms;
use Tests\TestCase;

class SmsGroupTest extends TestCase
{
    use CreatesSms, RefreshDatabase;

    public function test_group_send_uses_current_numbers_and_skips_unreachable_members(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $gateway = $this->fakeGateway();
        $group = SmsGroup::factory()->create();
        $reachable = Resident::factory()->create(['mobile_number' => '09170000001']);
        $deceased = Resident::factory()->create(['mobile_number' => '09170000002']);
        $removed = Resident::factory()->create(['mobile_number' => '09170000003']);
        $noNumber = Resident::factory()->create(['mobile_number' => '09170000004']);
        foreach ([$reachable, $deceased, $removed, $noNumber] as $resident) {
            SmsGroupMember::factory()->forResident($resident)->create(['sms_group_id' => $group->id]);
        }
        SmsGroupMember::factory()->create(['sms_group_id' => $group->id, 'mobile_number' => '+639170000009']);

        $reachable->update(['mobile_number' => '09170000008']);
        $deceased->update(['is_deceased' => true]);
        $removed->delete();
        $noNumber->update(['mobile_number' => null]);

        $this->actingAs($secretary)->get(route('sms.groups.message.create', $group))->assertInertia(fn (Assert $page) => $page
            ->component('sms/groups/Message')
            ->where('summary.recipients', 2)
            ->where('summary.skipped', 3));

        $this->actingAs($secretary)->post(route('sms.groups.message.store', $group), ['body' => 'Senior citizen payout on Monday.'])
            ->assertSessionHasNoErrors()->assertRedirect(route('sms.index'));

        $this->inBarangay($barangay);
        $this->assertSame(['+639170000008', '+639170000009'], SmsMessage::query()->orderBy('id')->pluck('recipient')->all());
        $batch = SmsBatch::query()->sole();
        $this->assertSame(SmsSource::Group, $batch->source);
        $this->assertSame($group->id, $batch->sms_group_id);
        $this->assertSame(3, $batch->skipped_count);
        $this->assertCount(2, $gateway->sent);
    }

    public function test_group_names_are_unique_per_barangay_ignoring_case(): void
    {
        [$secretary, $barangay] = $this->smsDesk();

        $this->actingAs($secretary)->post(route('sms.groups.store'), ['name' => '  Senior   citizens '])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('sms.groups.store'), ['name' => 'SENIOR CITIZENS'])->assertSessionHasErrors('name');

        $this->inBarangay($barangay);
        $group = SmsGroup::query()->sole();
        $this->assertSame('Senior citizens', $group->name);

        try {
            app(SmsGroupService::class)->create('senior citizens', $secretary);
            $this->fail('Expected a duplicate name error');
        } catch (BmsException $exception) {
            $this->assertSame(ErrorCode::SmsGroupNameTaken, $exception->errorCode);
        }

        [$otherSecretary, $other] = $this->smsDesk();
        $this->actingAs($otherSecretary)->post(route('sms.groups.store'), ['name' => 'Senior citizens'])->assertSessionHasNoErrors();
        $this->inBarangay($other);
        $this->assertSame(1, SmsGroup::query()->count());
    }

    public function test_groups_are_renamed_and_deactivated_instead_of_deleted(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $group = SmsGroup::factory()->create(['name' => 'Purok leaders']);
        SmsGroupMember::factory()->create(['sms_group_id' => $group->id]);
        $taken = SmsGroup::factory()->create(['name' => 'Tanods']);

        $this->actingAs($secretary)->put(route('sms.groups.update', $group), ['name' => 'tanods'])->assertSessionHasErrors('name');
        $this->actingAs($secretary)->put(route('sms.groups.update', $group), ['name' => 'Purok coordinators'])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('sms.groups.deactivate', $group))->assertSessionHasNoErrors();
        $this->actingAs($secretary)->postJson(route('sms.groups.message.store', $group), ['body' => 'Hi'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_group_inactive');
        $this->actingAs($secretary)->postJson(route('sms.groups.members.store', $group), ['mobile_number' => '09170000005'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_group_inactive');

        $this->inBarangay($barangay);
        $group->refresh();
        $this->assertSame('Purok coordinators', $group->name);
        $this->assertFalse($group->is_active);
        $this->assertSame(1, $group->members()->count());
        $this->assertSame('Tanods', $taken->refresh()->name);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::SmsGroupRenamed)->exists());
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::SmsGroupDeactivated)->exists());

        $this->actingAs($secretary)->get(route('sms.groups.index'))->assertInertia(fn (Assert $page) => $page
            ->component('sms/groups/Index')
            ->has('groups', 2)
            ->where('groups.1.is_active', false)
            ->where('groups.1.members_count', 1));
    }

    public function test_members_are_residents_or_normalized_typed_numbers(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $group = SmsGroup::factory()->create();
        $resident = Resident::factory()->create(['mobile_number' => '09170000001']);
        $withoutNumber = Resident::factory()->create(['mobile_number' => null]);

        $this->actingAs($secretary)->post(route('sms.groups.members.store', $group), ['resident_id' => $resident->id])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('sms.groups.members.store', $group), ['resident_id' => $resident->id])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('sms.groups.members.store', $group), ['resident_id' => $withoutNumber->id])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('sms.groups.members.store', $group), ['mobile_number' => '0917 000 0002', 'label' => 'Volunteer'])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->postJson(route('sms.groups.members.store', $group), ['mobile_number' => '+63 917 000 0002'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'sms_group_member_taken');
        $this->actingAs($secretary)->post(route('sms.groups.members.store', $group), ['mobile_number' => '02 8123 4567'])->assertSessionHasErrors('mobile_number');

        $this->inBarangay($barangay);
        $members = SmsGroupMember::query()->orderBy('id')->get();
        $this->assertCount(2, $members);
        $this->assertSame($resident->id, $members[0]->resident_id);
        $this->assertNull($members[0]->mobile_number);
        $this->assertSame('+639170000002', $members[1]->mobile_number);

        $this->actingAs($secretary)->get(route('sms.groups.show', $group))->assertInertia(fn (Assert $page) => $page
            ->component('sms/groups/Show')
            ->has('members', 2)
            ->where('members.0.number', '+639170000001')
            ->where('members.1.label', 'Volunteer'));

        $this->actingAs($secretary)->delete(route('sms.groups.members.destroy', ['group' => $group->id, 'member' => $members[1]->id]))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame(1, SmsGroupMember::query()->count());

        $otherGroup = SmsGroup::factory()->create();
        $this->actingAs($secretary)->delete(route('sms.groups.members.destroy', ['group' => $otherGroup->id, 'member' => $members[0]->id]))->assertNotFound();
    }

    public function test_group_screens_are_hidden_when_the_groups_flag_is_off(): void
    {
        [$secretary, $barangay] = $this->smsDesk();
        $group = SmsGroup::factory()->create();
        $this->turnOff(FeatureFlag::SmsGroups, $barangay);

        $this->actingAs($secretary)->get(route('sms.groups.index'))->assertNotFound();
        $this->actingAs($secretary)->get(route('sms.groups.message.create', $group))->assertNotFound();
        $this->actingAs($secretary)->get(route('sms.residents.group.create', ['scope' => 'filtered']))->assertNotFound();
    }
}
