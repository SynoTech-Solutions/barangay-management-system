<?php

namespace Tests\Feature\Finance;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Barangay;
use App\Models\BarangayClearance;
use App\Models\BusinessClearance;
use App\Models\Cedula;
use App\Models\CollectionType;
use App\Models\IdCard;
use App\Models\IndigencyCertificate;
use App\Models\OtherCollection;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ConsolidatedCollectionReportTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    private const string DAY = '2030-03-10';

    private function seedCollections(Barangay $barangay): void
    {
        $this->inBarangay($barangay);
        $day = CarbonImmutable::parse(self::DAY.' 09:30:00');
        $resident = Resident::factory()->create(['last_name' => 'Payer', 'first_name' => 'Sample']);
        $type = CollectionType::factory()->create(['name' => 'Facility Rental']);

        BarangayClearance::factory()->create(['resident_id' => $resident->id, 'or_number' => 'OR-1', 'amount' => '100.00', 'doc_stamp' => '30.00', 'issued_at' => $day]);
        BarangayClearance::factory()->cancelled()->create(['resident_id' => $resident->id, 'or_number' => 'OR-X', 'amount' => '999.00', 'issued_at' => $day]);
        BarangayClearance::factory()->create(['resident_id' => $resident->id, 'amount' => '0.00', 'doc_stamp' => '0.00', 'issued_at' => $day]);
        BusinessClearance::factory()->create(['or_number' => 'OR-2', 'amount' => '500.00', 'doc_stamp' => '15.00', 'issued_at' => $day->addDay(), 'updated_at' => $day->addMonths(2)]);
        Cedula::factory()->create(['resident_id' => $resident->id, 'amount' => '435.00', 'issued_at' => $day]);
        IdCard::factory()->create(['resident_id' => $resident->id, 'or_number' => 'OR-3', 'amount' => '90.00', 'issued_at' => $day]);
        IndigencyCertificate::factory()->create(['resident_id' => $resident->id, 'issued_at' => $day]);
        OtherCollection::factory()->create(['collection_type_id' => $type->id, 'or_number' => 'OR-4', 'amount' => '250.50', 'paid_on' => self::DAY, 'recorded_at' => $day->addMonth()]);
        OtherCollection::factory()->cancelled()->create(['collection_type_id' => $type->id, 'or_number' => 'OR-5', 'amount' => '75.00', 'paid_on' => self::DAY]);
        OtherCollection::factory()->create(['collection_type_id' => $type->id, 'or_number' => 'OR-6', 'amount' => '10.00', 'paid_on' => '2030-04-01']);
    }

    public function test_the_report_sums_every_source_by_payment_date_with_doc_stamps_and_without_cancelled_rows(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $this->seedCollections($barangay);
        [, $other] = $this->financeDesk();
        $this->seedCollections($other);

        $this->actingAs($treasurer)->get(route('collections.report.index', ['from' => '2030-03-01', 'to' => '2030-03-31']))->assertInertia(function (Assert $page): void {
            $page->component('collections/Report')
                ->where('totals.count', 5)
                ->where('totals.amount', '1375.50')
                ->where('totals.doc_stamp', '45.00')
                ->where('totals.total', '1420.50')
                ->has('rows.data', 5);

            $bySource = collect($page->toArray()['props']['bySource'])->keyBy('source');
            $this->assertSame('130.00', $bySource['barangay-clearance']['total']);
            $this->assertSame('515.00', $bySource['business-clearance']['total']);
            $this->assertSame('435.00', $bySource['cedula']['total']);
            $this->assertSame('90.00', $bySource['id-card']['total']);
            $this->assertSame('250.50', $bySource['other-collection']['total']);
            $this->assertArrayNotHasKey('indigency-certificate', $bySource->all());

            $byType = collect($page->toArray()['props']['byType'])->keyBy('label');
            $this->assertSame('250.50', $byType['Facility Rental']['total']);
            $this->assertSame(1, $byType['Facility Rental']['count']);

            $rows = $page->toArray()['props']['rows']['data'];
            $this->assertSame(['2030-03-10', '2030-03-10', '2030-03-10', '2030-03-10', '2030-03-11'], array_column($rows, 'paid_on'));
        });

        $this->actingAs($treasurer)->get(route('collections.report.index', ['from' => '2030-03-11', 'to' => '2030-03-11', 'source' => 'business-clearance']))
            ->assertInertia(fn (Assert $page) => $page->where('totals.count', 1)->where('rows.data.0.or_number', 'OR-2'));

        $this->actingAs($treasurer)->get(route('collections.report.print', ['from' => '2030-03-01', 'to' => '2030-04-30']))
            ->assertInertia(fn (Assert $page) => $page->component('collections/ReportPrint')->has('rows', 6)->where('totals.total', '1430.50'));
    }

    public function test_the_csv_export_is_audited_and_needs_the_export_permission(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);
        $this->seedCollections($barangay);

        $response = $this->actingAs($treasurer)->get(route('collections.report.export', ['from' => '2030-03-01', 'to' => '2030-03-31']));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Payment date', $csv);
        $this->assertStringContainsString('Facility Rental', $csv);
        $this->assertStringContainsString('130.00', $csv);
        $this->assertSame(6, substr_count(trim($csv), "\n") + 1);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::Exported)->exists());

        $this->actingAs($viewer)->get(route('collections.report.index'))->assertOk();
        $this->actingAs($viewer)->get(route('collections.report.export'))->assertForbidden();
    }

    public function test_the_report_follows_its_flag(): void
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $this->turnOff(FeatureFlag::ConsolidatedCollectionReport, $barangay);

        $this->actingAs($treasurer)->get(route('collections.report.index'))->assertNotFound();
        $this->actingAs($treasurer)->get(route('collections.report.export'))->assertNotFound();
    }
}
