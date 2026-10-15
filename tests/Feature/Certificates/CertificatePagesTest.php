<?php

namespace Tests\Feature\Certificates;

use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\BarangayClearance;
use App\Models\Blotter;
use App\Models\Business;
use App\Models\BusinessClearance;
use App\Models\IndigencyCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class CertificatePagesTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_every_certificate_screen_renders(): void
    {
        [$secretary, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $this->lookup(LookupType::ClearanceType);
        $resident = $this->photographedResident();
        $clearance = BarangayClearance::factory()->create(['resident_id' => $resident->id]);
        $business = Business::factory()->create();
        $businessClearance = BusinessClearance::factory()->create(['business_id' => $business->id]);
        $blotter = Blotter::factory()->against($resident)->create();
        $indigency = IndigencyCertificate::factory()->create(['resident_id' => $resident->id]);
        $this->turnOn(FeatureFlag::IndigencyCertificate, $barangay);

        $pages = [
            [route('clearances.create', ['resident_id' => $resident->id]), 'clearances/Form'],
            [route('clearances.edit', $clearance), 'clearances/Form'],
            [route('businesses.create'), 'businesses/Form'],
            [route('businesses.edit', $business), 'businesses/Form'],
            [route('businesses.show', $business), 'businesses/Show'],
            [route('business-clearances.index'), 'business-clearances/Index'],
            [route('business-clearances.create'), 'business-clearances/Form'],
            [route('business-clearances.edit', $businessClearance), 'business-clearances/Form'],
            [route('reports.businesses'), 'reports/Businesses'],
            [route('cedulas.index'), 'cedulas/Index'],
            [route('cedulas.create', ['resident_id' => $resident->id]), 'cedulas/Form'],
            [route('cedulas.templates.index'), 'cedulas/Templates'],
            [route('blotters.index'), 'blotters/Index'],
            [route('blotters.create'), 'blotters/Form'],
            [route('blotters.show', $blotter), 'blotters/Show'],
            [route('blotters.edit', $blotter), 'blotters/Form'],
            [route('indigency-certificates.index'), 'indigency/Index'],
            [route('indigency-certificates.create'), 'indigency/Form'],
            [route('indigency-certificates.edit', $indigency), 'indigency/Form'],
        ];

        foreach ($pages as [$url, $component]) {
            $this->actingAs($secretary)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
        }

        $this->actingAs($secretary)->get(route('clearances.create', ['resident_id' => $resident->id]))
            ->assertInertia(fn (Assert $page) => $page->where('resident.id', $resident->id)->has('lookups.clearance-type'));
    }
}
