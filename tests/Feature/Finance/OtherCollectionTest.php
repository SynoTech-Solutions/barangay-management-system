<?php

namespace Tests\Feature\Finance;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\BarangayClearance;
use App\Models\CollectionType;
use App\Models\IdCard;
use App\Models\OtherCollection;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class OtherCollectionTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(CollectionType $type, array $overrides = []): array
    {
        return [
            'collection_type_id' => $type->id,
            'payer_name' => 'Walk-in  Payer',
            'or_number' => 'OR-100',
            'amount' => '250.75',
            'paid_on' => now()->toDateString(),
            ...$overrides,
        ];
    }

    public function test_collections_are_numbered_snapshot_the_payer_and_keep_the_original_recorder(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $clerk = $this->barangayUser(SystemRole::Clerk, $barangay);
        $type = CollectionType::factory()->create();
        $resident = Resident::factory()->create();

        $this->actingAs($clerk)->post(route('collections.store'), $this->payload($type, ['payer_name' => null, 'resident_id' => $resident->id]))
            ->assertSessionHasNoErrors()->assertRedirect(route('collections.index'));

        $this->inBarangay($barangay);
        $collection = OtherCollection::query()->sole();
        $this->assertSame('OC-'.now()->year.'-000001', $collection->collection_number);
        $this->assertSame($resident->full_name, $collection->payer_name);
        $this->assertSame('250.75', $collection->amount);
        $this->assertSame($clerk->id, $collection->recorded_by);
        $recordedAt = $collection->recorded_at->toIso8601String();

        $this->actingAs($treasurer)->put(route('collections.update', $collection), $this->payload($type, ['amount' => '300.00', 'paid_on' => now()->subDay()->toDateString()]))
            ->assertSessionHasNoErrors();
        $collection->refresh();
        $this->assertSame('Walk-in Payer', $collection->payer_name);
        $this->assertNull($collection->resident_id);
        $this->assertSame($clerk->id, $collection->recorded_by);
        $this->assertSame($recordedAt, $collection->recorded_at->toIso8601String());
        $this->assertSame($treasurer->id, $collection->updated_by);

        $updated = AuditEvent::query()->where('action', AuditAction::CollectionUpdated)->sole();
        $this->assertSame('300.00', $updated->changes['after']['amount']);
        $this->assertArrayNotHasKey('or_number', $updated->changes['after']);
    }

    public function test_validation_requires_a_type_a_payer_an_or_number_and_a_positive_amount(): void
    {
        [$treasurer] = $this->financeDesk();
        $type = CollectionType::factory()->create();
        $hidden = CollectionType::factory()->create(['is_active' => false]);

        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['amount' => '0']))->assertSessionHasErrors('amount');
        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['amount' => '1.005']))->assertSessionHasErrors('amount');
        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['or_number' => '']))->assertSessionHasErrors('or_number');
        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['payer_name' => '']))->assertSessionHasErrors('payer_name');
        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($hidden))->assertSessionHasErrors('collection_type_id');
        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['paid_on' => now()->addDay()->toDateString()]))->assertSessionHasErrors('paid_on');

        $this->assertSame(0, OtherCollection::query()->count());
    }

    public function test_or_numbers_are_unique_across_clearances_id_cards_and_collections_until_cancelled(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        [$secretary] = $this->residentDesk(SystemRole::Secretary, $barangay);
        $type = CollectionType::factory()->create();
        $resident = $this->photographedResident();
        BarangayClearance::factory()->create(['resident_id' => $resident->id, 'or_number' => 'OR-7']);
        IdCard::factory()->create(['resident_id' => $resident->id, 'or_number' => 'OR-8']);

        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['or_number' => 'OR-7']))->assertSessionHasErrors('or_number');
        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['or_number' => 'OR-8']))->assertSessionHasErrors('or_number');
        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['or_number' => 'OR-9']))->assertSessionHasNoErrors();

        $this->actingAs($secretary)->post(route('clearances.store'), [
            'resident_id' => $resident->id,
            'clearance_type_id' => $this->lookup(LookupType::ClearanceType)->id,
            'purpose_id' => $this->lookup(LookupType::ClearancePurpose)->id,
            'or_number' => 'OR-9',
        ])->assertSessionHasErrors('or_number');

        $this->inBarangay($barangay);
        $collection = OtherCollection::query()->sole();
        $this->actingAs($treasurer)->post(route('collections.cancel', $collection), ['reason' => 'Wrong payer'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('collections.store'), $this->payload($type, ['or_number' => 'OR-9']))->assertSessionHasNoErrors();
    }

    public function test_cancelled_collections_are_locked_and_hidden_from_the_default_list(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $type = CollectionType::factory()->create();
        $active = OtherCollection::factory()->create(['collection_type_id' => $type->id, 'amount' => '40.00']);
        $cancelled = OtherCollection::factory()->cancelled()->create(['collection_type_id' => $type->id, 'amount' => '60.00']);

        $this->actingAs($treasurer)->putJson(route('collections.update', $cancelled), $this->payload($type))
            ->assertUnprocessable()->assertJsonPath('error.code', 'collection_cancelled');
        $this->actingAs($treasurer)->postJson(route('collections.cancel', $cancelled), ['reason' => 'Again'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'collection_cancelled');
        $this->actingAs($treasurer)->post(route('collections.cancel', $active), [])->assertSessionHasErrors('reason');

        $this->actingAs($treasurer)->get(route('collections.index'))->assertInertia(fn (Assert $page) => $page
            ->component('collections/Index')
            ->has('collections.data', 1)
            ->where('total', '40.00'));
        $this->actingAs($treasurer)->get(route('collections.index', ['include_cancelled' => 1]))->assertInertia(fn (Assert $page) => $page
            ->has('collections.data', 2)
            ->where('total', '40.00'));
        $this->actingAs($treasurer)->get(route('collections.print'))->assertInertia(fn (Assert $page) => $page
            ->component('collections/ListPrint')
            ->where('count', 1));
        $this->assertSame($barangay->id, $active->barangay_id);
    }

    public function test_collection_types_are_managed_by_the_treasurer_and_hidden_instead_of_deleted(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $clerk = $this->barangayUser(SystemRole::Clerk, $barangay);

        $this->actingAs($treasurer)->post(route('collections.types.store'), ['name' => 'Facility  Rental'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('collections.types.store'), ['name' => 'facility rental'])->assertSessionHasErrors('name');
        $type = CollectionType::query()->sole();
        $this->assertSame('Facility Rental', $type->name);
        $this->actingAs($treasurer)->post(route('collections.types.deactivate', $type))->assertSessionHasNoErrors();
        $this->assertFalse($type->refresh()->is_active);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::CollectionTypeSaved)->exists());

        $this->actingAs($clerk)->get(route('collections.types.index'))->assertForbidden();
        $this->actingAs($clerk)->post(route('collections.cancel', OtherCollection::factory()->create()), ['reason' => 'x'])->assertForbidden();
    }

    public function test_the_module_follows_its_flag_and_tenancy(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $this->turnOff(FeatureFlag::OtherCollections, $barangay);
        $this->actingAs($treasurer)->get(route('collections.index'))->assertNotFound();
        $this->actingAs($treasurer)->get(route('collections.types.index'))->assertNotFound();

        [$other] = $this->financeDesk();
        $foreign = OtherCollection::factory()->create();
        $this->actingAs($other)->get(route('collections.edit', $foreign))->assertOk();
        [$neighbor] = $this->financeDesk();
        $this->actingAs($neighbor)->get(route('collections.edit', $foreign))->assertNotFound();
        $this->actingAs($neighbor)->post(route('collections.cancel', $foreign), ['reason' => 'x'])->assertNotFound();
    }
}
