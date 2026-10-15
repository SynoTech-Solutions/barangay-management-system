<script setup lang="ts">
import { FeatureFlag } from '@/contracts/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { show as photo } from '@/routes/residents/photo';
import type { FamilyMember, ResidentDetail } from '@/types';

const props = defineProps<{
    resident: ResidentDetail;
    familyMembers: FamilyMember[];
    header: { barangay: string; municipality: string; province: string | null };
    preparedBy: string;
    printedAt: string;
}>();

const { feature } = useAccess();
const r = props.resident;

const rows = computed<Array<[string, string | number | null]>>(() => [
    [t('residents.fields.resident_number'), r.resident_number],
    [t('residents.fields.birthdate'), r.birthdate ?? t('residents.unknown')],
    [t('residents.fields.age'), r.age],
    [t('residents.fields.birthplace'), r.birthplace?.label ?? null],
    [t('residents.fields.gender'), r.gender_label],
    [t('residents.fields.civil_status'), r.civil_status_label],
    [t('residents.fields.nickname'), r.nickname],
    [t('residents.fields.spouse'), r.spouse?.full_name ?? null],
    [t('residents.fields.nationality'), r.labels.nationality_id],
    [t('residents.fields.religion'), r.labels.religion_id],
    [t('residents.fields.blood_type'), r.labels.blood_type_id],
    [t('residents.fields.profession'), r.labels.profession_id],
    [t('residents.fields.height_cm'), r.height_cm],
    [t('residents.fields.weight_kg'), r.weight_kg],
    [t('residents.fields.purok'), r.purok ?? null],
    [t('residents.fields.street_address'), r.street_address],
    [t('residents.fields.residence_type'), r.labels.residence_type_id],
    [t('residents.fields.residing_since'), r.residing_since],
    [t('residents.fields.mobile_number'), r.mobile_number],
    [t('residents.fields.email'), r.email],
    [t('residents.fields.precinct_number'), r.precinct_number],
]);

const flags = computed(() =>
    [
        [
            r.is_living_with_parents,
            t('residents.fields.is_living_with_parents'),
        ],
        [r.is_senior, t('residents.fields.senior_by_age')],
        [
            r.is_senior_citizen_member,
            t('residents.fields.is_senior_citizen_member'),
        ],
        [r.is_pwd, t('residents.fields.is_pwd')],
        [r.is_single_parent, t('residents.fields.is_single_parent')],
        [r.is_indigent, t('residents.fields.is_indigent')],
    ]
        .filter(([on]) => on)
        .map(([, label]) => label as string),
);

function printPage(): void {
    window.print();
}

function closePage(): void {
    window.close();
}

const showPhoto = computed(
    () => r.has_photo && feature(FeatureFlag.ResidentPhoto),
);
</script>

<template>
    <Head :title="`${t('print.title')} - ${resident.full_name}`" />

    <div class="mx-auto max-w-3xl bg-white p-8 text-black print:p-0">
        <div class="mb-6 flex justify-end gap-2 print:hidden">
            <Button variant="outline" @click="closePage">{{
                t('print.back')
            }}</Button>
            <Button @click="printPage">{{ t('print.print') }}</Button>
        </div>

        <header class="mb-6 text-center">
            <p class="text-sm">{{ t('print.republic') }}</p>
            <p v-if="header.province" class="text-sm">{{ header.province }}</p>
            <p class="text-sm">{{ header.municipality }}</p>
            <p class="font-semibold">{{ header.barangay }}</p>
            <h1 class="mt-4 text-xl font-bold uppercase">
                {{ t('print.title') }}
            </h1>
        </header>

        <section class="mb-6 flex gap-6">
            <img
                v-if="showPhoto"
                :src="
                    photo.url(resident.id, {
                        query: { v: resident.photo_version ?? 0 },
                    })
                "
                :alt="t('photo.alt', { name: resident.full_name })"
                class="h-36 w-36 border object-cover"
            />
            <div
                v-else
                class="flex h-36 w-36 items-center justify-center border text-xs"
            >
                {{ t('print.no_photo') }}
            </div>
            <div>
                <p class="text-2xl font-semibold">
                    {{ resident.full_name }}
                    <span v-if="resident.is_deceased" class="text-base">{{
                        t('print.deceased_marker')
                    }}</span>
                </p>
                <p v-if="resident.is_deceased" class="text-sm">
                    {{ t('residents.fields.deceased_on') }}:
                    {{ resident.deceased_on ?? t('residents.unknown') }} ·
                    {{ t('residents.fields.cause_of_death') }}:
                    {{ resident.cause_of_death ?? '' }}
                </p>
                <ul class="mt-2 text-sm">
                    <li v-for="flag in flags" :key="flag">✓ {{ flag }}</li>
                </ul>
            </div>
        </section>

        <table class="mb-6 w-full border-collapse text-sm">
            <tbody>
                <tr
                    v-for="[label, value] in rows"
                    :key="label"
                    class="border-b"
                >
                    <th class="w-1/3 py-1 pr-4 text-left font-normal">
                        {{ label }}
                    </th>
                    <td class="py-1">{{ value ?? '' }}</td>
                </tr>
            </tbody>
        </table>

        <h2 class="mb-2 font-semibold">{{ t('family.title') }}</h2>
        <table class="mb-8 w-full border-collapse text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-1">{{ t('family.name') }}</th>
                    <th class="py-1">{{ t('family.relationship') }}</th>
                    <th class="py-1">{{ t('family.age') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!familyMembers.length">
                    <td colspan="3" class="py-1">{{ t('family.empty') }}</td>
                </tr>
                <tr
                    v-for="member in familyMembers"
                    :key="member.id"
                    class="border-b"
                >
                    <td class="py-1">{{ member.full_name }}</td>
                    <td class="py-1">{{ member.relationship }}</td>
                    <td class="py-1">{{ member.age ?? '' }}</td>
                </tr>
            </tbody>
        </table>

        <footer class="flex justify-between text-xs">
            <span>{{ t('print.prepared_by') }}: {{ preparedBy }}</span>
            <span>{{
                t('print.printed_at', {
                    date: new Date(printedAt).toLocaleString(),
                })
            }}</span>
        </footer>
    </div>
</template>
