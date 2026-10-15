<?php

namespace Tests\Feature\Forms;

use App\Domain\Reports\ColumnDefinition;
use App\Domain\Reports\DatasetCatalog;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilter;
use App\Domain\Reports\ReportSort;
use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\ReportColumnSensitivity;
use App\Enums\ReportColumnType;
use App\Enums\ReportDataset;
use App\Enums\ReportOperator;
use App\Enums\SortDirection;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Barangay;
use App\Models\ColorCategory;
use App\Models\Resident;
use App\Models\ResidentPoliticalTag;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesForms;
use Tests\TestCase;

class SavedReportTest extends TestCase
{
    use CreatesForms, RefreshDatabase;

    /**
     * @return array{0: User, 1: Barangay}
     */
    private function designer(SystemRole $role = SystemRole::BarangayAdmin): array
    {
        [$user, $barangay] = $this->residentDesk($role);
        $this->turnOn(FeatureFlag::SqlReportBuilder, $barangay);

        return [$user, $barangay];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Senior women by purok',
            'dataset' => ReportDataset::Residents->value,
            'columns' => ['resident_number', 'full_name', 'age'],
            'filters' => [['column' => 'gender', 'operator' => 'eq', 'value' => 'female'], ['column' => 'age', 'operator' => 'gte', 'value' => '60']],
            'sort' => [['column' => 'full_name', 'direction' => 'asc']],
            'group_by' => null,
            ...$overrides,
        ];
    }

    public function test_a_definition_is_saved_as_versioned_json_and_runs_tenant_scoped(): void
    {
        [$admin, $barangay] = $this->designer();
        Resident::factory()->create(['first_name' => 'Older', 'last_name' => 'Woman', 'gender' => 'female', 'birthdate' => now()->subYears(70)->toDateString()]);
        Resident::factory()->create(['first_name' => 'Younger', 'last_name' => 'Woman', 'gender' => 'female', 'birthdate' => now()->subYears(30)->toDateString()]);
        Resident::factory()->create(['first_name' => 'Older', 'last_name' => 'Man', 'gender' => 'male', 'birthdate' => now()->subYears(70)->toDateString()]);
        [, $other] = $this->residentDesk();
        $this->inBarangay($other);
        Resident::factory()->create(['first_name' => 'Outside', 'last_name' => 'Woman', 'gender' => 'female', 'birthdate' => now()->subYears(80)->toDateString()]);

        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload())->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $report = SavedReport::query()->sole();
        $this->assertSame(1, $report->definition['v']);
        $this->assertEquals(ReportDataset::Residents, ReportDefinition::fromArray($report->definition)->dataset);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::SavedReportSaved)->exists());

        $this->actingAs($admin)->get(route('reports.saved.show', $report))->assertInertia(fn (Assert $page) => $page
            ->component('reports/saved/Show')
            ->has('rows.data', 1)
            ->where('rows.data.0.full_name', 'Woman, Older')
            ->where('columns.2.key', 'age'));

        $csv = $this->actingAs($admin)->get(route('reports.saved.export', $report))->assertOk()->streamedContent();
        $this->assertStringContainsString('Woman, Older', $csv);
        $this->assertStringNotContainsString('Outside', $csv);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::Exported)->exists());
    }

    public function test_every_dataset_runs_with_every_column_filter_and_grouping(): void
    {
        [$admin] = $this->designer();
        $reports = [];
        foreach (ReportDataset::cases() as $dataset) {
            $columns = array_values(array_filter(DatasetCatalog::columns($dataset), fn (ColumnDefinition $column): bool => $column->sensitivity === ReportColumnSensitivity::None));
            $keys = array_map(fn (ColumnDefinition $column): string => $column->key, $columns);
            $filters = array_map(fn (ColumnDefinition $column): ReportFilter => new ReportFilter($column->key, ReportOperator::IsNotEmpty), array_filter($columns, fn (ColumnDefinition $column): bool => $column->type !== ReportColumnType::Boolean));
            $groupable = array_values(array_filter($columns, fn (ColumnDefinition $column): bool => $column->groupable));
            $reports[] = SavedReport::factory()->definedAs(new ReportDefinition($dataset, $keys, [], [new ReportSort($keys[0], SortDirection::Descending)]))->create();
            $reports[] = SavedReport::factory()->definedAs(new ReportDefinition($dataset, $keys, array_values($filters), groupBy: $groupable[0]->key))->create();
        }

        foreach ($reports as $report) {
            $this->actingAs($admin)->get(route('reports.saved.show', $report))->assertOk()->assertInertia(fn (Assert $page) => $page->component('reports/saved/Show'));
        }
    }

    public function test_grouping_counts_rows_per_value(): void
    {
        [$admin] = $this->designer();
        Resident::factory()->count(2)->create(['gender' => 'female']);
        Resident::factory()->create(['gender' => 'male']);
        $report = SavedReport::factory()->definedAs(new ReportDefinition(ReportDataset::Residents, ['gender'], groupBy: 'gender'))->create();

        $this->actingAs($admin)->get(route('reports.saved.show', $report))->assertInertia(fn (Assert $page) => $page
            ->where('columns.1.key', 'count')
            ->where('rows.data', [['gender' => 'Female', 'count' => '2'], ['gender' => 'Male', 'count' => '1']]));
    }

    public function test_columns_operators_and_datasets_outside_the_allowlist_are_rejected(): void
    {
        [$admin] = $this->designer();

        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload(['columns' => ['full_name', 'password']]))->assertSessionHasErrors('columns.1');
        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload(['columns' => ['residents.full_name; drop table users']]))->assertSessionHasErrors('columns.0');
        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload(['dataset' => 'users']))->assertSessionHasErrors('dataset');
        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload(['filters' => [['column' => 'age', 'operator' => 'like', 'value' => '6']]]))->assertSessionHasErrors('filters.0.operator');
        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload(['filters' => [['column' => 'age', 'operator' => 'contains', 'value' => '6']]]))->assertSessionHasErrors('filters.0.value');
        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload(['filters' => [['column' => 'age', 'operator' => 'gte', 'value' => "1' or '1'='1"]]]))->assertSessionHasErrors('filters.0.value');
        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload(['group_by' => 'full_name']))->assertSessionHasErrors('group_by');
        $this->assertSame(0, SavedReport::query()->count());
    }

    public function test_political_and_government_id_columns_need_their_flag_and_permission(): void
    {
        [$admin, $barangay] = $this->designer();
        $resident = Resident::factory()->create(['national_id' => 'NID-123']);
        $category = ColorCategory::factory()->create(['name' => 'Blue']);
        ResidentPoliticalTag::query()->create(['resident_id' => $resident->id, 'color_category_id' => $category->id]);
        $report = SavedReport::factory()->definedAs(new ReportDefinition(ReportDataset::Residents, ['full_name', 'political_color', 'national_id']))->create();

        $this->actingAs($admin)->post(route('reports.saved.store'), $this->payload(['columns' => ['full_name', 'national_id']]))->assertSessionHasErrors('columns.1');
        $this->actingAs($admin)->get(route('reports.saved.show', $report))->assertInertia(fn (Assert $page) => $page
            ->where('columns', [['key' => 'full_name', 'label' => __('reports.saved.columns.full_name')]]));

        $filtered = SavedReport::factory()->definedAs(new ReportDefinition(ReportDataset::Residents, ['full_name'], [new ReportFilter('political_color', ReportOperator::Equals, 'Blue')]))->create();
        $this->actingAs($admin)->getJson(route('reports.saved.show', $filtered))->assertUnprocessable()->assertJsonPath('error.code', 'saved_report_unavailable');

        [$second] = $this->residentDesk(SystemRole::BarangayAdmin, $barangay);
        $this->turnOn(FeatureFlag::PoliticalColorTagging, $barangay);
        $this->turnOn(FeatureFlag::ReportGovernmentIdColumns, $barangay);
        $this->actingAs($second)->get(route('reports.saved.show', $report))->assertInertia(fn (Assert $page) => $page
            ->has('columns', 3)
            ->where('rows.data.0.political_color', 'Blue')
            ->where('rows.data.0.national_id', 'NID-123'));

        $secretary = $this->barangayUser(SystemRole::Secretary, $barangay);
        $this->actingAs($secretary)->get(route('reports.saved.show', $report))->assertInertia(fn (Assert $page) => $page->has('columns', 1));
    }

    public function test_permissions_flag_and_tenancy(): void
    {
        [$admin, $barangay] = $this->designer();
        $report = SavedReport::factory()->create();

        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);
        $this->actingAs($viewer)->get(route('reports.saved.index'))->assertOk();
        $this->actingAs($viewer)->get(route('reports.saved.show', $report))->assertOk();
        $this->actingAs($viewer)->get(route('reports.saved.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.saved.store'), $this->payload())->assertForbidden();

        $this->actingAs($admin)->post(route('reports.saved.deactivate', $report))->assertRedirect(route('reports.saved.index'));
        $this->actingAs($admin)->get(route('reports.saved.show', $report))->assertNotFound();

        [$outsider, $otherBarangay] = $this->designer();
        $this->inBarangay($barangay);
        $active = SavedReport::factory()->create();
        $this->actingAs($outsider)->get(route('reports.saved.show', $active))->assertNotFound();

        [$plain] = $this->residentDesk(SystemRole::BarangayAdmin);
        $this->actingAs($plain)->get(route('reports.saved.index'))->assertNotFound();
        $this->assertNotSame($barangay->id, $otherBarangay->id);
    }
}
