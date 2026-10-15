<script setup lang="ts">
import { FeatureFlag, Permission } from '@/contracts/generated';
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/composables/useAccess';
import { t } from '@/composables/useI18n';
import { edit, exportMethod, index } from '@/routes/reports/saved';
import type { PaginationMeta, SavedReport } from '@/types';

defineProps<{
    report: SavedReport;
    columns: Array<{ key: string; label: string }>;
    rows: { data: Array<Record<string, string>>; meta: PaginationMeta };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.saved_reports'), href: index() }],
    },
});

const { can, feature } = useAccess();
</script>

<template>
    <Head :title="report.name" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading :title="report.name" :description="report.dataset_label" />
            <div class="flex gap-2">
                <Button
                    v-if="feature(FeatureFlag.GridExcelExport)"
                    variant="outline"
                    as-child
                >
                    <a :href="exportMethod.url(report.id)">{{
                        t('saved_reports.export')
                    }}</a>
                </Button>
                <Button
                    v-if="can(Permission.ReportsDesign)"
                    variant="outline"
                    as-child
                >
                    <Link :href="edit(report.id)">{{ t('common.edit') }}</Link>
                </Button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="p-3"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!rows.data.length">
                        <td
                            :colspan="columns.length"
                            class="p-3 text-muted-foreground"
                        >
                            {{ t('saved_reports.no_rows') }}
                        </td>
                    </tr>
                    <tr
                        v-for="(row, position) in rows.data"
                        :key="position"
                        class="border-t"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="p-3"
                        >
                            {{ row[column.key] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="rows.meta" />
    </div>
</template>
