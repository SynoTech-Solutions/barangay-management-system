<?php

namespace Tests\Feature\PoliticalTags;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Barangay;
use App\Models\ColorCategory;
use App\Models\Resident;
use App\Models\ResidentPoliticalTag;
use App\Services\Features\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class PoliticalTagTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    private function turnOn(FeatureFlag $flag, Barangay $barangay): void
    {
        app(FeatureFlags::class)->override($flag, $barangay, true, null, null);
    }

    /**
     * @return array{0: Resident, 1: ColorCategory}
     */
    private function taggedResident(): array
    {
        $resident = Resident::factory()->named('Dela Cruz', 'Juan')->create(['is_registered_voter' => true]);
        $category = ColorCategory::factory()->create(['name' => 'Category Zeta', 'color_hex' => '#123456']);
        ResidentPoliticalTag::query()->create(['resident_id' => $resident->id, 'color_category_id' => $category->id]);

        return [$resident, $category];
    }

    public function test_tags_stay_hidden_while_the_flag_is_off_even_for_administrators(): void
    {
        [$admin] = $this->residentDesk(SystemRole::BarangayAdmin);
        [$resident] = $this->taggedResident();

        $this->actingAs($admin)->get(route('residents.show', $resident))
            ->assertInertia(fn (Assert $page) => $page->where('politicalTag', null));
        $this->actingAs($admin)->get(route('residents.index'))
            ->assertInertia(fn (Assert $page) => $page->where('politicalTags', null)->where('colorCategories', []));
        $this->actingAs($admin)->get(route('reports.political-status'))->assertNotFound();
        $this->actingAs($admin)->get(route('political-tags.categories.index'))->assertNotFound();
    }

    public function test_tags_need_the_view_permission_and_never_reach_exports_or_prints(): void
    {
        [$admin, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $secretary = $this->barangayUser(SystemRole::Secretary, $barangay);
        $this->turnOn(FeatureFlag::PoliticalColorTagging, $barangay);
        $this->inBarangay($barangay);
        [$resident] = $this->taggedResident();

        $this->actingAs($admin)->get(route('residents.show', $resident))
            ->assertInertia(fn (Assert $page) => $page->where('politicalTag.name', 'Category Zeta'));
        $this->actingAs($secretary)->get(route('residents.show', $resident))
            ->assertInertia(fn (Assert $page) => $page->where('politicalTag', null));
        $this->actingAs($secretary)->get(route('reports.political-status'))->assertForbidden();

        $export = $this->actingAs($admin)->get(route('residents.export'))->streamedContent();
        $this->assertStringNotContainsString('Category Zeta', $export);

        $print = $this->actingAs($admin)->get(route('residents.print', $resident));
        $this->assertStringNotContainsString('Category Zeta', (string) $print->getContent());
        $this->assertStringNotContainsString('Category Zeta', (string) $this->actingAs($admin)->get(route('reports.residents'))->getContent());
    }

    public function test_bulk_color_change_audits_each_changed_resident(): void
    {
        [$admin, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $this->turnOn(FeatureFlag::PoliticalColorTagging, $barangay);
        $this->inBarangay($barangay);
        [$tagged, $category] = $this->taggedResident();
        $other = Resident::factory()->create();
        $newCategory = ColorCategory::factory()->create();

        $this->actingAs($admin)
            ->post(route('residents.bulk.color'), ['resident_ids' => [$tagged->id, $other->id], 'color_category_id' => $category->id])
            ->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::PoliticalTagChanged)->count());

        $this->actingAs($admin)
            ->post(route('residents.bulk.color'), ['resident_ids' => [$tagged->id, $other->id], 'color_category_id' => $newCategory->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('residents.bulk.color'), ['resident_ids' => [$other->id], 'color_category_id' => null])
            ->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(4, AuditEvent::query()->where('action', AuditAction::PoliticalTagChanged)->count());
        $this->assertSame($newCategory->id, ResidentPoliticalTag::query()->where('resident_id', $tagged->id)->value('color_category_id'));
        $this->assertFalse(ResidentPoliticalTag::query()->where('resident_id', $other->id)->exists());
    }

    public function test_bulk_color_change_needs_the_manage_permission_and_bulk_edit_flag(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $admin = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->turnOn(FeatureFlag::PoliticalColorTagging, $barangay);
        $this->inBarangay($barangay);
        $resident = Resident::factory()->create();
        $category = ColorCategory::factory()->create();

        $this->actingAs($secretary)->post(route('residents.bulk.color'), ['resident_ids' => [$resident->id], 'color_category_id' => $category->id])->assertForbidden();

        $this->turnOff(FeatureFlag::ResidentBulkEdit, $barangay);
        $this->actingAs($admin)->postJson(route('residents.bulk.color'), ['resident_ids' => [$resident->id], 'color_category_id' => $category->id])->assertNotFound();
    }

    public function test_color_categories_validate_hex_colors(): void
    {
        [$admin, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $this->turnOn(FeatureFlag::PoliticalColorTagging, $barangay);

        $this->actingAs($admin)->post(route('political-tags.categories.store'), ['name' => 'Blue group', 'color_hex' => 'blue'])->assertSessionHasErrors('color_hex');
        $this->actingAs($admin)->post(route('political-tags.categories.store'), ['name' => 'Blue group', 'color_hex' => '#1E40AF'])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame('#1e40af', ColorCategory::query()->sole()->color_hex);
    }

    public function test_status_by_color_counts_living_registered_voters(): void
    {
        [$admin, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $this->turnOn(FeatureFlag::PoliticalColorTagging, $barangay);
        $this->inBarangay($barangay);
        [, $category] = $this->taggedResident();
        $deceased = Resident::factory()->deceased()->create(['is_registered_voter' => true]);
        ResidentPoliticalTag::query()->create(['resident_id' => $deceased->id, 'color_category_id' => $category->id]);

        $this->actingAs($admin)->get(route('reports.political-status'))->assertInertia(fn (Assert $page) => $page
            ->component('reports/PoliticalStatus')
            ->where('byColor.0.name', 'Category Zeta')
            ->where('byColor.0.total', 1));
    }

    public function test_purge_command_removes_tags_and_categories_of_one_barangay_and_audits(): void
    {
        [, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $this->taggedResident();
        [, $other] = $this->residentDesk();
        $this->taggedResident();

        $this->artisan('bms:political-tags:purge', ['--barangay' => $barangay->psgc_code])
            ->expectsConfirmation("Permanently delete all political color tags and categories of {$barangay->name}?", 'yes')
            ->assertSuccessful();

        $this->assertSame(0, ResidentPoliticalTag::acrossBarangays()->where('barangay_id', $barangay->id)->count());
        $this->assertSame(0, ColorCategory::acrossBarangays()->where('barangay_id', $barangay->id)->count());
        $this->assertSame(1, ResidentPoliticalTag::acrossBarangays()->where('barangay_id', $other->id)->count());
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::PoliticalTagsPurged)->where('barangay_id', $barangay->id)->exists());
    }

    public function test_purge_command_does_nothing_without_confirmation(): void
    {
        [, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $this->taggedResident();

        $this->artisan('bms:political-tags:purge', ['--barangay' => $barangay->psgc_code])
            ->expectsConfirmation("Permanently delete all political color tags and categories of {$barangay->name}?", 'no')
            ->assertFailed();

        $this->assertSame(1, ResidentPoliticalTag::acrossBarangays()->count());
    }
}
