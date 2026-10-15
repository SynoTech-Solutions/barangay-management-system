<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { t } from '@/composables/useI18n';
import type { ReportFilters, ReportOptions, ResidentListItem } from '@/types';

const props = defineProps<{
    residents: ResidentListItem[];
    total: number;
    limit: number;
    filters: ReportFilters;
    options: ReportOptions;
    barangay: string;
    printedAt: string;
}>();

const subtitle = computed(() => {
    const parts: string[] = [];
    const attribute = props.options.attributes.find(
        (entry) => entry.value === props.filters.attribute,
    );
    if (attribute && props.filters.attribute_values.length) {
        const labels = attribute.options
            .filter((option) =>
                props.filters.attribute_values.includes(option.value),
            )
            .map((option) => option.label);
        parts.push(`${attribute.label}: ${labels.join(', ')}`);
    }
    if (props.filters.age_from !== null || props.filters.age_to !== null) {
        parts.push(
            `${t('residents.columns.age')}: ${props.filters.age_from ?? 0}–${props.filters.age_to ?? ''}`,
        );
    }
    const flagLabels = props.options.flags
        .filter((flag) => props.filters.flags.includes(flag.value))
        .map((flag) => flag.label);
    if (flagLabels.length) {
        parts.push(flagLabels.join(', '));
    }
    const purok = props.options.puroks.find(
        (entry) => entry.value === props.filters.purok_id,
    );
    if (purok) {
        parts.push(`${t('residents.columns.purok')}: ${purok.label}`);
    }

    return parts.join(' · ');
});

function printPage(): void {
    window.print();
}
</script>

<template>
    <Head :title="t('reports.print_title')" />

    <div class="mx-auto max-w-5xl bg-white p-8 text-sm text-black print:p-0">
        <div class="mb-4 flex justify-end print:hidden">
            <Button @click="printPage">{{ t('reports.print') }}</Button>
        </div>
        <header class="mb-4 text-center">
            <p class="font-semibold">{{ barangay }}</p>
            <h1 class="text-lg font-bold uppercase">
                {{ t('reports.print_title') }}
            </h1>
            <p v-if="subtitle">{{ subtitle }}</p>
        </header>
        <p v-if="total > residents.length" class="mb-2">
            {{ t('reports.truncated', { limit, total }) }}
        </p>
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-1">{{ t('residents.columns.number') }}</th>
                    <th class="py-1">{{ t('residents.columns.name') }}</th>
                    <th class="py-1">{{ t('residents.columns.age') }}</th>
                    <th class="py-1">{{ t('residents.columns.gender') }}</th>
                    <th class="py-1">
                        {{ t('residents.fields.civil_status') }}
                    </th>
                    <th class="py-1">{{ t('residents.columns.purok') }}</th>
                    <th class="py-1">
                        {{ t('residents.fields.residing_since') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in residents" :key="row.id" class="border-b">
                    <td class="py-1 font-mono">{{ row.resident_number }}</td>
                    <td class="py-1">{{ row.full_name }}</td>
                    <td class="py-1">{{ row.age ?? '' }}</td>
                    <td class="py-1">{{ row.gender_label ?? '' }}</td>
                    <td class="py-1">{{ row.civil_status_label ?? '' }}</td>
                    <td class="py-1">{{ row.purok ?? '' }}</td>
                    <td class="py-1">
                        {{
                            row.length_of_stay
                                ? t(
                                      'residents.length_of_stay',
                                      row.length_of_stay,
                                  )
                                : ''
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="mt-2 font-semibold">{{ t('reports.total', { total }) }}</p>
        <p class="mt-4 text-xs">
            {{
                t('reports.printed_at', {
                    date: new Date(printedAt).toLocaleString(),
                })
            }}
        </p>
    </div>
</template>
