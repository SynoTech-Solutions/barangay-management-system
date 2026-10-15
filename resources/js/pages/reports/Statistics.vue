<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import BarChart from '@/components/residents/BarChart.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import { statistics as statisticsRoute } from '@/routes/reports';
import type { StatisticsRow } from '@/types';

const props = defineProps<{
    statistics: {
        population: number;
        deceased: number;
        flags: Record<string, number>;
        genders: StatisticsRow[];
        civil_statuses: StatisticsRow[];
        age_bands: StatisticsRow[];
        puroks: StatisticsRow[];
        sectors: StatisticsRow[] | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('nav.statistics'), href: statisticsRoute() }],
    },
});

const flagRows = computed<StatisticsRow[]>(() =>
    Object.entries(props.statistics.flags).map(([key, total]) => ({
        key,
        label: t(`reports.flags.${key}`),
        total,
    })),
);

const sections = computed(() =>
    [
        { key: 'flags', title: t('reports.flags_title'), rows: flagRows.value },
        {
            key: 'genders',
            title: t('reports.genders_title'),
            rows: props.statistics.genders,
        },
        {
            key: 'civil',
            title: t('reports.civil_statuses_title'),
            rows: props.statistics.civil_statuses,
        },
        {
            key: 'ages',
            title: t('reports.age_bands_title'),
            rows: props.statistics.age_bands,
        },
        {
            key: 'puroks',
            title: t('reports.puroks_title'),
            rows: props.statistics.puroks,
        },
        {
            key: 'sectors',
            title: t('reports.sectors_title'),
            rows: props.statistics.sectors,
        },
    ].filter((section) => section.rows !== null),
);

function printPage(): void {
    window.print();
}
</script>

<template>
    <Head :title="t('reports.statistics_title')" />

    <div class="flex max-w-5xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="t('reports.statistics_title')"
                :description="t('reports.statistics_description')"
            />
            <Button variant="outline" class="print:hidden" @click="printPage">{{
                t('reports.print')
            }}</Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('reports.population') }}
                </p>
                <p class="text-3xl font-semibold tabular-nums">
                    {{ statistics.population }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('reports.deceased') }}
                </p>
                <p class="text-3xl font-semibold tabular-nums">
                    {{ statistics.deceased }}
                </p>
            </div>
        </div>

        <section
            v-for="section in sections"
            :key="section.key"
            class="grid gap-3 rounded-lg border p-4"
        >
            <Heading variant="small" :title="section.title" />
            <BarChart :bars="section.rows ?? []" />
        </section>
    </div>
</template>
