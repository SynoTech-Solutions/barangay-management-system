<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import CollectionGroupTable from '@/components/finance/CollectionGroupTable.vue';
import CollectionRowsTable from '@/components/finance/CollectionRowsTable.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { exportMethod, index, print } from '@/routes/collections/report';
import type {
    CollectionGroup,
    CollectionReportFilters,
    CollectionReportRow,
    CollectionSums,
    Paginated,
    SelectOption,
} from '@/types';

const props = defineProps<{
    rows: Paginated<CollectionReportRow>;
    totals: CollectionSums;
    bySource: CollectionGroup[];
    byType: CollectionGroup[];
    filters: CollectionReportFilters;
    sources: SelectOption<string>[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.collection_report'), href: index() }],
    },
});

const { can, feature } = useAccess();
const state = reactive({
    from: props.filters.from,
    to: props.filters.to,
    source: props.filters.source,
});

const query = computed(
    () =>
        Object.fromEntries(
            Object.entries(state).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ) as Record<string, string>,
);

function apply(): void {
    router.get(index.url(), query.value, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head :title="t('collections.report.title')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="t('collections.report.title')"
                :description="t('collections.report.description')"
            />
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <a :href="print.url({ query })" target="_blank">{{
                        t('finance.print_list')
                    }}</a>
                </Button>
                <Button
                    v-if="
                        can(Permission.CollectionsExport) &&
                        feature(FeatureFlag.GridExcelExport)
                    "
                    variant="outline"
                    as-child
                >
                    <a :href="exportMethod.url({ query })">{{
                        t('collections.report.export')
                    }}</a>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-3">
            <Input
                v-model="state.from"
                type="date"
                :aria-label="t('documents.from')"
                @change="apply"
            />
            <Input
                v-model="state.to"
                type="date"
                :aria-label="t('documents.to')"
                @change="apply"
            />
            <OptionSelect
                v-model="state.source"
                :options="sources"
                :placeholder="t('collections.report.all_sources')"
                @update:model-value="apply"
            />
        </div>

        <div class="grid gap-6 rounded-lg border p-3 lg:grid-cols-2">
            <CollectionGroupTable
                :title="t('collections.report.by_source')"
                :groups="bySource"
                :totals="totals"
            />
            <CollectionGroupTable
                :title="t('collections.report.by_type')"
                :groups="byType"
                :totals="totals"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border p-3">
            <CollectionRowsTable :rows="rows.data" />
        </div>

        <Pagination :meta="rows.meta" />
    </div>
</template>
