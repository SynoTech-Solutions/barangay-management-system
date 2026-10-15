<?php

namespace Tests\Feature\Residents;

use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\FamilyMember;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ResidentAccessTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_residents_of_another_barangay_are_not_found(): void
    {
        Storage::fake('local');
        [$admin, , $purok] = $this->residentDesk(SystemRole::BarangayAdmin);
        [, $other] = $this->residentDesk(SystemRole::Clerk);
        $outsider = Resident::factory()->create(['barangay_id' => $other->id]);
        $outsiderMember = FamilyMember::factory()->create(['resident_id' => $outsider->id]);

        $this->actingAs($admin)->get(route('residents.show', $outsider))->assertNotFound();
        $this->actingAs($admin)->get(route('residents.edit', $outsider))->assertNotFound();
        $this->actingAs($admin)->put(route('residents.update', $outsider), $this->residentPayload($purok))->assertNotFound();
        $this->actingAs($admin)->get(route('residents.photo.show', $outsider))->assertNotFound();
        $this->actingAs($admin)->post(route('residents.photo.update', $outsider), ['photo' => UploadedFile::fake()->image('face.jpg')])->assertNotFound();
        $this->actingAs($admin)->delete(route('residents.destroy', $outsider))->assertNotFound();
        $this->actingAs($admin)->get(route('residents.print', $outsider))->assertNotFound();
        $this->actingAs($admin)->delete(route('residents.family-members.destroy', [$outsider, $outsiderMember]))->assertNotFound();
    }

    public function test_family_members_must_belong_to_the_resident_in_the_url(): void
    {
        [$admin] = $this->residentDesk(SystemRole::BarangayAdmin);
        [$first, $second] = Resident::factory()->count(2)->create()->all();
        $member = FamilyMember::factory()->create(['resident_id' => $second->id]);

        $this->actingAs($admin)->delete(route('residents.family-members.destroy', [$first, $member]))->assertNotFound();
    }

    public function test_spouse_and_bulk_targets_must_be_in_the_same_barangay(): void
    {
        [$admin, , $purok] = $this->residentDesk(SystemRole::BarangayAdmin);
        [, $other] = $this->residentDesk(SystemRole::Clerk);
        $outsider = Resident::factory()->create(['barangay_id' => $other->id]);
        $foreignPurok = Purok::factory()->create(['barangay_id' => $other->id]);
        $this->inBarangay($admin->barangay);
        $resident = Resident::factory()->create();

        $this->actingAs($admin)
            ->put(route('residents.update', $resident), $this->residentPayload($purok, ['spouse_id' => $outsider->id]))
            ->assertSessionHasErrors('spouse_id');
        $this->actingAs($admin)
            ->post(route('residents.bulk.purok'), ['resident_ids' => [$outsider->id], 'purok_id' => $purok->id])
            ->assertSessionHasErrors('resident_ids.0');
        $this->actingAs($admin)
            ->post(route('residents.bulk.purok'), ['resident_ids' => [$resident->id], 'purok_id' => $foreignPurok->id])
            ->assertSessionHasErrors('purok_id');
    }

    public function test_viewers_can_only_read(): void
    {
        [$viewer, , $purok] = $this->residentDesk(SystemRole::Viewer);
        $resident = Resident::factory()->create();

        $this->actingAs($viewer)->getJson(route('residents.search', ['q' => $resident->last_name]))->assertOk();
        $this->actingAs($viewer)->get(route('residents.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('residents.store'), $this->residentPayload($purok))->assertForbidden();
        $this->actingAs($viewer)->put(route('residents.update', $resident), $this->residentPayload($purok))->assertForbidden();
        $this->actingAs($viewer)->get(route('residents.export'))->assertForbidden();
        $this->actingAs($viewer)->get(route('puroks.index'))->assertForbidden();
    }

    public function test_clerks_cannot_delete_bulk_edit_export_or_manage_puroks(): void
    {
        [$clerk, , $purok] = $this->residentDesk(SystemRole::Clerk);
        $resident = Resident::factory()->create();

        $this->actingAs($clerk)->delete(route('residents.destroy', $resident))->assertForbidden();
        $this->actingAs($clerk)->post(route('residents.bulk.purok'), ['resident_ids' => [$resident->id], 'purok_id' => $purok->id])->assertForbidden();
        $this->actingAs($clerk)->get(route('residents.export'))->assertForbidden();
        $this->actingAs($clerk)->post(route('puroks.store'), ['name' => 'Purok Bago'])->assertForbidden();
    }

    public function test_disabled_features_answer_not_found(): void
    {
        Storage::fake('local');
        [$admin, $barangay, $purok] = $this->residentDesk(SystemRole::BarangayAdmin);
        $resident = Resident::factory()->create(['photo_path' => 'barangays/1/residents/1/photo.jpg']);
        $school = $this->lookup(LookupType::School);
        $course = $this->lookup(LookupType::Course);

        foreach ([FeatureFlag::ResidentPhoto, FeatureFlag::ResidentQuickAdd, FeatureFlag::ResidentBulkEdit, FeatureFlag::ResidentWorkEducation, FeatureFlag::ResidentProfilePrint] as $flag) {
            $this->turnOff($flag, $barangay);
        }

        $this->actingAs($admin)->get(route('residents.photo.show', $resident))->assertNotFound();
        $this->actingAs($admin)->postJson(route('residents.photo.update', $resident), ['photo' => UploadedFile::fake()->image('face.jpg')])->assertNotFound();
        $this->actingAs($admin)->postJson(route('residents.quick-add'), ['last_name' => 'Reyes', 'first_name' => 'Maria'])->assertNotFound();
        $this->actingAs($admin)->postJson(route('residents.bulk.purok'), ['resident_ids' => [$resident->id], 'purok_id' => $purok->id])->assertNotFound();
        $this->actingAs($admin)->postJson(route('residents.educations.store', $resident), ['school_id' => $school->id, 'course_id' => $course->id])->assertNotFound();
        $this->actingAs($admin)->get(route('residents.print', $resident))->assertNotFound();
    }

    public function test_linking_family_members_is_rejected_when_the_feature_is_off(): void
    {
        [$admin, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        [$resident, $relative] = Resident::factory()->count(2)->create()->all();
        $relationship = $this->lookup(LookupType::Relationship);
        $this->turnOff(FeatureFlag::FamilyMemberLinking, $barangay);

        $this->actingAs($admin)
            ->post(route('residents.family-members.store', $resident), ['relationship_id' => $relationship->id, 'related_resident_id' => $relative->id])
            ->assertSessionHasErrors('related_resident_id');

        $this->actingAs($admin)
            ->post(route('residents.family-members.store', $resident), ['relationship_id' => $relationship->id, 'full_name' => 'Reyes, Maria'])
            ->assertSessionHasNoErrors();
    }

    public function test_central_staff_need_a_selected_barangay(): void
    {
        $this->actingAs($this->centralAdmin())->get(route('residents.index'))->assertStatus(409);
    }
}
