<?php

namespace Tests\Feature\Residents;

use App\Enums\AuditAction;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Purok;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ResidentListTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_list_filters_by_purok_flags_and_sector_and_hides_removed_and_incomplete(): void
    {
        [$viewer, , $purok] = $this->residentDesk(SystemRole::Viewer);
        $otherPurok = Purok::factory()->create();
        $sector = $this->lookup(LookupType::Sector);
        $senior = Resident::factory()->create(['purok_id' => $purok->id, 'birthdate' => CarbonImmutable::today()->subYears(70)->toDateString(), 'is_pwd' => true]);
        $senior->sectors()->attach($sector->id, ['barangay_id' => $senior->barangay_id]);
        Resident::factory()->create(['purok_id' => $purok->id, 'birthdate' => CarbonImmutable::today()->subYears(20)->toDateString()]);
        Resident::factory()->create(['purok_id' => $otherPurok->id]);
        Resident::factory()->create(['purok_id' => $purok->id, 'is_incomplete' => true]);
        Resident::factory()->create(['purok_id' => $purok->id])->delete();

        $this->actingAs($viewer)
            ->get(route('residents.index', ['purok_id' => $purok->id]))
            ->assertInertia(fn (Assert $page) => $page->component('residents/Index')->where('residents.meta.total', 2));

        $this->actingAs($viewer)
            ->get(route('residents.index', ['flags' => ['senior', 'pwd']]))
            ->assertInertia(fn (Assert $page) => $page->where('residents.meta.total', 1)->where('residents.data.0.id', $senior->id)->where('residents.data.0.is_senior', true));

        $this->actingAs($viewer)
            ->get(route('residents.index', ['sector_id' => $sector->id]))
            ->assertInertia(fn (Assert $page) => $page->where('residents.meta.total', 1));

        $this->actingAs($viewer)
            ->get(route('residents.index', ['include_incomplete' => 1]))
            ->assertInertia(fn (Assert $page) => $page->where('residents.meta.total', 4));

        $this->actingAs($viewer)
            ->get(route('residents.index', ['removed' => 1]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.removed', false)->where('residents.meta.total', 3));
    }

    public function test_csv_export_streams_a_header_row_and_is_audited(): void
    {
        [$secretary, $barangay, $purok] = $this->residentDesk();
        Resident::factory()->named('Dela Cruz', 'Juan')->create(['purok_id' => $purok->id, 'street_address' => '=HYPERLINK("x")']);

        $response = $this->actingAs($secretary)->get(route('residents.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $lines = preg_split('/\r?\n/', trim(ltrim($response->streamedContent(), "\u{FEFF}"))) ?: [];
        $this->assertStringStartsWith('"Resident number","Last name","First name"', $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('Dela Cruz', $lines[1]);
        $this->assertStringContainsString("'=HYPERLINK", $lines[1]);

        $this->inBarangay($barangay);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::Exported)->where('actor_id', $secretary->id)->exists());
    }

    public function test_bulk_purok_change_writes_one_audit_row_per_changed_resident(): void
    {
        [$secretary, $barangay, $purok] = $this->residentDesk();
        $target = Purok::factory()->create();
        $moving = Resident::factory()->count(2)->create(['purok_id' => $purok->id]);
        $already = Resident::factory()->create(['purok_id' => $target->id]);

        $this->actingAs($secretary)
            ->post(route('residents.bulk.purok'), [
                'resident_ids' => [...$moving->pluck('id')->all(), $already->id],
                'purok_id' => $target->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->inBarangay($barangay);
        $this->assertSame(3, Resident::query()->where('purok_id', $target->id)->count());
        $audits = AuditEvent::query()->where('action', AuditAction::ResidentBulkPurokChanged)->get();
        $this->assertCount(2, $audits);
        $this->assertEqualsCanonicalizing($moving->pluck('id')->map(fn ($id) => (string) $id)->all(), $audits->pluck('subject_id')->all());
        $this->assertSame($purok->id, $audits->first()?->changes['before']['purok_id'] ?? null);
    }
}
