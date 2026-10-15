<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import BirthplacePicker from '@/components/residents/BirthplacePicker.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import EducationPanel from '@/components/residents/EducationPanel.vue';
import FamilyMembersPanel from '@/components/residents/FamilyMembersPanel.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import PhotoPanel from '@/components/residents/PhotoPanel.vue';
import ResidentPicker from '@/components/residents/ResidentPicker.vue';
import WorkHistoryPanel from '@/components/residents/WorkHistoryPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { index, show, store, update } from '@/routes/residents';
import type {
    FamilyMember,
    ResidentDetail,
    ResidentEducation,
    ResidentFormOptions,
    ResidentOption,
    ResidentWorkHistory,
    SelectOption,
} from '@/types';

type Tab =
    | 'personal'
    | 'address'
    | 'government'
    | 'sectors'
    | 'family'
    | 'history'
    | 'photo';

const props = defineProps<{
    resident: ResidentDetail | null;
    familyMembers: FamilyMember[];
    educations: ResidentEducation[];
    workHistories: ResidentWorkHistory[];
    options: ResidentFormOptions;
    limits: { photo_max_kilobytes: number };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.residents'), href: index() }] },
});

const { feature } = useAccess();
const resident = props.resident;

const LOOKUP_FIELDS: Array<[string, string, string]> = [
    ['title_id', 'title', 'title'],
    ['nationality_id', 'nationality', 'nationality'],
    ['religion_id', 'religion', 'religion'],
    ['blood_type_id', 'blood-type', 'blood_type'],
    ['body_type_id', 'body-type', 'body_type'],
    ['health_issue_id', 'health-issue', 'health_issue'],
    ['profession_id', 'profession', 'profession'],
    ['skill_id', 'skill', 'skill'],
    ['income_bracket_id', 'income-bracket', 'income_bracket'],
];

const CIRCUMSTANCE_FIELDS = [
    'is_living_with_parents',
    'is_senior_citizen_member',
    'is_pwd',
    'is_single_parent',
    'is_indigent',
] as const;

const FIELD_TABS: Record<string, Tab> = {
    purok_id: 'address',
    street_address: 'address',
    residence_type_id: 'address',
    residing_since: 'address',
    alternate_address: 'address',
    mobile_number: 'address',
    landline_number: 'address',
    email: 'address',
    facebook_url: 'address',
    national_id: 'government',
    drivers_license: 'government',
    tin: 'government',
    sss_gsis: 'government',
    pagibig: 'government',
    philhealth: 'government',
    voter_id: 'government',
    precinct_number: 'government',
};

const tab = ref<Tab>('personal');
const spouse = ref<ResidentOption | null>(resident?.spouse ?? null);
const birthplace = ref<SelectOption | null>(resident?.birthplace ?? null);

const form = useForm({
    title_id: resident?.title_id ?? null,
    last_name: resident?.last_name ?? '',
    first_name: resident?.first_name ?? '',
    middle_name: resident?.middle_name ?? '',
    name_extension: resident?.name_extension ?? '',
    nickname: resident?.nickname ?? '',
    birthdate: resident?.birthdate ?? '',
    gender: resident?.gender ?? null,
    civil_status: resident?.civil_status ?? null,
    nationality_id: resident?.nationality_id ?? null,
    religion_id: resident?.religion_id ?? null,
    blood_type_id: resident?.blood_type_id ?? null,
    body_type_id: resident?.body_type_id ?? null,
    health_issue_id: resident?.health_issue_id ?? null,
    profession_id: resident?.profession_id ?? null,
    skill_id: resident?.skill_id ?? null,
    income_bracket_id: resident?.income_bracket_id ?? null,
    height_cm: resident?.height_cm ?? '',
    weight_kg: resident?.weight_kg ?? '',
    national_id: resident?.national_id ?? '',
    drivers_license: resident?.drivers_license ?? '',
    tin: resident?.tin ?? '',
    sss_gsis: resident?.sss_gsis ?? '',
    pagibig: resident?.pagibig ?? '',
    philhealth: resident?.philhealth ?? '',
    is_registered_voter: resident?.is_registered_voter ?? false,
    voter_id: resident?.voter_id ?? '',
    precinct_number: resident?.precinct_number ?? '',
    is_living_with_parents: resident?.is_living_with_parents ?? false,
    is_senior_citizen_member: resident?.is_senior_citizen_member ?? false,
    is_pwd: resident?.is_pwd ?? false,
    is_single_parent: resident?.is_single_parent ?? false,
    is_indigent: resident?.is_indigent ?? false,
    purok_id: resident?.purok_id ?? null,
    street_address: resident?.street_address ?? '',
    is_permanent_resident: resident?.is_permanent_resident ?? false,
    residence_type_id: resident?.residence_type_id ?? null,
    residing_since: resident?.residing_since ?? '',
    alternate_address: resident?.alternate_address ?? '',
    mobile_number: resident?.mobile_number ?? '',
    landline_number: resident?.landline_number ?? '',
    email: resident?.email ?? '',
    facebook_url: resident?.facebook_url ?? '',
    is_deceased: resident?.is_deceased ?? false,
    deceased_on: resident?.deceased_on ?? '',
    cause_of_death: resident?.cause_of_death ?? '',
    sector_ids: (resident?.sectors ?? []).map((sector) => sector.id),
    confirm_duplicate: false,
});

const lookupModels = form as unknown as Record<string, number | null>;
const circumstanceModels = form as unknown as Record<string, boolean>;

const tabs = computed(() =>
    [
        { key: 'personal' as Tab, visible: true },
        { key: 'address' as Tab, visible: true },
        { key: 'government' as Tab, visible: true },
        {
            key: 'sectors' as Tab,
            visible: feature(FeatureFlag.SectorTagging),
        },
        { key: 'family' as Tab, visible: resident !== null },
        {
            key: 'history' as Tab,
            visible:
                resident !== null && feature(FeatureFlag.ResidentWorkEducation),
        },
        {
            key: 'photo' as Tab,
            visible: resident !== null && feature(FeatureFlag.ResidentPhoto),
        },
    ].filter((entry) => entry.visible),
);

const tabsWithErrors = computed(() => {
    const keys = new Set<Tab>();
    Object.keys(form.errors).forEach((field) => {
        if (field.startsWith('sector_ids')) {
            keys.add('sectors');
        } else {
            keys.add(FIELD_TABS[field] ?? 'personal');
        }
    });

    return keys;
});

function toggleSector(id: number, checked: boolean): void {
    form.sector_ids = checked
        ? [...form.sector_ids, id]
        : form.sector_ids.filter((value) => value !== id);
}

function submit(confirmDuplicate = false): void {
    form.confirm_duplicate = confirmDuplicate;
    const request = form.transform((data) => ({
        ...data,
        spouse_id: spouse.value?.id ?? null,
        birthplace_municipality_id: birthplace.value?.value ?? null,
    }));

    if (resident === null) {
        request.post(store.url(), { preserveScroll: true });
    } else {
        request.put(update.url(resident.id), { preserveScroll: true });
    }
}

const errors = computed(() => form.errors as Record<string, string>);
</script>

<template>
    <Head
        :title="
            resident ? t('residents.edit_title') : t('residents.create_title')
        "
    />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="
                    resident ? resident.full_name : t('residents.create_title')
                "
                :description="resident?.resident_number"
            />
            <Button v-if="resident" variant="outline" as-child>
                <Link :href="show(resident.id)">{{ t('residents.view') }}</Link>
            </Button>
        </div>

        <p
            v-if="resident?.is_incomplete"
            class="rounded-md border border-amber-500 p-3 text-sm"
        >
            {{ t('residents.incomplete_notice') }}
        </p>

        <nav class="flex flex-wrap gap-2 border-b pb-2">
            <Button
                v-for="entry in tabs"
                :key="entry.key"
                type="button"
                size="sm"
                :variant="tab === entry.key ? 'default' : 'ghost'"
                @click="tab = entry.key"
            >
                {{ t(`residents.tabs.${entry.key}`) }}
                <span
                    v-if="tabsWithErrors.has(entry.key)"
                    class="ml-1 inline-block size-2 rounded-full bg-red-600"
                />
            </Button>
        </nav>

        <form
            v-show="
                ['personal', 'address', 'government', 'sectors'].includes(tab)
            "
            class="grid gap-6"
            @submit.prevent="submit(false)"
        >
            <div
                v-if="errors.duplicate"
                class="grid gap-3 rounded-md border border-amber-500 p-4 text-sm"
            >
                <strong>{{ t('residents.duplicate_title') }}</strong>
                <p>{{ errors.duplicate }}</p>
                <Button
                    type="button"
                    variant="outline"
                    class="w-fit"
                    :disabled="form.processing"
                    @click="submit(true)"
                    >{{ t('residents.save_anyway') }}</Button
                >
            </div>

            <section v-show="tab === 'personal'" class="grid gap-4">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="title_id">{{
                            t('residents.fields.title')
                        }}</Label>
                        <OptionSelect
                            id="title_id"
                            v-model="form.title_id"
                            :options="options.lookups.title ?? []"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="last_name">{{
                            t('residents.fields.last_name')
                        }}</Label>
                        <Input
                            id="last_name"
                            v-model="form.last_name"
                            required
                        />
                        <InputError :message="form.errors.last_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="first_name">{{
                            t('residents.fields.first_name')
                        }}</Label>
                        <Input
                            id="first_name"
                            v-model="form.first_name"
                            required
                        />
                        <InputError :message="form.errors.first_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="middle_name">{{
                            t('residents.fields.middle_name')
                        }}</Label>
                        <Input id="middle_name" v-model="form.middle_name" />
                        <InputError :message="form.errors.middle_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="name_extension">{{
                            t('residents.fields.name_extension')
                        }}</Label>
                        <Input
                            id="name_extension"
                            v-model="form.name_extension"
                        />
                        <InputError :message="form.errors.name_extension" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="nickname">{{
                            t('residents.fields.nickname')
                        }}</Label>
                        <Input id="nickname" v-model="form.nickname" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="birthdate">{{
                            t('residents.fields.birthdate')
                        }}</Label>
                        <Input
                            id="birthdate"
                            v-model="form.birthdate"
                            type="date"
                        />
                        <InputError :message="form.errors.birthdate" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="birthplace">{{
                            t('residents.fields.birthplace')
                        }}</Label>
                        <BirthplacePicker
                            id="birthplace"
                            v-model="birthplace"
                        />
                        <InputError
                            :message="errors.birthplace_municipality_id"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="gender">{{
                            t('residents.fields.gender')
                        }}</Label>
                        <OptionSelect
                            id="gender"
                            v-model="form.gender"
                            :options="options.genders"
                            required
                        />
                        <InputError :message="form.errors.gender" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="civil_status">{{
                            t('residents.fields.civil_status')
                        }}</Label>
                        <OptionSelect
                            id="civil_status"
                            v-model="form.civil_status"
                            :options="options.civil_statuses"
                        />
                        <InputError :message="form.errors.civil_status" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="height_cm">{{
                            t('residents.fields.height_cm')
                        }}</Label>
                        <Input
                            id="height_cm"
                            v-model="form.height_cm"
                            type="number"
                            step="0.1"
                            min="0"
                        />
                        <InputError :message="form.errors.height_cm" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="weight_kg">{{
                            t('residents.fields.weight_kg')
                        }}</Label>
                        <Input
                            id="weight_kg"
                            v-model="form.weight_kg"
                            type="number"
                            step="0.1"
                            min="0"
                        />
                        <InputError :message="form.errors.weight_kg" />
                    </div>
                    <div
                        v-for="[field, type, label] in LOOKUP_FIELDS.slice(1)"
                        :key="field"
                        class="grid gap-2"
                    >
                        <Label :for="field">{{
                            t(`residents.fields.${label}`)
                        }}</Label>
                        <OptionSelect
                            :id="field"
                            v-model="lookupModels[field]"
                            :options="options.lookups[type] ?? []"
                        />
                        <InputError :message="errors[field]" />
                    </div>
                </div>

                <div class="grid gap-2 md:max-w-md">
                    <Label for="spouse">{{
                        t('residents.fields.spouse')
                    }}</Label>
                    <ResidentPicker
                        id="spouse"
                        v-model="spouse"
                        :exclude-id="resident?.id ?? null"
                    />
                    <InputError :message="errors.spouse_id" />
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">
                        {{ t('residents.fields.flags') }}
                    </legend>
                    <p
                        v-if="resident?.is_senior"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('residents.fields.senior_by_age') }}
                    </p>
                    <CheckboxField
                        v-for="field in CIRCUMSTANCE_FIELDS"
                        :key="field"
                        v-model="circumstanceModels[field]"
                        :label="t(`residents.fields.${field}`)"
                    />
                </fieldset>

                <fieldset class="grid gap-4 md:grid-cols-3">
                    <CheckboxField
                        v-model="form.is_deceased"
                        :label="t('residents.fields.is_deceased')"
                        class="md:col-span-3"
                    />
                    <template v-if="form.is_deceased">
                        <div class="grid gap-2">
                            <Label for="deceased_on">{{
                                t('residents.fields.deceased_on')
                            }}</Label>
                            <Input
                                id="deceased_on"
                                v-model="form.deceased_on"
                                type="date"
                            />
                            <InputError :message="form.errors.deceased_on" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="cause_of_death">{{
                                t('residents.fields.cause_of_death')
                            }}</Label>
                            <Input
                                id="cause_of_death"
                                v-model="form.cause_of_death"
                            />
                            <InputError :message="form.errors.cause_of_death" />
                        </div>
                    </template>
                </fieldset>
            </section>

            <section
                v-show="tab === 'address'"
                class="grid gap-4 md:grid-cols-2"
            >
                <div class="grid gap-2">
                    <Label for="purok_id">{{
                        t('residents.fields.purok')
                    }}</Label>
                    <OptionSelect
                        id="purok_id"
                        v-model="form.purok_id"
                        :options="options.puroks"
                        required
                    />
                    <InputError :message="form.errors.purok_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="street_address">{{
                        t('residents.fields.street_address')
                    }}</Label>
                    <Input id="street_address" v-model="form.street_address" />
                    <InputError :message="form.errors.street_address" />
                </div>
                <div class="grid gap-2">
                    <Label for="residence_type_id">{{
                        t('residents.fields.residence_type')
                    }}</Label>
                    <OptionSelect
                        id="residence_type_id"
                        v-model="form.residence_type_id"
                        :options="options.lookups['residence-type'] ?? []"
                    />
                    <InputError :message="form.errors.residence_type_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="residing_since">{{
                        t('residents.fields.residing_since')
                    }}</Label>
                    <Input
                        id="residing_since"
                        v-model="form.residing_since"
                        type="date"
                    />
                    <InputError :message="form.errors.residing_since" />
                </div>
                <CheckboxField
                    v-model="form.is_permanent_resident"
                    :label="t('residents.fields.is_permanent_resident')"
                    class="md:col-span-2"
                />
                <div
                    v-if="!form.is_permanent_resident"
                    class="grid gap-2 md:col-span-2"
                >
                    <Label for="alternate_address">{{
                        t('residents.fields.alternate_address')
                    }}</Label>
                    <Input
                        id="alternate_address"
                        v-model="form.alternate_address"
                    />
                    <InputError :message="form.errors.alternate_address" />
                </div>
                <div class="grid gap-2">
                    <Label for="mobile_number">{{
                        t('residents.fields.mobile_number')
                    }}</Label>
                    <Input
                        id="mobile_number"
                        v-model="form.mobile_number"
                        inputmode="tel"
                        :placeholder="t('residents.fields.mobile_hint')"
                    />
                    <InputError :message="form.errors.mobile_number" />
                </div>
                <div class="grid gap-2">
                    <Label for="landline_number">{{
                        t('residents.fields.landline_number')
                    }}</Label>
                    <Input
                        id="landline_number"
                        v-model="form.landline_number"
                        inputmode="tel"
                    />
                    <InputError :message="form.errors.landline_number" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">{{ t('residents.fields.email') }}</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="facebook_url">{{
                        t('residents.fields.facebook_url')
                    }}</Label>
                    <Input
                        id="facebook_url"
                        v-model="form.facebook_url"
                        type="url"
                    />
                    <InputError :message="form.errors.facebook_url" />
                </div>
            </section>

            <section
                v-show="tab === 'government'"
                class="grid gap-4 md:grid-cols-3"
            >
                <div
                    v-for="field in [
                        'national_id',
                        'drivers_license',
                        'tin',
                        'sss_gsis',
                        'pagibig',
                        'philhealth',
                    ] as const"
                    :key="field"
                    class="grid gap-2"
                >
                    <Label :for="field">{{
                        t(`residents.fields.${field}`)
                    }}</Label>
                    <Input :id="field" v-model="form[field]" />
                    <InputError :message="form.errors[field]" />
                </div>
                <CheckboxField
                    v-model="form.is_registered_voter"
                    :label="t('residents.fields.is_registered_voter')"
                    class="md:col-span-3"
                />
                <template v-if="form.is_registered_voter">
                    <div class="grid gap-2">
                        <Label for="voter_id">{{
                            t('residents.fields.voter_id')
                        }}</Label>
                        <Input id="voter_id" v-model="form.voter_id" />
                        <InputError :message="form.errors.voter_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="precinct_number">{{
                            t('residents.fields.precinct_number')
                        }}</Label>
                        <Input
                            id="precinct_number"
                            v-model="form.precinct_number"
                        />
                        <InputError :message="form.errors.precinct_number" />
                    </div>
                </template>
            </section>

            <section
                v-show="tab === 'sectors'"
                class="grid gap-2 md:grid-cols-3"
            >
                <CheckboxField
                    v-for="sector in options.lookups.sector ?? []"
                    :key="sector.value"
                    :model-value="form.sector_ids.includes(sector.value)"
                    :label="sector.label"
                    @update:model-value="
                        (checked: boolean) =>
                            toggleSector(sector.value, checked)
                    "
                />
                <InputError :message="form.errors.sector_ids" />
            </section>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">{{
                    form.processing ? t('common.saving') : t('common.save')
                }}</Button>
                <Badge v-if="resident?.is_deceased" variant="outline">{{
                    t('residents.deceased')
                }}</Badge>
            </div>
        </form>

        <FamilyMembersPanel
            v-if="resident && tab === 'family'"
            :resident-id="resident.id"
            :members="familyMembers"
            :relationships="options.lookups.relationship ?? []"
        />

        <div v-if="resident && tab === 'history'" class="grid gap-10">
            <EducationPanel
                :resident-id="resident.id"
                :educations="educations"
                :schools="options.lookups.school ?? []"
                :courses="options.lookups.course ?? []"
            />
            <WorkHistoryPanel
                :resident-id="resident.id"
                :work-histories="workHistories"
                :companies="options.lookups.company ?? []"
                :positions="options.lookups.position ?? []"
                :statuses="options.lookups['employment-status'] ?? []"
            />
        </div>

        <PhotoPanel
            v-if="resident && tab === 'photo'"
            :resident="resident"
            :max-kilobytes="limits.photo_max_kilobytes"
        />
    </div>
</template>
