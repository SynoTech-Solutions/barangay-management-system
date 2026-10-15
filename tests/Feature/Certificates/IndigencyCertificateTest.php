<?php

namespace Tests\Feature\Certificates;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Enums\SignatoryOffice;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Barangay;
use App\Models\IndigencyCertificate;
use App\Models\Resident;
use App\Models\Signatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class IndigencyCertificateTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    /**
     * @return array{0: User, 1: Barangay}
     */
    private function desk(SystemRole $role = SystemRole::Secretary): array
    {
        [$user, $barangay] = $this->residentDesk($role);
        $this->turnOn(FeatureFlag::IndigencyCertificate, $barangay);

        return [$user, $barangay];
    }

    public function test_issuing_allocates_a_number_snapshots_the_captain_and_prints_with_reprint_tracking(): void
    {
        [$secretary, $barangay] = $this->desk();
        Signatory::query()->create(['office' => SignatoryOffice::PunongBarangay, 'name' => 'Pedro Reyes', 'title' => 'Punong Barangay']);
        $purpose = $this->lookup(LookupType::CertificatePurpose, 'Medical assistance');
        $resident = $this->photographedResident();

        $this->actingAs($secretary)->post(route('indigency-certificates.store'), ['resident_id' => $resident->id, 'purpose_id' => $purpose->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('indigency-certificates.index'));

        $this->inBarangay($barangay);
        $certificate = IndigencyCertificate::query()->sole();
        $this->assertSame('IND-'.now()->year.'-000001', $certificate->certificate_number);
        $this->assertSame('Pedro Reyes', $certificate->certified_by_name);
        $this->assertSame(IssuedDocumentStatus::Issued, $certificate->status);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::IndigencyIssued)->exists());

        $this->actingAs($secretary)->get(route('indigency-certificates.print', $certificate))->assertInertia(fn (Assert $page) => $page
            ->component('indigency/Print')
            ->where('document.purpose', $purpose->label)
            ->where('document.is_reprint', false));
        $this->actingAs($secretary)->get(route('indigency-certificates.print', $certificate))->assertInertia(fn (Assert $page) => $page->where('document.is_reprint', true));
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::IndigencyPrinted)->count());
    }

    public function test_lifecycle_rules_apply(): void
    {
        [$secretary, $barangay] = $this->desk();
        $pending = IndigencyCertificate::factory()->create();
        $cancelled = IndigencyCertificate::factory()->cancelled()->create();
        $purpose = $this->lookup(LookupType::CertificatePurpose);

        $this->actingAs($secretary)->put(route('indigency-certificates.update', $pending), ['purpose_id' => $purpose->id])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->putJson(route('indigency-certificates.update', $cancelled), ['purpose_id' => $purpose->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'document_not_editable');
        $this->actingAs($secretary)->post(route('indigency-certificates.release'), ['ids' => [$pending->id, $cancelled->id]])->assertRedirect();
        $this->actingAs($secretary)->post(route('indigency-certificates.cancel'), ['ids' => [$pending->id]])->assertSessionHasErrors('reason');
        $this->actingAs($secretary)->postJson(route('indigency-certificates.store'), ['resident_id' => Resident::factory()->deceased()->create(['photo_path' => 'x.jpg'])->id, 'purpose_id' => $purpose->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_deceased');

        $this->inBarangay($barangay);
        $this->assertSame($purpose->id, $pending->refresh()->purpose_id);
        $this->assertTrue($pending->isPending());
    }

    public function test_the_module_is_off_by_default_and_follows_permissions_and_tenancy(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->actingAs($secretary)->get(route('indigency-certificates.index'))->assertNotFound();

        $this->turnOn(FeatureFlag::IndigencyCertificate, $barangay);
        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);
        $this->actingAs($viewer)->get(route('indigency-certificates.index'))->assertOk();
        $this->actingAs($viewer)->get(route('indigency-certificates.create'))->assertForbidden();

        [, $other] = $this->residentDesk();
        $this->inBarangay($other);
        $outsider = IndigencyCertificate::factory()->create();
        $this->inBarangay($barangay);
        $this->actingAs($secretary)->get(route('indigency-certificates.print', $outsider))->assertNotFound();
    }
}
