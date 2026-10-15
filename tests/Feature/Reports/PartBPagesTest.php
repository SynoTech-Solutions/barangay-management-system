<?php

namespace Tests\Feature\Reports;

use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Models\ColorCategory;
use App\Models\IdCard;
use App\Models\Resident;
use App\Services\Features\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class PartBPagesTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_id_card_forms_render_with_the_resident_preselected(): void
    {
        [$secretary] = $this->residentDesk();
        $resident = Resident::factory()->named('Dela Cruz', 'Juan')->create();
        $card = IdCard::factory()->create(['resident_id' => $resident->id]);

        $this->actingAs($secretary)->get(route('id-cards.create', ['resident_id' => $resident->id]))
            ->assertInertia(fn (Assert $page) => $page->component('id-cards/Form')->where('card', null)->where('resident.full_name', 'Dela Cruz, Juan'));
        $this->actingAs($secretary)->get(route('id-cards.edit', $card))
            ->assertInertia(fn (Assert $page) => $page->component('id-cards/Form')->where('card.id', $card->id));
    }

    public function test_blank_intake_form_follows_its_flag_and_enabled_sections(): void
    {
        [$viewer, $barangay] = $this->residentDesk(SystemRole::Viewer);

        $this->actingAs($viewer)->get(route('residents.blank-form'))
            ->assertInertia(fn (Assert $page) => $page->component('residents/BlankFormPrint')
                ->where('header.barangay', $barangay->name)
                ->where('sections.sectors', true)
                ->where('sections.work_education', true));

        $this->turnOff(FeatureFlag::BlankProfileForm, $barangay);
        $this->actingAs($viewer)->get(route('residents.blank-form'))->assertNotFound();
    }

    public function test_color_categories_page_renders_for_managers_when_enabled(): void
    {
        [$admin, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        app(FeatureFlags::class)->override(FeatureFlag::PoliticalColorTagging, $barangay, true, null, null);
        ColorCategory::factory()->create(['name' => 'Category Alpha']);

        $this->actingAs($admin)->get(route('political-tags.categories.index'))
            ->assertInertia(fn (Assert $page) => $page->component('political-tags/Categories')->where('categories.0.name', 'Category Alpha'));
    }
}
