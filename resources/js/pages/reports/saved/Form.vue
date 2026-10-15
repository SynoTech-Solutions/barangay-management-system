<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CheckboxField from '@/components/residents/CheckboxField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/composables/useI18n';
import { deactivate, index, store, update } from '@/routes/reports/saved';
import type {
    ReportCatalog,
    ReportColumnOption,
    ReportFilterDraft,
    ReportSortDraft,
    SavedReport,
} from '@/types';

const MAX_FILTERS = 10;
const MAX_SORTS = 3;

const props = defineProps<{
    report: SavedReport | null;
    catalog: ReportCatalog;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.saved_reports'), href: index() }],
    },
});

const report = props.report;
const form = useForm<{
    name: string;
    dataset: string;
    columns: string[];
    filters: ReportFilterDraft[];
    sort: ReportSortDraft[];
    group_by: string | null;
}>({
    name: report?.name ?? '',
    dataset: report?.dataset ?? props.catalog.datasets[0]?.value ?? '',
    columns: report?.definition.columns ?? [],
    filters: report?.definition.filters.map((filter) => ({ ...filter })) ?? [],
    sort: report?.definition.sort.map((sort) => ({ ...sort })) ?? [],
    group_by: report?.definition.group_by ?? null,
});

const errors = form.errors as Record<string, string | undefined>;

const columns = computed<ReportColumnOption[]>(
    () =>
        props.catalog.datasets.find((item) => item.value === form.dataset)
            ?.columns ?? [],
);

const groupable = computed(() =>
    columns.value.filter((column) => column.groupable),
);

watch(
    () => form.dataset,
    (dataset, previous) => {
        if (previous !== undefined && dataset !== previous) {
            form.columns = [];
            form.filters = [];
            form.sort = [];
            form.group_by = null;
        }
    },
);

function column(key: string): ReportColumnOption | undefined {
    return columns.value.find((item) => item.key === key);
}

function toggleColumn(key: string, checked: boolean): void {
    form.columns = checked
        ? [...form.columns, key]
        : form.columns.filter((item) => item !== key);
}

function addFilter(): void {
    const first = columns.value[0];

    if (first) {
        form.filters = [
            ...form.filters,
            {
                column: first.key,
                operator: first.operators[0]?.value ?? '',
                value: null,
            },
        ];
    }
}

function setFilterColumn(filter: ReportFilterDraft, key: string): void {
    filter.column = key;
    filter.operator = column(key)?.operators[0]?.value ?? '';
    filter.value = null;
}

function needsValue(filter: ReportFilterDraft): boolean {
    return (
        column(filter.column)?.operators.find(
            (operator) => operator.value === filter.operator,
        )?.needs_value ?? false
    );
}

function inputType(filter: ReportFilterDraft): string {
    const type = column(filter.column)?.type;

    if (type === 'date') {
        return 'date';
    }

    return type === 'number' || type === 'money' ? 'number' : 'text';
}

function addSort(): void {
    const first = form.columns[0] ?? columns.value[0]?.key;

    if (first) {
        form.sort = [...form.sort, { column: first, direction: 'asc' }];
    }
}

function submit(): void {
    if (report === null) {
        form.post(store.url());
    } else {
        form.put(update.url(report.id));
    }
}

function remove(): void {
    if (report && window.confirm(t('saved_reports.deactivate_confirm'))) {
        router.post(deactivate.url(report.id));
    }
}
</script>

<template>
    <Head :title="report ? t('saved_reports.edit') : t('saved_reports.new')" />

    <form class="flex max-w-4xl flex-col gap-6 p-4" @submit.prevent="submit">
        <Heading
            :title="report ? t('saved_reports.edit') : t('saved_reports.new')"
            :description="t('saved_reports.designer_help')"
        />

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="name">{{ t('saved_reports.name') }}</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="dataset">{{ t('saved_reports.dataset') }}</Label>
                <select
                    id="dataset"
                    v-model="form.dataset"
                    class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                >
                    <option
                        v-for="dataset in catalog.datasets"
                        :key="dataset.value"
                        :value="dataset.value"
                    >
                        {{ dataset.label }}
                    </option>
                </select>
                <InputError :message="form.errors.dataset" />
            </div>
        </div>

        <section class="grid gap-2">
            <Heading variant="small" :title="t('saved_reports.columns')" />
            <div class="grid gap-1 sm:grid-cols-2 md:grid-cols-3">
                <CheckboxField
                    v-for="item in columns"
                    :key="item.key"
                    :model-value="form.columns.includes(item.key)"
                    :label="item.label"
                    @update:model-value="
                        (checked: boolean) => toggleColumn(item.key, checked)
                    "
                />
            </div>
            <InputError :message="errors.columns ?? errors['columns.0']" />
        </section>

        <section class="grid gap-2">
            <Heading variant="small" :title="t('saved_reports.filters')" />
            <div
                v-for="(filter, position) in form.filters"
                :key="position"
                class="grid gap-2 md:grid-cols-[1fr_1fr_1fr_auto]"
            >
                <select
                    :value="filter.column"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    :aria-label="t('saved_reports.column')"
                    @change="
                        setFilterColumn(
                            filter,
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option
                        v-for="item in columns"
                        :key="item.key"
                        :value="item.key"
                    >
                        {{ item.label }}
                    </option>
                </select>
                <select
                    v-model="filter.operator"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    :aria-label="t('saved_reports.operator')"
                >
                    <option
                        v-for="operator in column(filter.column)?.operators ??
                        []"
                        :key="operator.value"
                        :value="operator.value"
                    >
                        {{ operator.label }}
                    </option>
                </select>
                <template v-if="needsValue(filter)">
                    <select
                        v-if="column(filter.column)?.type === 'choice'"
                        v-model="filter.value"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        :aria-label="t('saved_reports.value')"
                    >
                        <option
                            v-for="choice in column(filter.column)?.choices ??
                            []"
                            :key="choice.value"
                            :value="choice.value"
                        >
                            {{ choice.label }}
                        </option>
                    </select>
                    <Input
                        v-else
                        :model-value="filter.value ?? ''"
                        :type="inputType(filter)"
                        :aria-label="t('saved_reports.value')"
                        @update:model-value="
                            (value: string | number) =>
                                (filter.value = String(value))
                        "
                    />
                </template>
                <span v-else />
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :aria-label="t('saved_reports.remove')"
                    @click="
                        form.filters = form.filters.filter(
                            (_, index) => index !== position,
                        )
                    "
                    ><Trash2 class="size-4"
                /></Button>
                <InputError
                    class="md:col-span-4"
                    :message="
                        errors[`filters.${position}.value`] ??
                        errors[`filters.${position}.column`] ??
                        errors[`filters.${position}.operator`]
                    "
                />
            </div>
            <Button
                type="button"
                variant="outline"
                class="w-fit"
                :disabled="form.filters.length >= MAX_FILTERS"
                @click="addFilter"
                >{{ t('saved_reports.add_filter') }}</Button
            >
        </section>

        <section class="grid gap-2">
            <Heading variant="small" :title="t('saved_reports.sorting')" />
            <div
                v-for="(sort, position) in form.sort"
                :key="position"
                class="grid gap-2 md:grid-cols-[1fr_1fr_auto]"
            >
                <select
                    v-model="sort.column"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    :aria-label="t('saved_reports.column')"
                >
                    <option
                        v-for="item in columns"
                        :key="item.key"
                        :value="item.key"
                    >
                        {{ item.label }}
                    </option>
                </select>
                <select
                    v-model="sort.direction"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    :aria-label="t('saved_reports.direction')"
                >
                    <option
                        v-for="direction in catalog.directions"
                        :key="direction.value"
                        :value="direction.value"
                    >
                        {{ direction.label }}
                    </option>
                </select>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :aria-label="t('saved_reports.remove')"
                    @click="
                        form.sort = form.sort.filter(
                            (_, index) => index !== position,
                        )
                    "
                    ><Trash2 class="size-4"
                /></Button>
            </div>
            <Button
                type="button"
                variant="outline"
                class="w-fit"
                :disabled="form.sort.length >= MAX_SORTS"
                @click="addSort"
                >{{ t('saved_reports.add_sort') }}</Button
            >
        </section>

        <section class="grid max-w-sm gap-2">
            <Label for="group_by">{{ t('saved_reports.group_by') }}</Label>
            <select
                id="group_by"
                v-model="form.group_by"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option :value="null">
                    {{ t('saved_reports.no_grouping') }}
                </option>
                <option
                    v-for="item in groupable"
                    :key="item.key"
                    :value="item.key"
                >
                    {{ item.label }}
                </option>
            </select>
            <p class="text-xs text-muted-foreground">
                {{ t('saved_reports.group_help') }}
            </p>
            <InputError :message="form.errors.group_by" />
        </section>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">{{
                form.processing ? t('common.saving') : t('common.save')
            }}</Button>
            <Button
                v-if="report"
                type="button"
                variant="outline"
                @click="remove"
                >{{ t('saved_reports.deactivate') }}</Button
            >
        </div>
    </form>
</template>
