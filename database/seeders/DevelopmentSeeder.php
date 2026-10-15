<?php

namespace Database\Seeders;

use App\Domain\Forms\FieldDraft;
use App\Domain\Forms\TemplateContent;
use App\Enums\FormInputType;
use App\Enums\FormTemplateKind;
use App\Enums\Gender;
use App\Enums\LookupType;
use App\Enums\SignatoryOffice;
use App\Enums\SystemRole;
use App\Models\Barangay;
use App\Models\BarangayProfile;
use App\Models\CollectionType;
use App\Models\FormTemplate;
use App\Models\LookupValue;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Purok;
use App\Models\Region;
use App\Models\Resident;
use App\Models\Signatory;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Access\RoleSynchronizer;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Forms\FormPicklistService;
use App\Services\Forms\FormTemplateService;
use App\Services\Residents\ResidentRegistry;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DevelopmentSeeder extends Seeder
{
    private const int GENERATED_PASSWORD_LENGTH = 20;

    private const string SAMPLE_REGION_CODE = '9900000000';

    private const string SAMPLE_PROVINCE_CODE = '9901000000';

    private const string SAMPLE_MUNICIPALITY_CODE = '9901010000';

    private const string SAMPLE_BARANGAY_CODE = '9901010001';

    private const array SAMPLE_COLLECTION_TYPES = ['Facility Rental', 'Certification Fee', 'Garbage Fee'];

    private const string SAMPLE_PICKLIST = 'Complaint types';

    private const array SAMPLE_PICKLIST_ITEMS = ['Unjust vexation', 'Light threats', 'Trespass to dwelling', 'Oral defamation'];

    private const string SAMPLE_TEMPLATE = 'Certificate of residency';

    private const string SAMPLE_TEMPLATE_TYPE = 'Certificates and clearances';

    private const string SAMPLE_TEMPLATE_BODY = '<h1 style="text-align: center">Certificate of Residency</h1><p>To whom it may concern:</p><p>This certifies that {{resident.full_name}}, {{resident.age}} years old, {{resident.civil_status}}, is a resident of {{resident.address}}.</p><p>This certification is issued upon request for {{field.purpose}}.</p><p>{{document.given_date}}</p><p style="text-align: right"><strong>{{signatory.punong_barangay}}</strong><br>{{signatory.punong_barangay_title}}</p>';

    private const array SAMPLE_PUROKS = ['Purok 1', 'Purok 2', 'Purok 3'];

    private const array SAMPLE_RESIDENTS = [
        ['last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'birthdate' => '1960-01-15', 'gender' => Gender::Male, 'mobile_number' => '09170000001'],
        ['last_name' => 'Dela Cruz', 'first_name' => 'Maria', 'birthdate' => '1965-07-04', 'gender' => Gender::Female, 'mobile_number' => '09170000002'],
    ];

    public function run(
        RoleSynchronizer $roles,
        CurrentBarangay $currentBarangay,
        ResidentRegistry $registry,
        ChartOfAccounts $chart,
        FormTemplateService $templates,
        FormPicklistService $picklists,
    ): void {
        $region = Region::query()->firstOrCreate(['psgc_code' => self::SAMPLE_REGION_CODE], ['name' => 'Sample Region']);
        $province = Province::query()->firstOrCreate(['psgc_code' => self::SAMPLE_PROVINCE_CODE], ['name' => 'Sample Province', 'region_id' => $region->id]);
        $municipality = Municipality::query()->firstOrCreate(['psgc_code' => self::SAMPLE_MUNICIPALITY_CODE], [
            'name' => 'Sample Town',
            'region_id' => $region->id,
            'province_id' => $province->id,
        ]);
        $barangay = Barangay::query()->firstOrCreate(['psgc_code' => self::SAMPLE_BARANGAY_CODE], [
            'name' => 'Barangay Uno',
            'municipality_id' => $municipality->id,
        ]);
        $currentBarangay->runAs($barangay, fn () => BarangayProfile::query()->firstOrCreate(['barangay_id' => $barangay->id]));

        $this->account('central', 'Central Administrator', null, $roles->systemRole(SystemRole::CentralAdmin)->id);
        $this->account('captain', 'Barangay Administrator', $barangay->id, $roles->systemRole(SystemRole::BarangayAdmin)->id);
        $this->account('secretary', 'Barangay Secretary', $barangay->id, $roles->systemRole(SystemRole::Secretary)->id);
        $this->account('treasurer', 'Barangay Treasurer', $barangay->id, $roles->systemRole(SystemRole::Treasurer)->id);
        $this->account('bookkeeper', 'Barangay Bookkeeper', $barangay->id, $roles->systemRole(SystemRole::Bookkeeper)->id);

        $chart->installStandard($barangay);
        $currentBarangay->runAs($barangay, fn () => $this->collectionTypes());

        $currentBarangay->runAs($barangay, fn () => $this->residents($registry));
        $currentBarangay->runAs($barangay, fn () => $this->forms($templates, $picklists, $barangay));
    }

    private function forms(FormTemplateService $templates, FormPicklistService $picklists, Barangay $barangay): void
    {
        if (FormTemplate::query()->exists()) {
            return;
        }

        $actor = User::query()->where('username', 'secretary')->firstOrFail();
        $list = $picklists->create(self::SAMPLE_PICKLIST, $actor);
        foreach (self::SAMPLE_PICKLIST_ITEMS as $label) {
            $picklists->addItem($list, $label, $actor);
        }

        $type = LookupValue::query()
            ->whereNull('barangay_id')
            ->where('type', LookupType::FormTemplateType)
            ->where('label', self::SAMPLE_TEMPLATE_TYPE)
            ->firstOrFail();

        $templates->create(
            $type->id,
            self::SAMPLE_TEMPLATE,
            FormTemplateKind::RichText,
            new TemplateContent(self::SAMPLE_TEMPLATE_BODY, [new FieldDraft('purpose', 'Purpose', FormInputType::Text, true, null)]),
            null,
            $barangay->id,
            $actor,
        );
    }

    private function collectionTypes(): void
    {
        foreach (self::SAMPLE_COLLECTION_TYPES as $name) {
            CollectionType::query()->firstOrCreate(['name' => $name]);
        }
    }

    private function residents(ResidentRegistry $registry): void
    {
        if (Resident::query()->exists()) {
            return;
        }

        Signatory::query()->firstOrCreate(['office' => SignatoryOffice::PunongBarangay], ['name' => 'Pedro Reyes', 'title' => 'Punong Barangay']);
        Signatory::query()->firstOrCreate(['office' => SignatoryOffice::Secretary], ['name' => 'Ana Santos', 'title' => 'Barangay Secretary']);

        $puroks = array_map(fn (string $name): Purok => Purok::query()->create(['name' => $name]), self::SAMPLE_PUROKS);
        $actor = User::query()->where('username', 'secretary')->firstOrFail();

        foreach (self::SAMPLE_RESIDENTS as $sample) {
            $registry->register([...$sample, 'purok_id' => $puroks[0]->id, 'is_registered_voter' => true], null, [], $actor);
        }
    }

    private function account(string $username, string $name, ?int $barangayId, int $roleId): void
    {
        if (User::query()->where('username', $username)->exists()) {
            return;
        }

        $password = Str::password(self::GENERATED_PASSWORD_LENGTH, symbols: false);

        $user = new User;
        $user->fill(['name' => $name, 'username' => $username, 'password' => $password]);
        $user->barangay_id = $barangayId;
        $user->is_active = true;
        $user->save();

        UserRole::query()->create(['user_id' => $user->id, 'role_id' => $roleId, 'barangay_id' => $barangayId]);

        $this->command->line("  {$username} / {$password}");
    }
}
