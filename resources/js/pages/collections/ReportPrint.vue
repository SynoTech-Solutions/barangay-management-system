<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CollectionGroupTable from '@/components/finance/CollectionGroupTable.vue';
import CollectionRowsTable from '@/components/finance/CollectionRowsTable.vue';
import ReportSheet from '@/components/finance/ReportSheet.vue';
import { t } from '@/composables/useI18n';
import { longDate } from '@/lib/format';
import type {
    CollectionGroup,
    CollectionReportFilters,
    CollectionReportRow,
    CollectionSums,
    PrintHeader,
} from '@/types';

defineProps<{
    header: PrintHeader;
    rows: CollectionReportRow[];
    limit: number;
    totals: CollectionSums;
    bySource: CollectionGroup[];
    byType: CollectionGroup[];
    filters: CollectionReportFilters;
    printedAt: string;
}>();
</script>

<template>
    <Head :title="t('collections.report.print_title')" />

    <ReportSheet
        :header="header"
        :title="t('collections.report.print_title')"
        :subtitle="
            t('finance.date_range', {
                from: longDate(filters.from),
                to: longDate(filters.to),
            })
        "
        :printed-at="printedAt"
        :truncated-at="totals.count > rows.length ? limit : null"
    >
        <div class="mb-6 grid gap-6">
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
        <CollectionRowsTable :rows="rows" />
    </ReportSheet>
</template>
