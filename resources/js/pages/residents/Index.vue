<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import {
    create,
    edit,
    exportMethod,
    index,
    restore,
    show,
} from '@/routes/residents';
import {
    color as bulkColor,
    purok as bulkPurok,
} from '@/routes/residents/bulk';
import { create as smsGroupCreate } from '@/routes/sms/residents/group';
import { create as smsSendCreate } from '@/routes/sms/residents/send';
import type {
    ColorOption,
    Paginated,
    PoliticalTag,
    ResidentListFilters,
    ResidentListItem,
    SelectOption,
} from '@/types';

const SEARCH_DELAY_MS = 300;
const VOTER_YES = 'yes';
const VOTER_NO = 'no';

defineOptions({
    layout: { breadcrumbs: [{ title: t('nav.residents'), href: index() }] },
});

const props = defineProps<{
    residents: Paginated<ResidentListItem>;
    filters: ResidentListFilters;
    options: {
        puroks: SelectOption[];
        sectors: SelectOption[];
        genders: SelectOption<string>[];
        civil_statuses: SelectOption<string>[];
        flags: SelectOption<string>[];
        sorts: SelectOption<string>[];
    };
    politicalTags: Record<string, PoliticalTag> | null;
    colorCategories: ColorOption[];
}>();

const { can, feature } = useAccess();

const state = reactive({
    search: props.filters.search,
    purok_id: props.filters.purok_id,
    gender: props.filters.gender,
    civil_status: props.filters.civil_status,
    voter:
        props.filters.voter === null
            ? null
            : props.filters.voter
              ? VOTER_YES
              : VOTER_NO,
    flags: [...props.filters.flags],
    sector_id: props.filters.sector_id,
    include_incomplete: props.filters.include_incomplete,
    removed: props.filters.removed,
    sort: props.filters.sort as string | null,
    direction: props.filters.direction as string | null,
});

const voterOptions: SelectOption<string>[] = [
    { value: VOTER_YES, label: t('residents.filters.voter_yes') },
    { value: VOTER_NO, label: t('residents.filters.voter_no') },
];
const directionOptions: SelectOption<string>[] = [
    { value: 'asc', label: t('residents.filters.direction_asc') },
    { value: 'desc', label: t('residents.filters.direction_desc') },
];

const selected = ref<number[]>([]);
const targetPurok = ref<number | null>(null);
const targetColor = ref<number | null>(null);

const canTagColors = computed(
    () =>
        props.colorCategories.length > 0 &&
        can(Permission.PoliticalTagsManage) &&
        feature(FeatureFlag.PoliticalColorTagging) &&
        feature(FeatureFlag.ResidentBulkEdit) &&
        !props.filters.removed,
);

const canSendSms = computed(
    () =>
        can(Permission.SmsSend) &&
        feature(FeatureFlag.Sms) &&
        feature(FeatureFlag.SmsFromResidentLists) &&
        !props.filters.removed,
);
const canGroupSms = computed(
    () =>
        can(Permission.SmsGroupsManage) &&
        feature(FeatureFlag.Sms) &&
        feature(FeatureFlag.SmsGroups) &&
        feature(FeatureFlag.SmsFromResidentLists) &&
        !props.filters.removed,
);

const canBulkEdit = computed(
    () =>
        (can(Permission.ResidentsBulkEdit) &&
            feature(FeatureFlag.ResidentBulkEdit) &&
            !props.filters.removed) ||
        canTagColors.value ||
        canSendSms.value ||
        canGroupSms.value,
);
const canMovePurok = computed(
    () =>
        can(Permission.ResidentsBulkEdit) &&
        feature(FeatureFlag.ResidentBulkEdit),
);

const selectedSmsQuery = computed(() => ({
    scope: 'selected',
    resident_ids: selected.value,
}));
const filteredSmsQuery = computed(() => ({
    ...query.value,
    scope: 'filtered',
}));

const query = computed(() => {
    const entries = Object.entries({
        ...state,
        include_incomplete: state.include_incomplete ? 1 : null,
        removed: state.removed ? 1 : null,
        flags: state.flags.length ? state.flags : null,
    }).filter(([, value]) => value !== null && value !== '');

    return Object.fromEntries(entries) as Record<
        string,
        string | number | string[]
    >;
});

function apply(): void {
    selected.value = [];
    router.get(index.url(), query.value, {
        preserveState: true,
        replace: true,
    });
}

const applySearch = useDebounceFn(apply, SEARCH_DELAY_MS);

function clearFilters(): void {
    router.get(index.url());
}

function toggleFlag(flag: string, checked: boolean): void {
    state.flags = checked
        ? [...state.flags, flag]
        : state.flags.filter((value) => value !== flag);
    apply();
}

function toggleRow(id: number, checked: boolean): void {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((value) => value !== id);
}

const allSelected = computed(
    () =>
        props.residents.data.length > 0 &&
        props.residents.data.every((row) => selected.value.includes(row.id)),
);

function toggleAll(checked: boolean): void {
    selected.value = checked ? props.residents.data.map((row) => row.id) : [];
}

function moveSelected(): void {
    if (targetPurok.value === null) {
        return;
    }

    router.post(
        bulkPurok.url(),
        { resident_ids: selected.value, purok_id: targetPurok.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                targetPurok.value = null;
            },
        },
    );
}

function colorSelected(clear: boolean): void {
    router.post(
        bulkColor.url(),
        {
            resident_ids: selected.value,
            color_category_id: clear ? null : targetColor.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                targetColor.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="t('residents.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('residents.title')"
                :description="t('residents.description')"
            />
            <div class="flex gap-2">
                <Button
                    v-if="
                        can(Permission.ResidentsExport) &&
                        feature(FeatureFlag.GridExcelExport)
                    "
                    variant="outline"
                    as-child
                >
                    <a :href="exportMethod.url({ query })">{{
                        t('residents.export')
                    }}</a>
                </Button>
                <Button v-if="can(Permission.ResidentsCreate)" as-child>
                    <Link :href="create()">{{ t('residents.new') }}</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3">
            <div class="grid gap-3 md:grid-cols-4">
                <Input
                    v-model="state.search"
                    :placeholder="t('residents.filters.search_placeholder')"
                    class="md:col-span-2"
                    @input="applySearch"
                />
                <OptionSelect
                    v-model="state.purok_id"
                    :options="options.puroks"
                    :placeholder="`${t('residents.columns.purok')}: ${t('residents.filters.all')}`"
                    @update:model-value="apply"
                />
                <OptionSelect
                    v-model="state.gender"
                    :options="options.genders"
                    :placeholder="`${t('residents.columns.gender')}: ${t('residents.filters.all')}`"
                    @update:model-value="apply"
                />
                <OptionSelect
                    v-model="state.civil_status"
                    :options="options.civil_statuses"
                    :placeholder="`${t('residents.fields.civil_status')}: ${t('residents.filters.all')}`"
                    @update:model-value="apply"
                />
                <OptionSelect
                    v-model="state.voter"
                    :options="voterOptions"
                    :placeholder="`${t('residents.voter')}: ${t('residents.filters.all')}`"
                    @update:model-value="apply"
                />
                <OptionSelect
                    v-if="feature(FeatureFlag.SectorTagging)"
                    v-model="state.sector_id"
                    :options="options.sectors"
                    :placeholder="`${t('residents.fields.sectors')}: ${t('residents.filters.all')}`"
                    @update:model-value="apply"
                />
                <div class="flex gap-2">
                    <OptionSelect
                        v-model="state.sort"
                        :options="options.sorts"
                        :placeholder="t('residents.filters.sort')"
                        @update:model-value="apply"
                    />
                    <OptionSelect
                        v-model="state.direction"
                        :options="directionOptions"
                        :placeholder="t('residents.filters.direction_asc')"
                        @update:model-value="apply"
                    />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="text-sm font-medium">{{
                    t('residents.filters.flags')
                }}</span>
                <CheckboxField
                    v-for="flag in options.flags"
                    :key="flag.value"
                    :model-value="state.flags.includes(flag.value)"
                    :label="flag.label"
                    @update:model-value="
                        (checked: boolean) => toggleFlag(flag.value, checked)
                    "
                />
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <CheckboxField
                    v-model="state.include_incomplete"
                    :label="t('residents.filters.include_incomplete')"
                    @update:model-value="apply"
                />
                <CheckboxField
                    v-if="can(Permission.ResidentsDelete)"
                    v-model="state.removed"
                    :label="t('residents.filters.removed')"
                    @update:model-value="apply"
                />
                <Button variant="link" size="sm" @click="clearFilters">{{
                    t('residents.filters.clear')
                }}</Button>
            </div>
        </div>

        <div
            v-if="canBulkEdit && selected.length"
            class="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 p-3 text-sm"
        >
            <span>{{
                t('residents.bulk.selected', { count: selected.length })
            }}</span>
            <template v-if="canMovePurok">
                <Label for="bulk_purok">{{
                    t('residents.bulk.move_to')
                }}</Label>
                <div class="w-56">
                    <OptionSelect
                        id="bulk_purok"
                        v-model="targetPurok"
                        :options="options.puroks"
                    />
                </div>
                <Button
                    size="sm"
                    :disabled="targetPurok === null"
                    @click="moveSelected"
                    >{{ t('residents.bulk.apply') }}</Button
                >
            </template>
            <template v-if="canTagColors">
                <Label for="bulk_color">{{
                    t('residents.bulk.color_to')
                }}</Label>
                <div class="w-48">
                    <OptionSelect
                        id="bulk_color"
                        v-model="targetColor"
                        :options="colorCategories"
                    />
                </div>
                <Button
                    size="sm"
                    :disabled="targetColor === null"
                    @click="colorSelected(false)"
                    >{{ t('residents.bulk.apply_color') }}</Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    @click="colorSelected(true)"
                    >{{ t('residents.bulk.clear_color') }}</Button
                >
            </template>
            <Button v-if="canSendSms" size="sm" variant="outline" as-child>
                <Link :href="smsSendCreate({ query: selectedSmsQuery })">{{
                    t('residents.bulk.send_sms')
                }}</Link>
            </Button>
            <Button v-if="canGroupSms" size="sm" variant="outline" as-child>
                <Link :href="smsGroupCreate({ query: selectedSmsQuery })">{{
                    t('residents.bulk.add_to_sms_group')
                }}</Link>
            </Button>
            <Button size="sm" variant="outline" @click="selected = []">{{
                t('residents.bulk.clear')
            }}</Button>
        </div>

        <div
            v-if="(canSendSms || canGroupSms) && residents.meta.total > 0"
            class="flex flex-wrap items-center gap-3 text-sm"
        >
            <span class="text-muted-foreground">{{
                t('residents.bulk.sms_all_matching', {
                    count: residents.meta.total,
                })
            }}</span>
            <Button v-if="canSendSms" size="sm" variant="outline" as-child>
                <Link :href="smsSendCreate({ query: filteredSmsQuery })">{{
                    t('residents.bulk.send_sms')
                }}</Link>
            </Button>
            <Button v-if="canGroupSms" size="sm" variant="outline" as-child>
                <Link :href="smsGroupCreate({ query: filteredSmsQuery })">{{
                    t('residents.bulk.add_to_sms_group')
                }}</Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th v-if="canBulkEdit" class="w-8 p-3">
                            <CheckboxField
                                :model-value="allSelected"
                                label=""
                                @update:model-value="toggleAll"
                            />
                        </th>
                        <th class="p-3">{{ t('residents.columns.number') }}</th>
                        <th class="p-3">{{ t('residents.columns.name') }}</th>
                        <th class="p-3">{{ t('residents.columns.age') }}</th>
                        <th class="p-3">{{ t('residents.columns.gender') }}</th>
                        <th class="p-3">{{ t('residents.columns.purok') }}</th>
                        <th v-if="politicalTags" class="p-3">
                            {{ t('residents.color') }}
                        </th>
                        <th class="p-3">{{ t('residents.columns.status') }}</th>
                        <th class="p-3 text-right">
                            {{ t('common.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in residents.data"
                        :key="row.id"
                        class="border-t"
                    >
                        <td v-if="canBulkEdit" class="p-3">
                            <CheckboxField
                                :model-value="selected.includes(row.id)"
                                label=""
                                @update:model-value="
                                    (checked: boolean) =>
                                        toggleRow(row.id, checked)
                                "
                            />
                        </td>
                        <td class="p-3 font-mono">{{ row.resident_number }}</td>
                        <td class="p-3">
                            <Link
                                v-if="!row.is_removed"
                                :href="show(row.id)"
                                class="font-medium hover:underline"
                                >{{ row.full_name }}</Link
                            >
                            <span v-else class="font-medium">{{
                                row.full_name
                            }}</span>
                            <div class="text-muted-foreground">
                                {{ row.birthdate ?? t('residents.unknown') }}
                            </div>
                        </td>
                        <td class="p-3">{{ row.age ?? '' }}</td>
                        <td class="p-3">{{ row.gender_label ?? '' }}</td>
                        <td class="p-3">{{ row.purok ?? '' }}</td>
                        <td v-if="politicalTags" class="p-3">
                            <span
                                v-if="politicalTags[row.id]"
                                class="inline-flex items-center gap-1"
                            >
                                <span
                                    class="inline-block size-3 rounded-full border"
                                    :style="{
                                        backgroundColor:
                                            politicalTags[row.id].color_hex,
                                    }"
                                />
                                {{ politicalTags[row.id].name }}
                            </span>
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-if="row.is_removed"
                                    variant="outline"
                                    >{{ t('residents.removed') }}</Badge
                                >
                                <Badge
                                    v-if="row.is_deceased"
                                    variant="outline"
                                    >{{ t('residents.deceased') }}</Badge
                                >
                                <Badge
                                    v-if="row.is_incomplete"
                                    variant="outline"
                                    >{{ t('residents.incomplete') }}</Badge
                                >
                                <Badge
                                    v-if="row.is_senior"
                                    variant="secondary"
                                    >{{ t('residents.senior') }}</Badge
                                >
                                <Badge v-if="row.is_pwd" variant="secondary">{{
                                    t('residents.pwd')
                                }}</Badge>
                                <Badge
                                    v-if="row.is_registered_voter"
                                    variant="secondary"
                                    >{{ t('residents.voter') }}</Badge
                                >
                            </div>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="
                                        row.is_removed &&
                                        can(Permission.ResidentsDelete)
                                    "
                                    variant="outline"
                                    size="sm"
                                    @click="router.post(restore.url(row.id))"
                                    >{{ t('residents.restore') }}</Button
                                >
                                <Button
                                    v-if="
                                        !row.is_removed &&
                                        can(Permission.ResidentsUpdate)
                                    "
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link :href="edit(row.id)">{{
                                        t('residents.edit')
                                    }}</Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="residents.meta" />
    </div>
</template>
