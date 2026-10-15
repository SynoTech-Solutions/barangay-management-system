<?php

namespace Tests\Unit\Forms;

use App\Domain\Reports\DatasetCatalog;
use App\Domain\Reports\FilterValueRule;
use App\Domain\Reports\InvalidReportDefinition;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilter;
use App\Domain\Reports\ReportSort;
use App\Enums\ReportColumnSensitivity;
use App\Enums\ReportDataset;
use App\Enums\ReportOperator;
use App\Enums\SortDirection;
use PHPUnit\Framework\TestCase;

class SavedReportDefinitionTest extends TestCase
{
    public function test_definitions_round_trip_as_versioned_json(): void
    {
        $definition = new ReportDefinition(
            ReportDataset::Residents,
            ['full_name', 'purok'],
            [new ReportFilter('gender', ReportOperator::Equals, 'female')],
            [new ReportSort('full_name', SortDirection::Descending)],
            'purok',
        );

        $stored = json_decode((string) json_encode($definition->toArray()), true);
        $this->assertIsArray($stored);
        $this->assertSame(1, $stored['v']);
        $this->assertEquals($definition, ReportDefinition::fromArray($stored));
    }

    public function test_unknown_versions_and_datasets_are_rejected(): void
    {
        foreach ([['v' => 2, 'dataset' => 'residents'], ['v' => 1, 'dataset' => 'users'], ['dataset' => 'residents']] as $data) {
            try {
                ReportDefinition::fromArray($data);
                $this->fail('Expected an invalid definition.');
            } catch (InvalidReportDefinition) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_filter_values_are_checked_against_the_column_type(): void
    {
        $age = DatasetCatalog::column(ReportDataset::Residents, 'age');
        $gender = DatasetCatalog::column(ReportDataset::Residents, 'gender');
        $voter = DatasetCatalog::column(ReportDataset::Residents, 'is_registered_voter');
        $birthdate = DatasetCatalog::column(ReportDataset::Residents, 'birthdate');
        $this->assertNotNull($age);
        $this->assertNotNull($gender);
        $this->assertNotNull($voter);
        $this->assertNotNull($birthdate);

        $this->assertTrue(FilterValueRule::accepts($age, ReportOperator::AtLeast, '60'));
        $this->assertFalse(FilterValueRule::accepts($age, ReportOperator::AtLeast, '60; drop table residents'));
        $this->assertFalse(FilterValueRule::accepts($age, ReportOperator::Contains, '6'));
        $this->assertTrue(FilterValueRule::accepts($gender, ReportOperator::Equals, 'female'));
        $this->assertFalse(FilterValueRule::accepts($gender, ReportOperator::Equals, 'unknown'));
        $this->assertTrue(FilterValueRule::accepts($voter, ReportOperator::IsTrue, null));
        $this->assertFalse(FilterValueRule::accepts($voter, ReportOperator::Equals, 'yes'));
        $this->assertTrue(FilterValueRule::accepts($birthdate, ReportOperator::LessThan, '1990-12-31'));
        $this->assertFalse(FilterValueRule::accepts($birthdate, ReportOperator::LessThan, '1990-02-30'));
    }

    public function test_sensitive_columns_are_marked(): void
    {
        $this->assertSame(ReportColumnSensitivity::PoliticalTag, DatasetCatalog::column(ReportDataset::Residents, 'political_color')?->sensitivity);
        $this->assertSame(ReportColumnSensitivity::GovernmentId, DatasetCatalog::column(ReportDataset::Residents, 'national_id')?->sensitivity);
        $this->assertNull(DatasetCatalog::column(ReportDataset::Residents, 'password'));
    }
}
