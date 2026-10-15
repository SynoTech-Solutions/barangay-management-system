<script setup lang="ts">
import { t } from '@/composables/useI18n';
import type { CollectionGroup, CollectionSums } from '@/types';

defineProps<{
    title: string;
    groups: CollectionGroup[];
    totals: CollectionSums;
}>();
</script>

<template>
    <table class="w-full border-collapse text-sm">
        <caption class="mb-1 text-left font-semibold">
            {{
                title
            }}
        </caption>
        <thead>
            <tr class="border-b text-left">
                <th class="py-1">{{ t('collections.report.group') }}</th>
                <th class="py-1 text-right">
                    {{ t('collections.report.count') }}
                </th>
                <th class="py-1 text-right">{{ t('collections.amount') }}</th>
                <th class="py-1 text-right">
                    {{ t('collections.report.doc_stamp') }}
                </th>
                <th class="py-1 text-right">
                    {{ t('collections.report.total') }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="group in groups"
                :key="`${group.source}-${group.label}`"
                class="border-b"
            >
                <td class="py-1">{{ group.label }}</td>
                <td class="py-1 text-right tabular-nums">{{ group.count }}</td>
                <td class="py-1 text-right tabular-nums">{{ group.amount }}</td>
                <td class="py-1 text-right tabular-nums">
                    {{ group.doc_stamp }}
                </td>
                <td class="py-1 text-right tabular-nums">{{ group.total }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="font-semibold">
                <td class="py-1">{{ t('documents.totals') }}</td>
                <td class="py-1 text-right tabular-nums">{{ totals.count }}</td>
                <td class="py-1 text-right tabular-nums">
                    {{ totals.amount }}
                </td>
                <td class="py-1 text-right tabular-nums">
                    {{ totals.doc_stamp }}
                </td>
                <td class="py-1 text-right tabular-nums">{{ totals.total }}</td>
            </tr>
        </tfoot>
    </table>
</template>
