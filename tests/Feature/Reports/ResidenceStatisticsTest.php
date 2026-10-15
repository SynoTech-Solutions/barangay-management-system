<?php

namespace Tests\Feature\Reports;

use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ResidenceStatisticsTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_statistics_exclude_removed_residents_and_count_deceased_separately(): void
    {
        [$viewer, , $purok] = $this->residentDesk(SystemRole::Viewer);
        $sector = $this->lookup(LookupType::Sector, 'Farmer');
        $today = CarbonImmutable::today();

        $senior = Resident::factory()->create(['purok_id' => $purok->id, 'gender' => 'female', 'birthdate' => $today->subYears(70)->toDateString(), 'is_pwd' => true, 'is_registered_voter' => true]);
        $senior->sectors()->attach($sector->id, ['barangay_id' => $senior->barangay_id]);
        Resident::factory()->create(['purok_id' => $purok->id, 'gender' => 'male', 'birthdate' => $today->subYears(20)->toDateString()]);
        Resident::factory()->create(['gender' => 'male', 'birthdate' => null, 'is_pwd' => true, 'is_deceased' => true]);
        Resident::factory()->create(['gender' => 'male', 'is_pwd' => true])->delete();

        $this->actingAs($viewer)->get(route('reports.statistics'))->assertInertia(fn (Assert $page) => $page
            ->component('reports/Statistics')
            ->where('statistics.population', 2)
            ->where('statistics.deceased', 1)
            ->where('statistics.flags.pwd', 1)
            ->where('statistics.flags.senior', 1)
            ->where('statistics.flags.voter', 1)
            ->where('statistics.genders.0.key', 'male')
            ->where('statistics.genders.0.total', 1)
            ->where('statistics.genders.1.total', 1)
            ->where('statistics.age_bands.4.key', '18-24')
            ->where('statistics.age_bands.4.total', 1)
            ->where('statistics.age_bands.8.key', '60-79')
            ->where('statistics.age_bands.8.total', 1)
            ->where('statistics.puroks.0.total', 2)
            ->where('statistics.sectors.0.label', $sector->label)
            ->where('statistics.sectors.0.total', 1));
    }

    public function test_resident_report_filters_by_age_range_and_attribute(): void
    {
        [$viewer] = $this->residentDesk(SystemRole::Viewer);
        $religion = $this->lookup(LookupType::Religion);
        $today = CarbonImmutable::today();
        $match = Resident::factory()->create(['birthdate' => $today->subYears(30)->toDateString(), 'religion_id' => $religion->id, 'residing_since' => $today->subYears(3)->subMonths(2)->toDateString()]);
        Resident::factory()->create(['birthdate' => $today->subYears(30)->toDateString()]);
        Resident::factory()->create(['birthdate' => $today->subYears(41)->toDateString(), 'religion_id' => $religion->id]);

        $this->actingAs($viewer)
            ->get(route('reports.residents', ['age_from' => 25, 'age_to' => 40, 'attribute' => 'religion_id', 'attribute_values' => [$religion->id]]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Residents')
                ->where('residents.meta.total', 1)
                ->where('residents.data.0.id', $match->id)
                ->where('residents.data.0.length_of_stay', ['years' => 3, 'months' => 2]));

        $this->actingAs($viewer)
            ->get(route('reports.residents.print', ['age_to' => 30]))
            ->assertInertia(fn (Assert $page) => $page->component('reports/ResidentsPrint')->where('total', 2));
    }

    public function test_reports_need_the_reports_permission(): void
    {
        [$treasurer] = $this->residentDesk(SystemRole::Treasurer);
        $this->actingAs($treasurer)->get(route('reports.statistics'))->assertOk();

        [$viewer] = $this->residentDesk(SystemRole::Viewer);
        $this->actingAs($viewer)->get(route('reports.residents'))->assertOk();
        $this->actingAs($this->centralAdmin())->get(route('reports.statistics'))->assertStatus(409);
    }
}
