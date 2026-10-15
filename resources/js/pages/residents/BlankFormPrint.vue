<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import type { SelectOption } from '@/types';

const FAMILY_ROWS = 6;
const EDUCATION_ROWS = 3;
const WORK_ROWS = 2;

defineProps<{
    header: {
        barangay: string;
        municipality: string;
        province: string | null;
        telephone: string | null;
    };
    sections: { sectors: boolean; work_education: boolean };
    sectors: SelectOption[];
}>();

const general = [
    'title',
    'last_name',
    'first_name',
    'middle_name',
    'name_extension',
    'nickname',
    'birthdate',
    'birthplace',
    'gender',
    'civil_status',
    'nationality',
    'religion',
    'blood_type',
    'height_cm',
    'weight_kg',
    'profession',
    'skill',
    'income_bracket',
];
const address = [
    'purok',
    'street_address',
    'residence_type',
    'residing_since',
    'alternate_address',
    'mobile_number',
    'landline_number',
    'email',
];
const government = [
    'national_id',
    'drivers_license',
    'tin',
    'sss_gsis',
    'pagibig',
    'philhealth',
    'voter_id',
    'precinct_number',
];
const circumstances = [
    'is_registered_voter',
    'is_permanent_resident',
    'is_living_with_parents',
    'is_senior_citizen_member',
    'is_pwd',
    'is_single_parent',
    'is_indigent',
];

function printPage(): void {
    window.print();
}
</script>

<template>
    <Head :title="t('blank_form.title')" />

    <div
        class="blank-form mx-auto max-w-[8.5in] bg-white p-8 text-[10pt] text-black print:p-0"
    >
        <div class="mb-4 flex justify-end print:hidden">
            <Button @click="printPage">{{ t('print.print') }}</Button>
        </div>

        <header class="mb-4 text-center leading-tight">
            <p>{{ t('print.republic') }}</p>
            <p v-if="header.province">{{ header.province }}</p>
            <p>{{ header.municipality }}</p>
            <p class="font-semibold">{{ header.barangay }}</p>
            <p v-if="header.telephone">{{ header.telephone }}</p>
            <h1 class="mt-2 text-lg font-bold uppercase">
                {{ t('blank_form.title') }}
            </h1>
        </header>

        <h2 class="section-title">{{ t('blank_form.general') }}</h2>
        <div class="grid grid-cols-3 gap-x-4">
            <div v-for="field in general" :key="field" class="blank-field">
                {{ t(`residents.fields.${field}`) }}
            </div>
        </div>

        <h2 class="section-title">{{ t('blank_form.address') }}</h2>
        <div class="grid grid-cols-2 gap-x-4">
            <div v-for="field in address" :key="field" class="blank-field">
                {{ t(`residents.fields.${field}`) }}
            </div>
        </div>

        <h2 class="section-title">{{ t('blank_form.government') }}</h2>
        <div class="grid grid-cols-3 gap-x-4">
            <div v-for="field in government" :key="field" class="blank-field">
                {{ t(`residents.fields.${field}`) }}
            </div>
        </div>
        <div class="mt-2 grid grid-cols-2 gap-x-4">
            <p v-for="field in circumstances" :key="field">
                ☐ {{ t(`residents.fields.${field}`) }}
            </p>
        </div>

        <h2 class="section-title">{{ t('blank_form.family') }}</h2>
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="cell">{{ t('family.name') }}</th>
                    <th class="cell">{{ t('family.relationship') }}</th>
                    <th class="cell">{{ t('family.birthdate') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in FAMILY_ROWS" :key="row">
                    <td class="cell h-6" />
                    <td class="cell" />
                    <td class="cell" />
                </tr>
            </tbody>
        </table>

        <template v-if="sections.sectors">
            <h2 class="section-title">{{ t('blank_form.sectors') }}</h2>
            <div class="grid grid-cols-3 gap-x-4">
                <p v-for="sector in sectors" :key="sector.value">
                    ☐ {{ sector.label }}
                </p>
            </div>
        </template>

        <template v-if="sections.work_education">
            <h2 class="section-title">{{ t('blank_form.education') }}</h2>
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="cell">{{ t('education.school') }}</th>
                        <th class="cell">{{ t('education.course') }}</th>
                        <th class="cell">{{ t('education.started_on') }}</th>
                        <th class="cell">{{ t('education.ended_on') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in EDUCATION_ROWS" :key="row">
                        <td class="cell h-6" />
                        <td class="cell" />
                        <td class="cell" />
                        <td class="cell" />
                    </tr>
                </tbody>
            </table>

            <h2 class="section-title">{{ t('blank_form.work') }}</h2>
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="cell">{{ t('work.company') }}</th>
                        <th class="cell">{{ t('work.position') }}</th>
                        <th class="cell">{{ t('work.employment_status') }}</th>
                        <th class="cell">{{ t('work.started_on') }}</th>
                        <th class="cell">{{ t('work.ended_on') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in WORK_ROWS" :key="row">
                        <td class="cell h-6" />
                        <td class="cell" />
                        <td class="cell" />
                        <td class="cell" />
                        <td class="cell" />
                    </tr>
                </tbody>
            </table>
        </template>

        <div class="mt-10 grid grid-cols-4 gap-4 text-center">
            <p class="col-span-1 border-t pt-1">
                {{ t('blank_form.signature') }}
            </p>
            <p class="border-t pt-1">{{ t('blank_form.date_filled') }}</p>
            <p class="border-t pt-1">{{ t('blank_form.posted_by') }}</p>
            <p class="border-t pt-1">{{ t('blank_form.date_posted') }}</p>
        </div>
    </div>
</template>

<style>
@page legal-form {
    size: legal portrait;
    margin: 12mm;
}

@media print {
    .blank-form {
        page: legal-form;
    }
}

.blank-form .section-title {
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    border-bottom: 1px solid #000;
    font-weight: 600;
    text-transform: uppercase;
}

.blank-form .blank-field {
    border-bottom: 1px solid #555;
    padding-top: 1.1rem;
    font-size: 8pt;
}

.blank-form .cell {
    border: 1px solid #555;
    padding: 2px 4px;
    text-align: left;
}
</style>
