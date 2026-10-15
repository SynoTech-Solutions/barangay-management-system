<?php

namespace Tests\Feature\Platform;

use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\ReceiptDocumentType;
use App\Enums\SystemRole;
use App\Exceptions\BmsException;
use App\Models\Barangay;
use App\Models\BarangayClearance;
use App\Models\CollectionType;
use App\Models\IdCard;
use App\Models\OfficialReceipt;
use App\Models\User;
use App\Services\Certificates\BarangayClearanceService;
use App\Services\Finance\OtherCollectionService;
use App\Services\IdCards\IdCardService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class OfficialReceiptTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    /**
     * @return array{0: User, 1: Barangay}
     */
    private function desk(): array
    {
        [$user, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $this->turnOff(FeatureFlag::IssuanceRequiresPhoto, $barangay);

        return [$user, $barangay];
    }

    private function issueClearance(User $actor, string $orNumber): BarangayClearance
    {
        return app(BarangayClearanceService::class)->issue($this->photographedResident()->id, [
            'clearance_type_id' => $this->lookup(LookupType::ClearanceType)->id,
            'purpose_id' => $this->lookup(LookupType::ClearancePurpose)->id,
            'or_number' => $orNumber,
            'amount' => '50.00',
        ], $actor);
    }

    /**
     * @return array{collection_type_id: int, resident_id: null, payer_name: string, or_number: string, amount: string, paid_on: string, remarks: null}
     */
    private function collection(string $orNumber): array
    {
        return [
            'collection_type_id' => CollectionType::factory()->create()->id,
            'resident_id' => null,
            'payer_name' => 'Walk-in Payer',
            'or_number' => $orNumber,
            'amount' => '10.00',
            'paid_on' => now()->toDateString(),
            'remarks' => null,
        ];
    }

    private function assertOrTaken(callable $attempt): void
    {
        try {
            $attempt();
            $this->fail('A duplicate OR number was saved.');
        } catch (BmsException $exception) {
            $this->assertSame('or_number_taken', $exception->errorCode->value);
        }
    }

    public function test_services_that_skip_validation_still_cannot_reuse_an_or_number_across_document_types(): void
    {
        [$actor] = $this->desk();
        $this->issueClearance($actor, 'or-55');

        $this->assertOrTaken(fn () => app(OtherCollectionService::class)->record($this->collection(' OR-55 '), $actor));
        $this->assertOrTaken(fn () => app(IdCardService::class)->issue($this->photographedResident()->id, ['or_number' => 'Or-55', 'amount' => '0'], $actor));

        $this->assertSame(1, OfficialReceipt::query()->count());
        $this->assertSame(1, BarangayClearance::query()->count());
        $this->assertSame(0, IdCard::query()->count());
    }

    public function test_the_database_rejects_a_second_active_receipt_for_the_same_number(): void
    {
        [, $barangay] = $this->desk();
        $row = ['barangay_id' => $barangay->id, 'or_number' => 'OR-1', 'created_at' => now(), 'updated_at' => now()];

        DB::table('official_receipts')->insert([...$row, 'document_type' => ReceiptDocumentType::IdCard->value, 'document_id' => 1, 'cancelled_at' => now()]);
        DB::table('official_receipts')->insert([...$row, 'document_type' => ReceiptDocumentType::IdCard->value, 'document_id' => 2]);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('official_receipts')->insert([...$row, 'document_type' => ReceiptDocumentType::OtherCollection->value, 'document_id' => 3]);
    }

    public function test_cancelling_or_renumbering_a_document_frees_its_number(): void
    {
        [$actor] = $this->desk();
        $clearance = $this->issueClearance($actor, 'OR-60');
        $other = $this->issueClearance($actor, 'OR-61');

        app(BarangayClearanceService::class)->cancel([$clearance->id], 'Wrong payer', $actor);
        app(OtherCollectionService::class)->record($this->collection('OR-60'), $actor);

        app(BarangayClearanceService::class)->update($other, ['or_number' => 'OR-62', 'amount' => '50.00'], $actor);
        app(OtherCollectionService::class)->record($this->collection('OR-61'), $actor);

        $this->assertOrTaken(fn () => app(OtherCollectionService::class)->record($this->collection('OR-62'), $actor));
        $this->assertSame(['OR-60', 'OR-61', 'OR-62'], OfficialReceipt::query()->whereNull('cancelled_at')->orderBy('or_number')->pluck('or_number')->all());
    }

    public function test_other_barangays_may_use_the_same_number(): void
    {
        [$actor] = $this->desk();
        $this->issueClearance($actor, 'OR-70');

        [$neighbor] = $this->desk();
        $this->issueClearance($neighbor, 'OR-70');

        $this->assertSame(2, OfficialReceipt::acrossBarangays()->where('or_number', 'OR-70')->count());
    }

    public function test_the_migration_backfills_receipts_from_existing_documents(): void
    {
        $this->desk();
        $first = IdCard::factory()->create(['or_number' => 'or  80']);
        BarangayClearance::factory()->cancelled()->create(['or_number' => 'OR 80']);
        IdCard::factory()->create(['or_number' => null]);

        Schema::drop('official_receipts');
        $migration = require database_path('migrations/2026_10_21_000001_create_official_receipts_table.php');
        $migration->up();

        $receipts = OfficialReceipt::query()->orderBy('id')->get();
        $this->assertCount(2, $receipts);
        $this->assertSame(['OR 80', 'OR 80'], $receipts->pluck('or_number')->all());
        $active = $receipts->whereNull('cancelled_at')->sole();
        $this->assertSame(ReceiptDocumentType::IdCard, $active->document_type);
        $this->assertSame($first->id, $active->document_id);
    }
}
