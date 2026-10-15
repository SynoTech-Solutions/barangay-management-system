<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import BarChart from '@/components/residents/BarChart.vue';
import OptionSelect from '@/components/residents/OptionSelect.vue';
import { t } from '@/composables/useI18n';
import { politicalStatus } from '@/routes/reports';
import type { SelectOption } from '@/types';

type ColorCount = { name: string; color_hex: string; total: number };

const props = defineProps<{
    purokId: number | null;
    puroks: SelectOption[];
    byColor: ColorCount[];
    byPurok: { purok: string; colors: ColorCount[] }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('nav.political_status'), href: politicalStatus() },
        ],
    },
});

const purok = ref<number | null>(props.purokId);

function bars(colors: ColorCount[]) {
    return colors.map((color) => ({
        key: color.name,
        label: color.name,
        total: color.total,
        color: color.color_hex,
    }));
}

function apply(): void {
    router.get(
        politicalStatus.url(),
        purok.value === null ? {} : { purok_id: purok.value },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('reports.political_title')" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="t('reports.political_title')"
            :description="t('reports.political_description')"
        />

        <section class="grid gap-3 rounded-lg border p-4">
            <div class="max-w-xs">
                <OptionSelect
                    v-model="purok"
                    :options="puroks"
                    :placeholder="t('reports.all_puroks')"
                    @update:model-value="apply"
                />
            </div>
            <BarChart v-if="byColor.length" :bars="bars(byColor)" />
            <p v-else class="text-sm text-muted-foreground">
                {{ t('reports.no_data') }}
            </p>
        </section>

        <section class="grid gap-4">
            <Heading variant="small" :title="t('reports.by_purok_title')" />
            <div
                v-for="group in byPurok"
                :key="group.purok"
                class="grid gap-2 rounded-lg border p-4"
            >
                <p class="font-medium">{{ group.purok }}</p>
                <BarChart :bars="bars(group.colors)" />
            </div>
        </section>
    </div>
</template>
