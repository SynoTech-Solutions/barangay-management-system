<?php

namespace Tests\Feature\Residents;

use App\Domain\Audit\AuditRedactor;
use App\Enums\AuditAction;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\FamilyMember;
use App\Models\Resident;
use App\Models\ResidentEducation;
use App\Models\ResidentWorkHistory;
use App\Support\StoragePaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ResidentDetailsTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_family_members_can_be_free_text_or_linked_to_a_resident(): void
    {
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);
        $resident = Resident::factory()->create();
        $mother = Resident::factory()->named('Reyes', 'Maria')->create(['birthdate' => '1950-02-03']);
        $relationship = $this->lookup(LookupType::Relationship, 'Mother');

        $this->actingAs($clerk)
            ->post(route('residents.family-members.store', $resident), ['relationship_id' => $relationship->id, 'full_name' => ' Santos,  Ana ', 'birthdate' => '2010-01-01'])
            ->assertSessionHasNoErrors();
        $this->actingAs($clerk)
            ->post(route('residents.family-members.store', $resident), ['relationship_id' => $relationship->id, 'related_resident_id' => $mother->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($clerk)
            ->post(route('residents.family-members.store', $resident), ['relationship_id' => $relationship->id, 'related_resident_id' => $mother->id])
            ->assertSessionHasErrors('related_resident_id');
        $this->actingAs($clerk)
            ->post(route('residents.family-members.store', $resident), ['relationship_id' => $relationship->id])
            ->assertSessionHasErrors('full_name');

        $this->inBarangay($barangay);
        $members = FamilyMember::query()->orderBy('id')->get();
        $this->assertCount(2, $members);
        $this->assertSame('Santos, Ana', $members[0]->full_name);
        $this->assertSame('2010-01-01', $members[0]->birthdate?->toDateString());
        $this->assertSame('Reyes, Maria', $members[1]->full_name);
        $this->assertSame('1950-02-03', $members[1]->birthdate?->toDateString());

        $this->actingAs($clerk)->delete(route('residents.family-members.destroy', [$resident, $members[0]]))->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertSame($clerk->id, FamilyMember::query()->withTrashed()->findOrFail($members[0]->id)->deleted_by);
        $this->assertSame(1, FamilyMember::query()->count());
    }

    public function test_education_end_date_is_saved_without_a_start_date_and_order_is_checked(): void
    {
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);
        $resident = Resident::factory()->create();
        $school = $this->lookup(LookupType::School);
        $course = $this->lookup(LookupType::Course);

        $this->actingAs($clerk)
            ->post(route('residents.educations.store', $resident), ['school_id' => $school->id, 'course_id' => $course->id, 'ended_on' => '2004-03-31'])
            ->assertSessionHasNoErrors();
        $this->actingAs($clerk)
            ->post(route('residents.educations.store', $resident), ['school_id' => $school->id, 'course_id' => $course->id, 'started_on' => '2005-06-01', 'ended_on' => '2004-03-31'])
            ->assertSessionHasErrors('ended_on');

        $this->inBarangay($barangay);
        $education = ResidentEducation::query()->sole();
        $this->assertNull($education->started_on);
        $this->assertSame('2004-03-31', $education->ended_on?->toDateString());
    }

    public function test_current_jobs_have_no_end_date(): void
    {
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);
        $resident = Resident::factory()->create();

        $this->actingAs($clerk)
            ->post(route('residents.work-histories.store', $resident), [
                'company_id' => $this->lookup(LookupType::Company)->id,
                'position_id' => $this->lookup(LookupType::Position)->id,
                'employment_status_id' => $this->lookup(LookupType::EmploymentStatus)->id,
                'started_on' => '2020-01-01',
                'ended_on' => '2021-01-01',
                'is_current' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $work = ResidentWorkHistory::query()->sole();
        $this->assertTrue($work->is_current);
        $this->assertNull($work->ended_on);
    }

    public function test_photos_are_stored_privately_served_with_authorization_and_never_audited_as_bytes(): void
    {
        Storage::fake(StoragePaths::PRIVATE_DISK);
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);
        $resident = Resident::factory()->create();

        $this->actingAs($clerk)
            ->post(route('residents.photo.update', $resident), ['photo' => UploadedFile::fake()->image('face.jpg', 400, 400)])
            ->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $path = $resident->refresh()->photo_path;
        $this->assertNotNull($path);
        Storage::disk(StoragePaths::PRIVATE_DISK)->assertExists($path);

        $this->actingAs($clerk)->get(route('residents.photo.show', $resident))->assertOk();

        $audit = AuditEvent::query()->where('action', AuditAction::ResidentPhotoChanged)->sole();
        $this->assertSame(AuditRedactor::REDACTED, $audit->changes['after']['photo_path'] ?? null);
        $this->assertLessThan(1024, strlen((string) json_encode($audit->changes)));

        $this->actingAs($clerk)
            ->post(route('residents.photo.update', $resident), ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('photo');

        $this->actingAs($clerk)->delete(route('residents.photo.destroy', $resident))->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertNull($resident->refresh()->photo_path);
        Storage::disk(StoragePaths::PRIVATE_DISK)->assertMissing($path);
    }

    public function test_photo_size_limit_is_enforced(): void
    {
        Storage::fake(StoragePaths::PRIVATE_DISK);
        [$clerk] = $this->residentDesk(SystemRole::Clerk);
        $resident = Resident::factory()->create();

        $this->actingAs($clerk)
            ->post(route('residents.photo.update', $resident), ['photo' => UploadedFile::fake()->image('huge.jpg')->size(50_000)])
            ->assertSessionHasErrors('photo');
    }
}
