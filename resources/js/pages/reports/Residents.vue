<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
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
import { reportQuery } from '@/composables/useReportQuery';
import { residents as residentsReport } from '@/routes/reports';
import { print as printReport } from '@/routes/reports/residents';
import { exportMethod, show } from '@/routes/residents';
import type {
    Paginated,
    ReportFilters,
    ReportOptions,
    ResidentListItem,
} from '@/types';

const props = defineProps<{
    residents: Paginated<ResidentListItem>;
    filters: ReportFilters;
    options: ReportOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.resident_reports'), href: residentsReport() },
        ],
    },
});

const { can, feature } = useAccess();
const state = reactive<ReportFilters>({
    ...props.filters,
    flags: [...props.filters.flags],
    attribute_values: [...props.filters.attribute_values],
});

const ages = reactive<{ from: string | number; to: string | number }>({
    from: props.filters.age_from ?? '',
    to: props.filters.age_to ?? '',
});

const attributeOptions = computed(
    () =>
        props.options.attributes.find(
            (entry) => entry.value === state.attribute,
        )?.options ?? [],
);
const query = computed(() => reportQuery(state));

function apply(): void {
    state.age_from = ages.from === '' ? null : Number(ages.from);
    state.age_to = ages.to === '' ? null : Number(ages.to);
    router.get(residentsReport.url(), query.value, {
        preserveState: true,
        replace: true,
    });
}

function toggleFlag(value: string, checked: boolean): void {
    state.flags = checked
        ? [...state.flags, value]
        : state.flags.filter((entry) => entry !== value);
}

function toggleValue(value: number, checked: boolean): void {
    state.attribute_values = checked
        ? [...state.attribute_values, value]
        : state.attribute_values.filter((entry) => entry !== value);
}

function changeAttribute(): void {
    state.attribute_values = [];
}
</script>

<template>
    <Head :title="t('reports.residents_title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('reports.residents_title')"
                :description="t('reports.residents_description')"
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
                <Button variant="outline" as-child>
                    <a :href="printReport.url({ query })" target="_blank">{{
                        t('reports.print')
                    }}</a>
                </Button>
            </div>
        </div>

        <form class="grid gap-3 rounded-lg border p-3" @submit.prevent="apply">
            <div class="grid gap-3 md:grid-cols-4">
                <Input
                    v-model="state.search"
                    :placeholder="t('residents.filters.search_placeholder')"
                    class="md:col-span-2"
                />
                <OptionSelect
                    v-model="state.purok_id"
                    :options="options.puroks"
                    :placeholder="`${t('residents.columns.purok')}: ${t('residents.filters.all')}`"
                />
                <OptionSelect
                    v-model="state.gender"
                    :options="options.genders"
                    :placeholder="`${t('residents.columns.gender')}: ${t('residents.filters.all')}`"
                />
                <OptionSelect
                    v-model="state.civil_status"
                    :options="options.civil_statuses"
                    :placeholder="`${t('residents.fields.civil_status')}: ${t('residents.filters.all')}`"
                />
                <OptionSelect
                    v-if="feature(FeatureFlag.SectorTagging)"
                    v-model="state.sector_id"
                    :options="options.sectors"
                    :placeholder="`${t('residents.fields.sectors')}: ${t('residents.filters.all')}`"
                />
                <div class="flex items-center gap-2">
                    <Label for="age_from" class="shrink-0">{{
                        t('reports.age_from')
                    }}</Label>
                    <Input
                        id="age_from"
                        v-model="ages.from"
                        type="number"
                        min="0"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Label for="age_to" class="shrink-0">{{
                        t('reports.age_to')
                    }}</Label>
                    <Input
                        id="age_to"
                        v-model="ages.to"
                        type="number"
                        min="0"
                    />
                </div>
                <OptionSelect
                    v-model="state.attribute"
                    :options="options.attributes"
                    :placeholder="t('reports.attribute')"
                    @update:model-value="changeAttribute"
                />
                <OptionSelect
                    v-model="state.sort"
                    :options="options.sorts"
                    :placeholder="t('residents.filters.sort')"
                />
            </div>
            <div
                v-if="attributeOptions.length"
                class="flex flex-wrap gap-x-4 gap-y-2"
            >
                <span class="text-sm font-medium">{{
                    t('reports.attribute_values')
                }}</span>
                <CheckboxField
                    v-for="option in attributeOptions"
                    :key="option.value"
                    :model-value="state.attribute_values.includes(option.value)"
                    :label="option.label"
                    @update:model-value="
                        (checked: boolean) => toggleValue(option.value, checked)
                    "
                />
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
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
            <div class="flex items-center gap-4">
                <CheckboxField
                    v-model="state.include_incomplete"
                    :label="t('residents.filters.include_incomplete')"
                />
                <Button type="submit" size="sm">{{
                    t('common.search')
                }}</Button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">{{ t('residents.columns.number') }}</th>
                        <th class="p-3">{{ t('residents.columns.name') }}</th>
                        <th class="p-3">{{ t('residents.columns.age') }}</th>
                        <th class="p-3">{{ t('residents.columns.gender') }}</th>
                        <th class="p-3">
                            {{ t('residents.fields.civil_status') }}
                        </th>
                        <th class="p-3">{{ t('residents.columns.purok') }}</th>
                        <th class="p-3">
                            {{ t('residents.fields.residing_since') }}
                        </th>
                        <th class="p-3">{{ t('residents.columns.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in residents.data"
                        :key="row.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">{{ row.resident_number }}</td>
                        <td class="p-3">
                            <Link
                                :href="show(row.id)"
                                class="font-medium hover:underline"
                                >{{ row.full_name }}</Link
                            >
                        </td>
                        <td class="p-3">{{ row.age ?? '' }}</td>
                        <td class="p-3">{{ row.gender_label ?? '' }}</td>
                        <td class="p-3">{{ row.civil_status_label ?? '' }}</td>
                        <td class="p-3">{{ row.purok ?? '' }}</td>
                        <td class="p-3">
                            {{
                                row.length_of_stay
                                    ? t(
                                          'residents.length_of_stay',
                                          row.length_of_stay,
                                      )
                                    : ''
                            }}
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-if="row.is_deceased"
                                    variant="outline"
                                    >{{ t('residents.deceased') }}</Badge
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
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="residents.meta" />
    </div>
</template>
