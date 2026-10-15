<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import ResidentClearancesPanel from '@/components/documents/ResidentClearancesPanel.vue';
import ResidentFormsPanel from '@/components/forms/ResidentFormsPanel.vue';
import ResidentFacts from '@/components/residents/ResidentFacts.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { destroy, edit, index, print, show } from '@/routes/residents';
import { show as blotterShow } from '@/routes/blotters';
import { create as issueClearance } from '@/routes/clearances';
import { create as issueIdCard } from '@/routes/id-cards';
import { show as photo } from '@/routes/residents/photo';
import { create as composeSms } from '@/routes/sms';
import type {
    BarangayClearance,
    FamilyMember,
    FormTransaction,
    PoliticalTag,
    ResidentBlotter,
    ResidentDetail,
    ResidentEducation,
    ResidentWorkHistory,
} from '@/types';

type RelatedTab = { key: string; label: string; visible: boolean };

const props = defineProps<{
    resident: ResidentDetail;
    familyMembers: FamilyMember[];
    educations: ResidentEducation[];
    workHistories: ResidentWorkHistory[];
    politicalTag: PoliticalTag | null;
    clearances: BarangayClearance[] | null;
    forms: FormTransaction[] | null;
    blotters: ResidentBlotter[] | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.residents'), href: index() }] },
});

const { can, feature } = useAccess();
const r = props.resident;

const tabs = computed<RelatedTab[]>(() =>
    [
        { key: 'details', label: t('residents.tabs.details'), visible: true },
        { key: 'family', label: t('residents.tabs.family'), visible: true },
        {
            key: 'education',
            label: t('residents.tabs.education'),
            visible: feature(FeatureFlag.ResidentWorkEducation),
        },
        {
            key: 'work',
            label: t('residents.tabs.work'),
            visible: feature(FeatureFlag.ResidentWorkEducation),
        },
        {
            key: 'clearances',
            label: t('residents.tabs.clearances'),
            visible: props.clearances !== null,
        },
        {
            key: 'forms',
            label: t('residents.tabs.forms'),
            visible: props.forms !== null,
        },
        {
            key: 'blotters',
            label: t('residents.tabs.blotters'),
            visible: props.blotters !== null,
        },
    ].filter((tab) => tab.visible),
);
const active = ref('details');

const yesNo = (value: boolean): string =>
    value ? t('common.yes') : t('common.no');

const personal = computed<Array<[string, string | number | null]>>(() => [
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
    [t('residents.fields.body_type'), r.labels.body_type_id],
    [t('residents.fields.health_issue'), r.labels.health_issue_id],
    [t('residents.fields.profession'), r.labels.profession_id],
    [t('residents.fields.skill'), r.labels.skill_id],
    [t('residents.fields.income_bracket'), r.labels.income_bracket_id],
    [t('residents.fields.height_cm'), r.height_cm],
    [t('residents.fields.weight_kg'), r.weight_kg],
]);

const address = computed<Array<[string, string | number | null]>>(() => [
    [t('residents.fields.purok'), r.purok ?? null],
    [t('residents.fields.street_address'), r.street_address],
    [t('residents.fields.residence_type'), r.labels.residence_type_id],
    [t('residents.fields.residing_since'), r.residing_since],
    [
        t('residents.fields.is_permanent_resident'),
        yesNo(r.is_permanent_resident),
    ],
    [t('residents.fields.alternate_address'), r.alternate_address],
    [t('residents.fields.mobile_number'), r.mobile_number],
    [t('residents.fields.landline_number'), r.landline_number],
    [t('residents.fields.email'), r.email],
]);

const government = computed<Array<[string, string | number | null]>>(() => [
    [t('residents.fields.national_id'), r.national_id],
    [t('residents.fields.drivers_license'), r.drivers_license],
    [t('residents.fields.tin'), r.tin],
    [t('residents.fields.sss_gsis'), r.sss_gsis],
    [t('residents.fields.pagibig'), r.pagibig],
    [t('residents.fields.philhealth'), r.philhealth],
    [t('residents.fields.is_registered_voter'), yesNo(r.is_registered_voter)],
    [t('residents.fields.voter_id'), r.voter_id],
    [t('residents.fields.precinct_number'), r.precinct_number],
]);

const flags = computed(() =>
    [
        [r.is_senior, t('residents.fields.senior_by_age')],
        [
            r.is_senior_citizen_member,
            t('residents.fields.is_senior_citizen_member'),
        ],
        [r.is_pwd, t('residents.fields.is_pwd')],
        [r.is_single_parent, t('residents.fields.is_single_parent')],
        [r.is_indigent, t('residents.fields.is_indigent')],
        [
            r.is_living_with_parents,
            t('residents.fields.is_living_with_parents'),
        ],
    ]
        .filter(([on]) => on)
        .map(([, label]) => label as string),
);

const facebookLink = computed(() =>
    r.facebook_url && /^https?:\/\//i.test(r.facebook_url)
        ? r.facebook_url
        : null,
);

function remove(): void {
    if (window.confirm(t('residents.delete_confirm'))) {
        router.delete(destroy.url(r.id));
    }
}
</script>

<template>
    <Head :title="resident.full_name" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <img
                    v-if="
                        resident.has_photo && feature(FeatureFlag.ResidentPhoto)
                    "
                    :src="
                        photo.url(resident.id, {
                            query: { v: resident.photo_version ?? 0 },
                        })
                    "
                    :alt="t('photo.alt', { name: resident.full_name })"
                    class="h-28 w-28 rounded-md border object-cover"
                />
                <div class="grid gap-2">
                    <Heading
                        :title="resident.full_name"
                        :description="resident.resident_number"
                    />
                    <div class="flex flex-wrap gap-1">
                        <Badge v-if="resident.is_deceased" variant="outline">{{
                            t('residents.deceased')
                        }}</Badge>
                        <Badge
                            v-if="resident.is_incomplete"
                            variant="outline"
                            >{{ t('residents.incomplete') }}</Badge
                        >
                        <Badge v-if="politicalTag" variant="outline">
                            <span
                                class="mr-1 inline-block size-2.5 rounded-full"
                                :style="{
                                    backgroundColor: politicalTag.color_hex,
                                }"
                            />
                            {{ politicalTag.name }}
                        </Badge>
                        <Badge
                            v-for="sector in resident.sectors ?? []"
                            :key="sector.id"
                            variant="secondary"
                            >{{ sector.label }}</Badge
                        >
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="can(Permission.ResidentsUpdate)" as-child>
                    <Link :href="edit(resident.id)">{{
                        t('residents.edit')
                    }}</Link>
                </Button>
                <Button
                    v-if="
                        feature(FeatureFlag.ResidentIdCards) &&
                        can(Permission.IdCardsIssue) &&
                        !resident.is_deceased
                    "
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            issueIdCard({ query: { resident_id: resident.id } })
                        "
                        >{{ t('residents.issue_id_card') }}</Link
                    >
                </Button>
                <Button
                    v-if="
                        feature(FeatureFlag.BarangayClearance) &&
                        can(Permission.ClearancesIssue) &&
                        !resident.is_deceased
                    "
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            issueClearance({
                                query: { resident_id: resident.id },
                            })
                        "
                        >{{ t('residents.issue_clearance') }}</Link
                    >
                </Button>
                <Button
                    v-if="
                        feature(FeatureFlag.Sms) &&
                        can(Permission.SmsSend) &&
                        resident.mobile_number &&
                        !resident.is_deceased
                    "
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            composeSms({ query: { resident_id: resident.id } })
                        "
                        >{{ t('residents.send_sms') }}</Link
                    >
                </Button>
                <Button
                    v-if="feature(FeatureFlag.ResidentProfilePrint)"
                    variant="outline"
                    as-child
                >
                    <a :href="print.url(resident.id)" target="_blank">{{
                        t('residents.print')
                    }}</a>
                </Button>
                <Button
                    v-if="can(Permission.ResidentsDelete)"
                    variant="outline"
                    @click="remove"
                    >{{ t('residents.delete') }}</Button
                >
            </div>
        </div>

        <nav class="flex flex-wrap gap-2 border-b pb-2">
            <Button
                v-for="tab in tabs"
                :key="tab.key"
                size="sm"
                :variant="active === tab.key ? 'default' : 'ghost'"
                @click="active = tab.key"
                >{{ tab.label }}</Button
            >
        </nav>

        <div v-if="active === 'details'" class="grid gap-8">
            <section class="grid gap-3">
                <Heading
                    variant="small"
                    :title="t('residents.tabs.personal')"
                />
                <ResidentFacts :facts="personal" />
                <div v-if="flags.length" class="flex flex-wrap gap-1">
                    <Badge
                        v-for="flag in flags"
                        :key="flag"
                        variant="secondary"
                        >{{ flag }}</Badge
                    >
                </div>
                <ResidentFacts
                    v-if="resident.is_deceased"
                    :facts="[
                        [
                            t('residents.fields.deceased_on'),
                            resident.deceased_on,
                        ],
                        [
                            t('residents.fields.cause_of_death'),
                            resident.cause_of_death,
                        ],
                    ]"
                />
            </section>
            <section class="grid gap-3">
                <Heading variant="small" :title="t('residents.tabs.address')" />
                <ResidentFacts :facts="address" />
                <a
                    v-if="facebookLink"
                    :href="facebookLink"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    class="text-sm underline"
                    >{{ t('residents.fields.facebook_url') }}</a
                >
            </section>
            <section class="grid gap-3">
                <Heading
                    variant="small"
                    :title="t('residents.tabs.government')"
                />
                <ResidentFacts :facts="government" />
            </section>
            <p class="text-sm text-muted-foreground">
                {{
                    t('residents.registered_on', {
                        date: resident.created_at
                            ? new Date(resident.created_at).toLocaleString()
                            : '',
                        name: resident.created_by ?? '',
                    })
                }}
                <br />
                {{
                    t('residents.updated_on', {
                        date: resident.updated_at
                            ? new Date(resident.updated_at).toLocaleString()
                            : '',
                        name: resident.updated_by ?? '',
                    })
                }}
            </p>
        </div>

        <table
            v-if="active === 'family'"
            class="w-full rounded-lg border text-sm"
        >
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="p-2">{{ t('family.name') }}</th>
                    <th class="p-2">{{ t('family.relationship') }}</th>
                    <th class="p-2">{{ t('family.birthdate') }}</th>
                    <th class="p-2">{{ t('family.age') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!familyMembers.length">
                    <td colspan="4" class="p-2 text-muted-foreground">
                        {{ t('family.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="member in familyMembers"
                    :key="member.id"
                    class="border-t"
                >
                    <td class="p-2">
                        <Link
                            v-if="member.related_resident"
                            :href="show(member.related_resident.id)"
                            class="hover:underline"
                            >{{ member.full_name }}</Link
                        >
                        <span v-else>{{ member.full_name }}</span>
                    </td>
                    <td class="p-2">{{ member.relationship }}</td>
                    <td class="p-2">{{ member.birthdate ?? '' }}</td>
                    <td class="p-2">{{ member.age ?? '' }}</td>
                </tr>
            </tbody>
        </table>

        <table
            v-if="active === 'education'"
            class="w-full rounded-lg border text-sm"
        >
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="p-2">{{ t('education.school') }}</th>
                    <th class="p-2">{{ t('education.course') }}</th>
                    <th class="p-2">{{ t('education.started_on') }}</th>
                    <th class="p-2">{{ t('education.ended_on') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!educations.length">
                    <td colspan="4" class="p-2 text-muted-foreground">
                        {{ t('education.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="education in educations"
                    :key="education.id"
                    class="border-t"
                >
                    <td class="p-2">{{ education.school }}</td>
                    <td class="p-2">{{ education.course }}</td>
                    <td class="p-2">{{ education.started_on ?? '' }}</td>
                    <td class="p-2">{{ education.ended_on ?? '' }}</td>
                </tr>
            </tbody>
        </table>

        <table
            v-if="active === 'blotters' && blotters !== null"
            class="w-full rounded-lg border text-sm"
        >
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="p-2">{{ t('blotters.number') }}</th>
                    <th class="p-2">{{ t('blotters.incident_at') }}</th>
                    <th class="p-2">{{ t('blotters.incident_type') }}</th>
                    <th class="p-2">{{ t('blotters.role') }}</th>
                    <th class="p-2">{{ t('documents.status') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!blotters.length">
                    <td colspan="5" class="p-2 text-muted-foreground">
                        {{ t('blotters.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="entry in blotters"
                    :key="`${entry.id}-${entry.role}`"
                    class="border-t"
                >
                    <td class="p-2 font-mono">
                        <Link
                            :href="blotterShow(entry.id)"
                            class="hover:underline"
                            >{{ entry.blotter_number }}</Link
                        >
                    </td>
                    <td class="p-2">
                        {{ new Date(entry.incident_at).toLocaleDateString() }}
                    </td>
                    <td class="p-2">{{ entry.incident_type }}</td>
                    <td class="p-2">{{ entry.role_label }}</td>
                    <td class="p-2">{{ entry.status_label }}</td>
                </tr>
            </tbody>
        </table>

        <ResidentClearancesPanel
            v-if="active === 'clearances' && clearances !== null"
            :clearances="clearances"
        />

        <ResidentFormsPanel
            v-if="active === 'forms' && forms !== null"
            :forms="forms"
            :resident-id="r.id"
        />

        <table
            v-if="active === 'work'"
            class="w-full rounded-lg border text-sm"
        >
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="p-2">{{ t('work.company') }}</th>
                    <th class="p-2">{{ t('work.position') }}</th>
                    <th class="p-2">{{ t('work.employment_status') }}</th>
                    <th class="p-2">{{ t('work.started_on') }}</th>
                    <th class="p-2">{{ t('work.ended_on') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!workHistories.length">
                    <td colspan="5" class="p-2 text-muted-foreground">
                        {{ t('work.empty') }}
                    </td>
                </tr>
                <tr
                    v-for="work in workHistories"
                    :key="work.id"
                    class="border-t"
                >
                    <td class="p-2">{{ work.company }}</td>
                    <td class="p-2">{{ work.position }}</td>
                    <td class="p-2">{{ work.employment_status }}</td>
                    <td class="p-2">{{ work.started_on ?? '' }}</td>
                    <td class="p-2">
                        {{
                            work.is_current
                                ? t('work.current')
                                : (work.ended_on ?? '')
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
