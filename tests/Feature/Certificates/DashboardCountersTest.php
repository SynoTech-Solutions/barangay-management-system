<?php

namespace Tests\Feature\Certificates;

use App\Enums\FeatureFlag;
use App\Enums\Permission;
use App\Enums\RoleScope;
use App\Enums\SystemRole;
use App\Models\BarangayClearance;
use App\Models\Blotter;
use App\Models\BusinessClearance;
use App\Models\Cedula;
use App\Models\IdCard;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class DashboardCountersTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    private function seedDocuments(): void
    {
        BarangayClearance::factory()->count(2)->create();
        BarangayClearance::factory()->released()->create(['issued_at' => now()->subDays(3)]);
        BusinessClearance::factory()->create();
        BusinessClearance::factory()->expiredOn(now()->subDay()->toDateString())->create(['issued_at' => now()->subYear()]);
        IdCard::factory()->count(3)->create();
        IdCard::factory()->cancelled()->create(['issued_at' => now()->subDays(2)]);
        Cedula::factory()->create();
        Cedula::factory()->released()->create(['issued_at' => now()->subDays(5)]);
        Blotter::factory()->count(2)->create();
        Blotter::factory()->resolved()->create();
        Blotter::factory()->cancelled()->create();
    }

    public function test_counters_show_documents_for_release_issued_today_and_expired_in_one_query(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-10 10:00:00'));
        [$secretary, $barangay] = $this->residentDesk();
        $this->seedDocuments();
        [, $other] = $this->residentDesk();
        $this->inBarangay($other);
        BarangayClearance::factory()->count(5)->create();
        $this->inBarangay($barangay);

        $this->actingAs($secretary)->get(route('dashboard'))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($secretary)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('counters', [
                ['key' => 'clearances-for-release', 'label' => 'Barangay clearances for release', 'count' => 2],
                ['key' => 'clearances-issued-today', 'label' => 'Barangay clearances issued today', 'count' => 2],
                ['key' => 'business-clearances-for-release', 'label' => 'Business clearances for release', 'count' => 1],
                ['key' => 'business-clearances-issued-today', 'label' => 'Business clearances issued today', 'count' => 1],
                ['key' => 'business-clearances-expired', 'label' => 'Businesses with expired clearances', 'count' => 1],
                ['key' => 'id-cards-for-release', 'label' => 'ID cards for release', 'count' => 3],
                ['key' => 'id-cards-issued-today', 'label' => 'ID cards issued today', 'count' => 3],
                ['key' => 'cedulas-for-release', 'label' => 'Cedulas for release', 'count' => 1],
                ['key' => 'cedulas-issued-today', 'label' => 'Cedulas issued today', 'count' => 1],
                ['key' => 'open-blotters', 'label' => 'Open blotter cases', 'count' => 2],
                ['key' => 'form-transactions-for-release', 'label' => 'Forms for release', 'count' => 0],
            ]));
        $counterQueries = array_filter(DB::getQueryLog(), fn (array $entry): bool => str_contains($entry['query'], 'barangay_clearances'));
        $this->assertCount(1, $counterQueries);
    }

    public function test_counters_follow_flags_and_permissions(): void
    {
        [$treasurer, $barangay] = $this->residentDesk(SystemRole::Treasurer);
        $this->seedDocuments();
        $this->turnOff(FeatureFlag::BusinessClearance, $barangay);
        $this->turnOff(FeatureFlag::DocumentReleaseStep, $barangay);

        $this->actingAs($treasurer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('counters', fn ($counters) => collect($counters)->pluck('key')->all() === ['clearances-issued-today', 'id-cards-issued-today', 'cedulas-issued-today', 'checks-for-release', 'unliquidated-cash-advances']));
    }

    public function test_counters_are_hidden_when_the_dashboard_flag_is_off_or_no_barangay_is_selected(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::DashboardPendingCounters, $barangay);

        $this->actingAs($secretary)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('counters', []));
        $this->actingAs($this->centralAdmin())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('counters', []));
    }

    public function test_a_custom_role_without_clearance_access_sees_only_id_card_counters(): void
    {
        $barangay = $this->residentDesk()[1];
        $role = Role::query()->create(['barangay_id' => $barangay->id, 'key' => 'front-desk', 'name' => 'Front desk', 'scope' => RoleScope::Barangay, 'is_system' => false]);
        RolePermission::query()->create(['role_id' => $role->id, 'permission' => Permission::ResidentsView->value]);
        $user = User::factory()->forBarangay($barangay)->create();
        UserRole::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'barangay_id' => $barangay->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('counters', fn ($counters) => collect($counters)->pluck('key')->all() === ['id-cards-for-release', 'id-cards-issued-today']));
    }
}
