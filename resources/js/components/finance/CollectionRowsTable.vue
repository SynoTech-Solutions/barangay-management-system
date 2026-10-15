<script setup lang="ts">
import { t } from '@/composables/useI18n';
import type { CollectionReportRow } from '@/types';

defineProps<{ rows: CollectionReportRow[] }>();
</script>

<template>
    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="border-b text-left">
                <th class="py-1 pr-2">{{ t('collections.paid_on') }}</th>
                <th class="py-1 pr-2">{{ t('collections.report.source') }}</th>
                <th class="py-1 pr-2">{{ t('collections.type') }}</th>
                <th class="py-1 pr-2">
                    {{ t('collections.report.reference') }}
                </th>
                <th class="py-1 pr-2">{{ t('collections.or_number') }}</th>
                <th class="py-1 pr-2">{{ t('collections.payer') }}</th>
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
                v-for="row in rows"
                :key="`${row.source}-${row.source_id}`"
                class="border-b"
            >
                <td class="py-1 pr-2">{{ row.paid_on }}</td>
                <td class="py-1 pr-2">{{ row.source_label }}</td>
                <td class="py-1 pr-2">{{ row.type }}</td>
                <td class="py-1 pr-2 font-mono">{{ row.reference }}</td>
                <td class="py-1 pr-2 font-mono">{{ row.or_number }}</td>
                <td class="py-1 pr-2">{{ row.payer }}</td>
                <td class="py-1 text-right tabular-nums">{{ row.amount }}</td>
                <td class="py-1 text-right tabular-nums">
                    {{ row.doc_stamp }}
                </td>
                <td class="py-1 text-right tabular-nums">{{ row.total }}</td>
            </tr>
        </tbody>
    </table>
</template>
