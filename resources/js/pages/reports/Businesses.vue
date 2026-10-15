<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import BarChart from '@/components/residents/BarChart.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { t } from '@/composables/useI18n';
import { businesses as businessReport } from '@/routes/reports';
import type { SelectOption, StatisticsRow } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.business_report'), href: businessReport() },
        ],
    },
});

const props = defineProps<{
    rows: StatisticsRow[];
    filters: { dimension: string; status: string | null };
    dimensions: SelectOption<string>[];
    statuses: SelectOption<string>[];
}>();

const state = reactive({ ...props.filters });

const total = computed(() =>
    props.rows.reduce((sum, row) => sum + row.total, 0),
);

function apply(): void {
    router.get(
        businessReport.url(),
        Object.fromEntries(
            Object.entries(state).filter(([, value]) => value !== null),
        ) as Record<string, string>,
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('business_report.title')" />

    <div class="flex max-w-4xl flex-col gap-4 p-4">
        <Heading
            :title="t('business_report.title')"
            :description="t('business_report.description')"
        />

        <div class="grid gap-3 rounded-lg border p-3 md:grid-cols-2">
            <OptionSelect
                v-model="state.dimension"
                :options="dimensions"
                :placeholder="t('business_report.dimension')"
                required
                @update:model-value="apply"
            />
            <OptionSelect
                v-model="state.status"
                :options="statuses"
                :placeholder="t('businesses.all_statuses')"
                @update:model-value="apply"
            />
        </div>

        <p v-if="!rows.length" class="text-sm text-muted-foreground">
            {{ t('reports.no_data') }}
        </p>
        <BarChart v-else :bars="rows" />
        <p class="text-sm font-medium">
            {{ t('business_report.total', { total }) }}
        </p>
    </div>
</template>
